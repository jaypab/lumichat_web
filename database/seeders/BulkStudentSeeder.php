<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class BulkStudentSeeder extends Seeder
{
    public function run(): void
    {
        $faker   = Faker::create('en_PH');
        $courses = ['BSIT','EDUC','CAS','CRIM','BLIS','MIDWIFERY','BSHM','BSBA'];
        $years   = ['1st year','2nd year','3rd year','4th year'];
        $now     = now();

        // progress (nice to see it work)
        $this->command->info('Writing to tbl_users (role=student)…');
        $this->command->getOutput()->progressStart(500);

        $batch = [];
        for ($i = 1; $i <= 500; $i++) {
            $full  = $faker->firstName().' '.$faker->lastName();
            $email = Str::lower(Str::slug($full, '.')).$i.'@example.edu.ph';

            $batch[] = [
                'name'               => $full,                                          // <-- tbl_users.name
                'email'              => $email,
                'contact_number'     => '09'.$faker->numberBetween(100000000, 999999999),
                'course'             => $courses[array_rand($courses)],
                'year_level'         => $years[array_rand($years)],
                'password'           => Hash::make('Password@123'),
                'role'               => 'student',                                      // <-- important
                'appointments_enabled'=> 1,                                             // set as you prefer
                'email_verified_at'  => $now,                                           // mark verified (optional)
                'remember_token'     => Str::random(60),
                'created_at'         => $now,
                'updated_at'         => $now,
            ];

            if (count($batch) === 200) {
                DB::table('tbl_users')->insert($batch);  // <-- write to tbl_users
                $batch = [];
            }
            $this->command->getOutput()->progressAdvance();
        }

        if ($batch) {
            DB::table('tbl_users')->insert($batch);
        }

        $this->command->getOutput()->progressFinish();
        $this->command->info('Done. Students in tbl_users: '.DB::table('tbl_users')->where('role','student')->count());
    }
}
