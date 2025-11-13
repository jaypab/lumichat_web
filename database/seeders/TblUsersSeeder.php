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
                'email' => 'earlsepida63@gmail.com',
                'name'  => 'Earl Sepida',
                'course' => 'BSIT',
                'year_level' => '4th Year',
                'contact_number' => '09569279299',
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
                'email' => 'jaypabua3@gmail.com',
                'name'  => 'Test Student 02',
                'course' => 'BSIT',
                'year_level' => '3',
                'contact_number' => '09170000002',
                'email_verified_at' => $now,
                'password' => Hash::make('12345678'),

                'role' => 'student',
                'appointments_enabled' => 0,

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
                'email' => 'faithmagayon@gmail.com',
                'name'  => 'Faith Magayon',
                'course' => 'MID',
                'year_level' => '4th Year',
                'contact_number' => '09569279299',
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
            ],[
                'email' => 'gideon@gmail.com',
                'name'  => 'Gideon Atabelo',
                'course' => 'HM',
                'year_level' => '4th Year',
                'contact_number' => '09569279299',
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
            ],[
                'email' => 'godwin@gmail.com',
                'name'  => 'Godwin Atabelo',
                'course' => 'BSIT',
                'year_level' => '4th Year',
                'contact_number' => '09569279299',
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
