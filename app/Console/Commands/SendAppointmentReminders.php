<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Carbon\Carbon;
use App\Support\Notify;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind
        {--dry : Show what would be sent without notifying}
        {--lead= : Lead time before start (e.g. 24h, 2h, 90m). If omitted, runs 24h and 2h batches.}
        {--window=20 : Minutes window around target time (± window)}
        {--ping-counselor=0 : Also notify counselor (0/1)}';

    protected $description = 'Send appointment reminders (idempotent) for pending/confirmed sessions at configurable lead times.';

    public function handle(): int
    {
        $now    = Carbon::now()->second(0);
        $window = (int) $this->option('window');
        $dry    = (bool) $this->option('dry');
        $pingC  = (int) $this->option('ping-counselor') === 1;

        $leadOpt = $this->option('lead');

        // Build target lead times (in minutes => label)
        if ($leadOpt) {
            $mins = $this->parseLeadToMinutes((string)$leadOpt);
            if ($mins <= 0) {
                $this->error('Invalid --lead. Use formats like 24h, 2h, 90m.');
                return self::FAILURE;
            }
            $targets = [$mins => $this->leadLabel($mins, (string)$leadOpt)];
        } else {
            // default: 24h and ~2h before
            $targets = [24*60 => '24h', 2*60 => '2h'];
        }

        $sent = 0;

        foreach ($targets as $mins => $label) {
            $from = $now->copy()->addMinutes($mins - $window);
            $to   = $now->copy()->addMinutes($mins + $window);

            $rows = DB::table('tbl_appointments as a')
                ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
                ->whereIn('a.status', ['pending','confirmed'])
                ->whereBetween('a.scheduled_at', [$from, $to])
                ->select([
                    'a.id','a.scheduled_at','a.student_id','a.counselor_id',
                    DB::raw("COALESCE(s.name,'Student') as student_name"),
                    DB::raw("COALESCE(c.name,'Guidance Counselor') as counselor_name"),
                ])
                ->orderBy('a.scheduled_at')
                ->get();

            foreach ($rows as $r) {
                // Idempotency guard (per appt + lead label)
                if ($this->alreadySent((int)$r->id, $label)) {
                    if ($dry) $this->line("[dry] skip (already sent) {$label} → #{$r->id}");
                    continue;
                }

                $dtNice       = Carbon::parse($r->scheduled_at)->format('M d, Y g:i A');
                $studentUrl   = Route::has('appointment.view') ? route('appointment.view', (int)$r->id) : null;
                $counselorUrl = Route::has('counselor.appointments.show') ? route('counselor.appointments.show', (int)$r->id) : null;

                // Student copy
                [$title, $body] = $this->composeStudentMessage($label, (string)$r->student_name, (string)$r->counselor_name, $dtNice);

                if ($dry) {
                    $this->line("[dry] {$label} → #{$r->id} {$dtNice} → {$r->student_name}" . (!empty($r->counselor_id) ? " | counselor_id={$r->counselor_id}" : ""));
                } else {
                    // Student ping
                    Notify::student((int)$r->student_id, $title, $body, $studentUrl);

                    // Optional counselor ping
                    if ($pingC && !empty($r->counselor_id)) {
                        $coTitle = match ($label) {
                            '24h' => 'Tomorrow’s appointment reminder',
                            '2h'  => 'Today’s appointment reminder',
                            default => 'Upcoming appointment reminder',
                        };
                        $coBody  = "You have a counseling appointment on {$dtNice} with student {$r->student_name}.";

                        // Only add URL if route exists to avoid 404s
                        Notify::counselor((int)$r->counselor_id, $coTitle, $coBody, $counselorUrl ?: null);
                    }

                    $this->markSent((int)$r->id, $label);
                    $sent++;
                }
            }
        }

        $this->info($dry ? 'Dry-run complete.' : "Sent {$sent} reminders.");
        return self::SUCCESS;
    }

    private function composeStudentMessage(string $label, string $studentName, string $counselorName, string $dtNice): array
    {
        $counselorName = $counselorName !== '' ? $counselorName : 'your counselor';

        return match ($label) {
            '24h' => [
                'Appointment reminder for tomorrow',
                "Hi {$studentName}, this is a reminder of your counseling appointment tomorrow ({$dtNice}) with {$counselorName}.",
            ],
            '2h' => [
                'Upcoming appointment (in ~2 hours)',
                "Hi {$studentName}, your counseling appointment is today ({$dtNice}) with {$counselorName}. See you soon.",
            ],
            default => [
                'Upcoming counseling appointment',
                "Hi {$studentName}, reminder: your counseling appointment is on {$dtNice} with {$counselorName}.",
            ],
        };
    }

    private function parseLeadToMinutes(string $lead): int
    {
        $lead = trim(strtolower($lead));
        if (preg_match('/^(\d+)\s*h$/', $lead, $m)) return (int)$m[1] * 60; // e.g., 24h
        if (preg_match('/^(\d+)\s*m$/', $lead, $m)) return (int)$m[1];      // e.g., 90m
        if (ctype_digit($lead)) return (int)$lead;                           // raw minutes
        return 0;
    }

    private function leadLabel(int $minutes, string $raw): string
    {
        $raw = strtolower(trim($raw));
        if (in_array($raw, ['24h','2h','3h'], true)) return $raw;
        return $minutes % 60 === 0 ? ($minutes/60).'h' : $minutes.'m';
    }

    private function markSent(int $appointmentId, string $lead): void
    {
        if (Schema::hasTable('appointment_reminders')) {
            try {
                DB::table('appointment_reminders')->insert([
                    'appointment_id' => $appointmentId,
                    'lead'           => $lead,
                    'sent_at'        => now(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                return;
            } catch (\Throwable $e) {
                // fallback to cache on duplicate insert/race or db error
            }
        }
        Cache::put($this->cacheKey($appointmentId, $lead), 1, now()->addDays(7));
    }

    private function alreadySent(int $appointmentId, string $lead): bool
    {
        if (Schema::hasTable('appointment_reminders')) {
            $exists = DB::table('appointment_reminders')
                ->where('appointment_id', $appointmentId)
                ->where('lead', $lead)
                ->exists();
            if ($exists) return true;
        }
        return Cache::has($this->cacheKey($appointmentId, $lead));
    }

    private function cacheKey(int $appointmentId, string $lead): string
    {
        return "appt:remind:{$appointmentId}:{$lead}";
    }
}
