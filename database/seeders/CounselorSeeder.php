<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CounselorSeeder extends Seeder
{
    /**
     * Creates counselor accounts (user + counselor profile).
     * Availability will be created later via the UI, not here.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $counselors = [
            [
                'full_name' => 'Jason Ang',
                'email'     => 'jason.ang@tcc.edu.ph',
                'password'  => 'Jason123', // CHANGE in production
                'phone'     => '09991234567',
            ],
            [
                'full_name' => 'Sally',
                'email'     => 'sally@tcc.edu.ph',
                'password'  => 'Sally123', // CHANGE in production
                'phone'     => '09987654321',
            ],

            [
                'full_name' => 'Juan Dela Cruz',
                'email'     => 'dummycounselor35@gmail.com',
                'password'  => '@Password12345', // CHANGE in production
                'phone'     => '09987654525',
            ],
        ];

        foreach ($counselors as $c) {
            // 1) Ensure counselor exists in tbl_counselors
            $counselorId = DB::table('tbl_counselors')->where('email', $c['email'])->value('id');
            if (!$counselorId) {
                $payload = [
                    'name'       => $c['full_name'],
                    'email'      => $c['email'],
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (Schema::hasColumn('tbl_counselors', 'phone')) {
                    $payload['phone'] = $c['phone'];
                }
                $counselorId = DB::table('tbl_counselors')->insertGetId($payload);
            }

            // 2) Ensure user exists in tbl_users (role=counselor)
            $userId = DB::table('tbl_users')->where('email', $c['email'])->value('id');
            if (!$userId) {
                DB::table('tbl_users')->insert([
                    'name'       => explode(' ', $c['full_name'])[0],
                    'email'      => $c['email'],
                    'password'   => Hash::make($c['password']),
                    'role'       => 'counselor',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                // Ensure existing user is tagged as counselor
                $role = DB::table('tbl_users')->where('id', $userId)->value('role');
                if ($role !== 'counselor') {
                    DB::table('tbl_users')->where('id', $userId)->update([
                        'role'       => 'counselor',
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->command?->info("Counselor seeded: {$c['full_name']} <{$c['email']}>");
        }
    }
}
