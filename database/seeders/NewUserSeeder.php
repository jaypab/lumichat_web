<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TblUsersSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            [
                'email' => 'lorenzmanillasaldivar@gmail.com',
                'sis'   => '2025009',
                'name'  => 'Lorenz Saldivar',
                'course' => 'BSIT',
                'year_level' => '4th Year',
                'contact_number' => '09676142138',
                'email_verified_at' => $now,             // set to null if you prefer unverified
                'password' => Hash::make('12345678'),

                'role' => 'student',
                'appointments_enabled' => 0,             // disabled

                'remember_token' => Str::random(40),

                // TOS NOT ACCEPTED
                'tos_version' => 0,
                'tos_accepted_at' => null,
                'tos_ip' => null,
                'tos_user_agent' => null,

                'last_seen_appt_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
             [
                'email' => 'ondoxsaldivar@gmail.com',
                'sis'   => '2025010',
                'name'  => 'Justine Nabunturan',
                'course' => 'EDUC',
                'year_level' => '2nd Year',
                'contact_number' => '09676142138',
                'email_verified_at' => $now,             // set to null if you prefer unverified
                'password' => Hash::make('12345678'),

                'role' => 'student',
                'appointments_enabled' => 0,             // disabled

                'remember_token' => Str::random(40),

                // TOS NOT ACCEPTED
                'tos_version' => 0,
                'tos_accepted_at' => null,
                'tos_ip' => null,
                'tos_user_agent' => null,

                'last_seen_appt_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('tbl_users')->updateOrInsert(
                ['email' => $row['email']], // unique key
                $row
            );
        }
    }
}
