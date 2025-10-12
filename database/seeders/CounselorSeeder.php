<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CounselorSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // --- counselors to create (add or tweak freely) ---
        $rows = [
            [
                'name'      => 'Sir Jason Ang',
                'email'     => 'jasonang@school.edu',
                'phone'     => '09569279299',
                'is_active' => 1,
            ],
            [
                'name'      => 'Ma’am Kristine Dela Cruz',
                'email'     => 'kristine.delacruz@school.edu',
                'phone'     => '09171234567',
                'is_active' => 1,
            ],
            [
                'name'      => 'Sir Miguel Bautista',
                'email'     => 'miguel.bautista@school.edu',
                'phone'     => '09998887777',
                'is_active' => 1,
            ],
            [
                'name'      => 'Ma’am Arlene Santos',
                'email'     => 'arlene.santos@school.edu',
                'phone'     => '09081231234',
                'is_active' => 1,
            ],
        ];

        // Insert counselors (skip if email already exists so it’s re-runnable)
        $ids = [];
        foreach ($rows as $r) {
            $exists = DB::table('tbl_counselors')->where('email', $r['email'])->value('id');
            if ($exists) {
                $ids[] = (int) $exists;
                continue;
            }

            $id = DB::table('tbl_counselors')->insertGetId([
                'name'       => $r['name'],
                'email'      => $r['email'],
                'phone'      => $r['phone'],
                'is_active'  => (int) $r['is_active'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $ids[] = $id;
        }

        // Build Mon–Fri 09:00–17:00 availability for each counselor
        // tbl_counselor_availabilities: counselor_id | weekday (1=Mon..5=Fri) | start_time | end_time | timestamps
        $availBatch = [];
        foreach ($ids as $cid) {
            for ($weekday = 1; $weekday <= 5; $weekday++) {
                // Check if an identical slot already exists (so the seeder is idempotent)
                $exists = DB::table('tbl_counselor_availabilities')
                    ->where('counselor_id', $cid)
                    ->where('weekday', $weekday)
                    ->where('start_time', '09:00:00')
                    ->where('end_time', '17:00:00')
                    ->exists();

                if (!$exists) {
                    $availBatch[] = [
                        'counselor_id' => $cid,
                        'weekday'      => $weekday,      // 1..5  (Mon..Fri)
                        'start_time'   => '09:00:00',
                        'end_time'     => '17:00:00',
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }
        }

        if ($availBatch) {
            DB::table('tbl_counselor_availabilities')->insert($availBatch);
        }

        $this->command->info('Counselors: '.DB::table('tbl_counselors')->count());
        $this->command->info('Availabilities: '.DB::table('tbl_counselor_availabilities')->count());
    }
}
