<?php

namespace App\Services;

use App\Models\CounselorAvailability;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentSlotService
{
    /**
     * Compute free slots for a counselor on a date by intersecting availability with existing appointments.
     *
     * @param  int    $counselorId
     * @param  string $dateYmd  'YYYY-MM-DD'
     * @param  int    $slotMinutes size of each slot (e.g., 30)
     * @return array  [ "date" => "YYYY-MM-DD", "slots" => [ ["start"=>"HH:MM","end"=>"HH:MM","free"=>true], ... ] ]
     */
    public function slotsForDate(int $counselorId, string $dateYmd, int $slotMinutes = 30): array
    {
        $date = Carbon::parse($dateYmd)->startOfDay();

        // 1) Pull availability windows (is_available=true) minus blackouts (is_available=false)
        /** @var Collection<int, CounselorAvailability> $windows */
        $windows = CounselorAvailability::query()
            ->where('counselor_id', $counselorId)
            ->whereDate('avail_date', $date)
            ->orderBy('start_time')
            ->get();

        // Build base timelines: available ranges only
        $availableRanges = [];
        foreach ($windows as $w) {
            if ($w->is_available) {
                $availableRanges[] = [$w->start_time, $w->end_time];
            }
        }
        // Apply explicit blackouts by subtracting
        foreach ($windows as $w) {
            if (!$w->is_available) {
                $availableRanges = $this->subtractRange($availableRanges, [$w->start_time, $w->end_time]);
            }
        }

        // 2) Pull existing approved/active appointments to block slots
        // Adjust table/status names to your schema
        $appointments = DB::table('tbl_appointments')
            ->where('counselor_id', $counselorId)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['pending','approved','confirmed']) // anything that holds time
            ->selectRaw('TIME(scheduled_at) as start_t, TIME(DATE_ADD(scheduled_at, INTERVAL duration_minute MINUTE)) as end_t')
            ->get();

        $busyRanges = [];
        foreach ($appointments as $a) {
            $busyRanges[] = [$a->start_t, $a->end_t];
        }

        // 3) Generate granular slots from availableRanges then mark as free/busy
        $slots = [];
        foreach ($availableRanges as [$st, $et]) {
            $cursor = Carbon::parse("{$dateYmd} {$st}");
            $end    = Carbon::parse("{$dateYmd} {$et}");
            while ($cursor->lt($end)) {
                $slotStart = $cursor->copy();
                $slotEnd   = $cursor->copy()->addMinutes($slotMinutes);
                if ($slotEnd->gt($end)) break;

                $isBusy = $this->intersectsBusy($slotStart, $slotEnd, $busyRanges, $dateYmd);
                $slots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end'   => $slotEnd->format('H:i'),
                    'free'  => !$isBusy,
                ];
                $cursor->addMinutes($slotMinutes);
            }
        }

        return ['date' => $date->toDateString(), 'slots' => $slots];
    }

    private function intersectsBusy(Carbon $slotStart, Carbon $slotEnd, array $busyRanges, string $dateYmd): bool
    {
        foreach ($busyRanges as [$bst, $bet]) {
            $bStart = Carbon::parse("{$dateYmd} {$bst}");
            $bEnd   = Carbon::parse("{$dateYmd} {$bet}");
            // overlap if start < busyEnd && end > busyStart
            if ($slotStart->lt($bEnd) && $slotEnd->gt($bStart)) return true;
        }
        return false;
    }

    /**
     * Subtract blackout range from availability ranges.
     * @param array $ranges [ [st,et], ... ] times (HH:MM:SS)
     * @param array $black  [st,et]
     * @return array
     */
    private function subtractRange(array $ranges, array $black): array
    {
        [$bs, $be] = $black;
        $result = [];
        foreach ($ranges as [$as, $ae]) {
            if ($be <= $as || $bs >= $ae) {
                // no overlap
                $result[] = [$as, $ae];
                continue;
            }
            // overlap cases
            if ($bs > $as) $result[] = [$as, $bs];
            if ($be < $ae) $result[] = [$be, $ae];
        }
        return $result;
    }
}
