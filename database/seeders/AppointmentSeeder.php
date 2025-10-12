<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class AppointmentSeeder extends Seeder
{
    public function run(): void
    {
        $students   = DB::table('tbl_users')->where('role', 'student')->pluck('id')->all();
        $counselors = DB::table('tbl_counselors')->pluck('id')->all();

        if (empty($students)) {
            $this->command->warn('No students found. Seed users first.');
            return;
        }

        $TOTAL = 800;
        $now   = Carbon::now();

        // reduce DB hits for uniqueness: counselor_id|Y-m-d H:i:s
        $takenMap = [];

        $this->command->info("Seeding {$TOTAL} appointments into tbl_appointments…");
        $this->command->getOutput()->progressStart($TOTAL);

        $batch = [];

        for ($i = 0; $i < $TOTAL; $i++) {

            // Weighted initial choice (will be normalized by time later)
            $status = $this->weightedPick([
                'pending'   => 25,
                'confirmed' => 45,
                'completed' => 15,
                'canceled'  => 15,
            ]);

            $studentId = Arr::random($students);
            $slot      = $this->randomSlot($now);

            // counselor selection depends on status (will adjust after time normalization)
            $counselorId = null;
            if (in_array($status, ['confirmed', 'completed'], true) && !empty($counselors)) {
                $counselorId = $this->pickFreeCounselor($slot, $counselors, $takenMap);
                if ($counselorId === null) {
                    // fall back to pending if we cannot secure a unique blocking slot
                    $status = 'pending';
                }
            }

            // ---- TIME NORMALIZATION RULES ----
            if ($slot->lt($now)) {
                // Past slots => completed unless explicitly canceled
                if ($status !== 'canceled') {
                    $status = 'completed';
                    if ($counselorId === null && !empty($counselors)) {
                        // ensure counselor for completed
                        $counselorId = $this->pickFreeCounselor($slot, $counselors, $takenMap);
                        if ($counselorId === null) {
                            // if we still cannot, mark as canceled (non-blocking) to avoid unique errors
                            $status = 'canceled';
                        }
                    }
                } else {
                    // canceled remains canceled; no counselor
                    $counselorId = null;
                }
            } else {
                // Future slots
                if ($status === 'completed') {
                    // completed cannot be in the future; downgrade to confirmed
                    $status = 'confirmed';
                    if ($counselorId === null && !empty($counselors)) {
                        $counselorId = $this->pickFreeCounselor($slot, $counselors, $takenMap);
                        if ($counselorId === null) {
                            // as a last resort set to pending if uniqueness prevents assignment
                            $status = 'pending';
                        }
                    }
                }

                if ($status === 'pending' || $status === 'canceled') {
                    // pending/canceled must not have counselor
                    $counselorId = null;
                }
            }
            // ----------------------------------

            // “Booked on” (created_at) — ensure it’s before the slot and not in the far future
            $bookedOn = (clone $slot)
                ->subDays(rand(1, 15))
                ->setTime(rand(8, 17), [0, 15, 30, 45][rand(0, 3)], 0);
            if ($bookedOn->gt($now)) {
                $bookedOn = (clone $now)->subMinutes(rand(5, 240));
            }

            $row = [
                'parent_id'          => null,
                'student_id'         => $studentId,
                'chatbot_session_id' => null,
                'counselor_id'       => $counselorId,
                'scheduled_at'       => $slot,
                'status'             => $status,
                'note'               => $this->noteFor($status),
                'final_note'         => null,
                'finalized_by'       => null,
                'finalized_at'       => null,
                'created_at'         => $bookedOn,
                'updated_at'         => $bookedOn,
                // blocking_at is STORED GENERATED – don’t set it
            ];

            if ($status === 'completed' && $counselorId) {
                $row['final_note']   = 'Session completed. Student advised with follow-up tasks.';
                $row['finalized_by'] = $counselorId;
                $row['finalized_at'] = (clone $slot)->addHour();
            }

            $batch[] = $row;

            if (count($batch) === 200) {
                DB::table('tbl_appointments')->insert($batch);
                $batch = [];
            }

            $this->command->getOutput()->progressAdvance();
        }

        if (!empty($batch)) {
            DB::table('tbl_appointments')->insert($batch);
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info('Done. tbl_appointments count: ' . DB::table('tbl_appointments')->count());
    }

    /**
     * Pick a random weekday slot in 30-minute steps between 08:00–17:30,
     * within ~ -20 .. +35 days from now.
     */
    private function randomSlot(Carbon $now): Carbon
    {
        $date = (clone $now)->startOfDay()->addDays(rand(-20, 35));
        while (in_array($date->dayOfWeekIso, [6, 7], true)) {
            $date->addDay();
        }
        $slotIndex = rand(16, 35); // 16 => 08:00, 35 => 17:30
        return (clone $date)->addMinutes($slotIndex * 30);
    }

    /** Small helper to choose a counselor that is free at $slot (respecting unique (counselor_id, blocking_at)) */
    private function pickFreeCounselor(Carbon $slot, array $counselors, array &$takenMap): ?int
    {
        // try a few times with random counselors
        for ($tries = 0; $tries < 12; $tries++) {
            $cid = Arr::random($counselors);
            $key = $cid . '|' . $slot->format('Y-m-d H:i:s');

            if (isset($takenMap[$key])) {
                continue;
            }

            $exists = DB::table('tbl_appointments')
                ->where('counselor_id', $cid)
                ->where('blocking_at', $slot->format('Y-m-d H:i:s'))
                ->exists();

            if (!$exists) {
                $takenMap[$key] = true;
                return $cid;
            }
        }
        return null;
    }

    /** Short note by status */
    private function noteFor(string $status): string
    {
        return match ($status) {
            'pending'   => 'Auto-seeded: awaiting counselor confirmation.',
            'confirmed' => 'Auto-seeded: confirmed via system.',
            'canceled'  => 'Auto-seeded: booking canceled.',
            'completed' => 'Auto-seeded: session finished.',
            default     => 'Auto-seeded.',
        };
    }

    /** Weighted random choice */
    private function weightedPick(array $weights): string
    {
        $sum = array_sum($weights);
        if ($sum <= 0) return array_key_first($weights);

        $r = rand(1, $sum);
        foreach ($weights as $key => $w) {
            if ($r <= $w) return $key;
            $r -= $w;
        }
        return array_key_first($weights);
    }
}
