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
     * Creates exactly one counselor account (user + counselor profile).
     * Availability will be created later via the UI, not here.
     */
    public function run(): void
    {
        $now        = Carbon::now();

        // Customize these if needed
        $fullName   = 'Jayson Ang';
        $email      = 'jayson.ang@tcc.edu.ph';
        $password   = 'Jayson123';   // CHANGE in production

        // 1) Ensure counselor exists in tbl_counselors
        $counselorId = DB::table('tbl_counselors')->where('email', $email)->value('id');
        if (!$counselorId) {
            $payload = [
                'name'       => $fullName,
                'email'      => $email,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // Insert phone only if the column exists
            if (Schema::hasColumn('tbl_counselors', 'phone')) {
                $payload['phone'] = '09991234567';
            }
            $counselorId = DB::table('tbl_counselors')->insertGetId($payload);
        }

        // 2) Ensure login user exists in tbl_users (role=counselor)
        $userId = DB::table('tbl_users')->where('email', $email)->value('id');
        if (!$userId) {
            DB::table('tbl_users')->insert([
                'name'       => 'Jayson',
                'email'      => $email,
                'password'   => Hash::make($password),
                'role'       => 'counselor',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            // Guarantee role=counselor if user already exists
            $role = DB::table('tbl_users')->where('id', $userId)->value('role');
            if ($role !== 'counselor') {
                DB::table('tbl_users')->where('id', $userId)->update([
                    'role'       => 'counselor',
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info("Counselor seeded: {$fullName} <{$email}>");
    }
}
