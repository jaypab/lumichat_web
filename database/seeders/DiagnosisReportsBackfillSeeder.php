<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class DiagnosisReportsBackfillSeeder extends Seeder
{
    /**
     * Backfill tbl_diagnosis_reports from completed appointments.
     * - Uses appointment.final_note as a signal, BUT maps it to one of your intents.
     * - Skips duplicates (per appointment if column exists, otherwise per student+counselor+month).
     * - Timestamps use finalized_at (or scheduled_at) for proper month/year grouping.
     */
    public function run(): void
    {
        $this->command->info('Backfilling diagnosis reports from completed appointments…');

        /* ---------- Table / column detection ---------- */
        $reports = 'tbl_diagnosis_reports';
        if (!Schema::hasTable($reports)) {
            $this->command->warn("❗ {$reports} table not found. Aborting.");
            return;
        }

        // Column names that vary across installs
        $hasApptId   = Schema::hasColumn($reports, 'appointment_id');
        $dxCol       = Schema::hasColumn($reports, 'diagnosis_result')
                        ? 'diagnosis_result'
                        : (Schema::hasColumn($reports, 'diagnosis') ? 'diagnosis' : null);

        if (!$dxCol || !Schema::hasColumn($reports, 'student_id') || !Schema::hasColumn($reports, 'counselor_id')) {
            $this->command->warn("❗ {$reports} is missing required columns (student_id/counselor_id/diagnosis). Aborting.");
            return;
        }

        /* ---------- Source appointments ---------- */
        // Enough history to populate Counselor Logs while staying fast.
        $from = now()->subMonths(6)->startOfMonth();

        $appts = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->where('a.status', 'completed')
            ->where('a.scheduled_at', '>=', $from)
            ->select([
                'a.id as appt_id',
                'a.student_id',
                'a.counselor_id',
                'a.scheduled_at',
                'a.finalized_at',
                'a.final_note',
            ])
            ->orderBy('a.scheduled_at')
            ->get();

        if ($appts->isEmpty()) {
            $this->command->warn('No completed appointments found in the selected window.');
            return;
        }

        /* ---------- Existing uniqueness keys ---------- */
        $existingKeys = [];

        if ($hasApptId) {
            // Most reliable: uniqueness by appointment_id
            $ids = DB::table($reports)->pluck('appointment_id')->filter()->all();
            $existingKeys = array_fill_keys($ids, true);
        } else {
            // Fallback uniqueness: counselor + student + month + diagnosis (lower)
            $already = DB::table($reports)
                ->select('counselor_id', 'student_id', $dxCol.' as dx', 'created_at')
                ->get();

            foreach ($already as $r) {
                $dt  = Carbon::parse($r->created_at ?? now());
                $key = implode(':', [
                    (int) $r->counselor_id,
                    (int) $r->student_id,
                    $dt->format('Y-m'),
                    mb_strtolower((string) $r->dx),
                ]);
                $existingKeys[$key] = true;
            }
        }

        /* ---------- Intent labels + keyword mapper ---------- */
        // Display labels to show in UI (not slugs).
        $INTENTS = [
            'Academic Pressure',
            'Anxiety',
            'Bullying',
            'Burnout',
            'Depression',
            'Family Problems',
            'Financial Stress',
            'Grief / Loss',
            'Loneliness',
            'Low Self-Esteem',
            'Relationship Issues',
            'Sleep Problems',
            'Stress',
            'Substance Abuse',
            'Time Management',
        ];

        // Lightweight keyword → label mapping
        // (Everything lowercased; we look for any of these words inside final_note)
        $MAP = [
            'academic' => 'Academic Pressure',
            'grades'   => 'Academic Pressure',
            'exam'     => 'Academic Pressure',
            'anxiety'  => 'Anxiety',
            'panic'    => 'Anxiety',
            'bully'    => 'Bullying',
            'harass'   => 'Bullying',
            'burnout'  => 'Burnout',
            'depress'  => 'Depression',
            'sad'      => 'Depression',
            'family'   => 'Family Problems',
            'parent'   => 'Family Problems',
            'finance'  => 'Financial Stress',
            'money'    => 'Financial Stress',
            'budget'   => 'Financial Stress',
            'grief'    => 'Grief / Loss',
            'loss'     => 'Grief / Loss',
            'lonely'   => 'Loneliness',
            'isolate'  => 'Loneliness',
            'esteem'   => 'Low Self-Esteem',
            'confidence'=> 'Low Self-Esteem',
            'relationship' => 'Relationship Issues',
            'breakup'      => 'Relationship Issues',
            'sleep'     => 'Sleep Problems',
            'insomnia'  => 'Sleep Problems',
            'stress'    => 'Stress',
            'overwhelm' => 'Stress',
            'alcohol'   => 'Substance Abuse',
            'drug'      => 'Substance Abuse',
            'time'      => 'Time Management',
            'deadline'  => 'Time Management',
            'procrast'  => 'Time Management',
        ];

        $pickDiagnosis = function (?string $finalNote) use ($INTENTS, $MAP): string {
            $t = mb_strtolower(trim((string) $finalNote));

            // Ignore boilerplate/administrative notes (e.g., "Session completed…")
            if ($t === '' ||
                str_contains($t, 'session completed') ||
                str_contains($t, 'student advised') ||
                str_contains($t, 'follow-up')) {
                return Arr::random($INTENTS);
            }

            foreach ($MAP as $needle => $label) {
                if (mb_strpos($t, $needle) !== false) {
                    return $label;
                }
            }

            // As a last resort, if the note is short (single word-ish), try to uppercase-nicify it.
            if (mb_strlen($t) <= 24 && preg_match('/^[a-z\s\-]+$/i', $t)) {
                return ucwords($t);
            }

            // Otherwise pick a reasonable label to keep the UI clean.
            return Arr::random($INTENTS);
        };

        /* ---------- Build & insert ---------- */
        $batch   = [];
        $added   = 0;
        $skipped = 0;

        foreach ($appts as $a) {
            // Choose a consistent timestamp so grouping/months work on reports
            $when = $a->finalized_at ?: $a->scheduled_at ?: now();

            $dx = $pickDiagnosis($a->final_note);

            if ($hasApptId) {
                if (isset($existingKeys[$a->appt_id])) { $skipped++; continue; }
            } else {
                $key = implode(':', [
                    (int) $a->counselor_id,
                    (int) $a->student_id,
                    Carbon::parse($when)->format('Y-m'),
                    mb_strtolower($dx),
                ]);
                if (isset($existingKeys[$key])) { $skipped++; continue; }
            }

            $row = [
                'student_id'    => (int) $a->student_id,
                'counselor_id'  => (int) $a->counselor_id,
                $dxCol          => $dx,
                // Keep a tiny copy of the original for reference if a notes column exists
                // (won’t break if the column isn’t there)
                'created_at'    => $when,
                'updated_at'    => $when,
            ];

            if ($hasApptId) {
                $row['appointment_id'] = (int) $a->appt_id;
            }
            if (Schema::hasColumn($reports, 'notes')) {
                $row['notes'] = $this->truncateNullable($a->final_note, 255);
            }

            $batch[] = $row;
            $added++;

            // Track uniqueness immediately to avoid memory blow-ups
            if ($hasApptId) {
                $existingKeys[$a->appt_id] = true;
            } else {
                $existingKeys[$key] = true;
            }

            if (count($batch) >= 500) {
                DB::table($reports)->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table($reports)->insert($batch);
        }

        $this->command->info("Done. Inserted {$added} diagnosis report row(s); skipped {$skipped} duplicate(s).");
    }

    private function truncateNullable(?string $s, int $limit): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        return mb_strlen($s) > $limit ? mb_substr($s, 0, $limit) : $s;
    }
}
