<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class DiagnosisReportSeeder extends Seeder
{
    /**
     * How many months back to seed, including current month.
     * e.g., 3 = current, -1, -2 (3 months total)
     */
    private int $monthsSpan = 4; // seed 4 months: now .. now-3

    /** Total target reports across all months (roughly distributed) */
    private int $totalReports = 500; // tweak as you like

    /** Weighted list of diagnosis labels (aligning to your intents) */
    private array $dxWeighted = [
        // label                  weight
        ['Stress',               16],
        ['Anxiety',              14],
        ['Depression',           12],
        ['Financial Stress',      8],
        ['Family Problems',       7],
        ['Relationship Issues',   7],
        ['Time Management',       6],
        ['Sleep Problems',        5],
        ['Burnout',               5],
        ['Low Self-Esteem',       5],
        ['Bullying',              4],
        ['Loneliness',            4],
        ['Grief / Loss',          3],
        ['Substance Abuse',       2],
        ['Academic Pressure',     2],
    ];

    public function run(): void
    {
        $students   = DB::table('tbl_users')->where('role', 'student')->pluck('id')->all();
        $counselors = DB::table('tbl_counselors')->pluck('id')->all();

        if (empty($students) || empty($counselors)) {
            $this->command->warn('DiagnosisReportSeeder: No students or counselors found. Seed those first.');
            return;
        }

        // Build a pickable bag from weights
        $bag = [];
        foreach ($this->dxWeighted as [$label, $w]) {
            for ($i = 0; $i < $w; $i++) $bag[] = $label;
        }

        $now = Carbon::now();
        $months = [];
        for ($i = 0; $i < $this->monthsSpan; $i++) {
            $months[] = (clone $now)->subMonths($i)->startOfMonth();
        }

        // Distribute total reports across months and counselors
        $perMonth = max(1, intdiv($this->totalReports, count($months)));
        $batch = [];
        $inserted = 0;

        $this->command->info("Seeding Diagnosis Reports for ".count($months)." month(s)…");
        $this->command->getOutput()->progressStart($this->totalReports);

        foreach ($months as $mStart) {
            $mEnd = (clone $mStart)->endOfMonth();

            // more or less even, with a bit of variation
            $thisMonthTarget = max(1, (int) round($perMonth * (0.85 + mt_rand(0, 30)/100))); 

            for ($k = 0; $k < $thisMonthTarget; $k++) {
                $studentId   = Arr::random($students);
                $counselorId = Arr::random($counselors);
                $diagnosis   = Arr::random($bag);

                // Random timestamp within that month (9:00–17:30 window)
                $day   = mt_rand(0, $mEnd->day - 1);
                $start = (clone $mStart)->addDays($day)->setTime(9, 0);
                $slot  = mt_rand(0, 17); // 9:00 -> 17:30 in 30-min steps (17 steps)
                $at    = (clone $start)->addMinutes($slot * 30);

                $batch[] = [
                    'student_id'       => $studentId,
                    'counselor_id'     => $counselorId,
                    'diagnosis_result' => $diagnosis,
                    'notes'            => null,
                    'created_at'       => $at,
                    'updated_at'       => $at,
                ];
                $inserted++;

                if (count($batch) >= 500) {
                    DB::table('tbl_diagnosis_reports')->insert($batch);
                    $batch = [];
                }

                // stop exactly at intended total
                $this->command->getOutput()->progressAdvance();
                if ($inserted >= $this->totalReports) break;
            }

            if ($inserted >= $this->totalReports) break;
        }

        if ($batch) {
            DB::table('tbl_diagnosis_reports')->insert($batch);
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info("Done. Inserted {$inserted} diagnosis report(s).");
    }
}
