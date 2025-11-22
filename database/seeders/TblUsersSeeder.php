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
                'sis'   => '2025001',
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
                'email' => 'faithmagayon@gmail.com',
                'sis'   => '2025004',
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
            ],
            [
                'email' => 'gideon@gmail.com',
                'sis'   => '2025005',
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
            ],
            [
                'email' => 'godwin@gmail.com',
                'sis'   => '2025006',
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
            [
                'email' => 'labininaycloyd5@gmail.com',
                'sis'   => '2025002',
                'name'  => 'Cloyd Labininay',
                'course' => 'BSIT',
                'year_level' => '4th Year',
                'contact_number' => '09554330963',
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
                'email' => 'labininaycloyd777@gmail.com',
                'sis'   => '2025007',
                'name'  => 'Angelo Labininay',
                'course' => 'BSIT',
                'year_level' => '1st Year',
                'contact_number' => '09557181684',
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
                'email' => 'lowelljaypabua@gmail.com',
                'sis'   => '2025003',
                'name'  => 'Jay Pabua',
                'course' => 'HM',
                'year_level' => '2nd Year',
                'contact_number' => '09123456788',
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
                'email' => ' jaypabua3@gmail.com',
                'sis'   => '2025008',
                'name'  => 'Cloyd Labucana',
                'course' => 'HM',
                'year_level' => '1st Year',
                'contact_number' => '09123456789',
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
