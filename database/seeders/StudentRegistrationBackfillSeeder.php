<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StudentRegistrationBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Backfilling tbl_registration from appointments (for Counselor Logs “Students handled”)…');

        // Table guards
        if (!Schema::hasTable('tbl_appointments')) {
            $this->command->warn('tbl_appointments not found. Abort.');
            return;
        }
        if (!Schema::hasTable('tbl_registration')) {
            $this->command->warn('tbl_registration not found. Abort.');
            return;
        }

        // Detect usable columns on tbl_registration
        $regHasFullName = Schema::hasColumn('tbl_registration', 'full_name');
        $regHasEmail    = Schema::hasColumn('tbl_registration', 'email');
        $regHasCourseId = Schema::hasColumn('tbl_registration', 'course_id'); // optional
        $regHasGender   = Schema::hasColumn('tbl_registration', 'gender');    // optional

        // Prefer names from tbl_users if available, else we’ll fabricate something readable.
        $hasUsers   = Schema::hasTable('tbl_users');
        $usersCols  = $hasUsers ? DB::getSchemaBuilder()->getColumnListing('tbl_users') : [];
        $usersHasName = in_array('name', $usersCols, true);
        $usersHasEmail= in_array('email', $usersCols, true);

        // Student IDs referenced by appointments
        $studentIds = DB::table('tbl_appointments')
            ->whereNotNull('student_id')
            ->distinct()
            ->pluck('student_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->values();

        if ($studentIds->isEmpty()) {
            $this->command->warn('No students in appointments. Nothing to backfill.');
            return;
        }

        // Which of those are missing in tbl_registration?
        $already = DB::table('tbl_registration')
            ->whereIn('id', $studentIds)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $missingIds = $studentIds->diff($already)->values();
        if ($missingIds->isEmpty()) {
            $this->command->info('All appointment students already exist in tbl_registration. Nothing to insert.');
            return;
        }

        // Pull names/emails from users if possible
        $users = collect();
        if ($hasUsers && $usersHasName) {
            $users = DB::table('tbl_users')
                ->whereIn('id', $missingIds)
                ->select([
                    'id',
                    $usersHasName ? 'name' : DB::raw("NULL as name"),
                    $usersHasEmail ? 'email' : DB::raw("NULL as email"),
                ])
                ->get()
                ->keyBy('id');
        }

        $now   = Carbon::now();
        $batch = [];
        foreach ($missingIds as $sid) {
            $u = $users->get($sid);

            $fullName = null;
            if ($u && !empty($u->name)) {
                $fullName = trim($u->name);
            }

            if (!$fullName) {
                // Fallback: “Student #1234”
                $fullName = 'Student #'.$sid;
            }

            $row = [
                'id'         => (int) $sid,       // important: repo joins a.student_id = s.id
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($regHasFullName) $row['full_name'] = $fullName;
            if ($regHasEmail)    $row['email']     = $u->email ?? null;
            if ($regHasCourseId) $row['course_id'] = null; // leave null (or set a default if you want)
            if ($regHasGender)   $row['gender']    = null;

            $batch[] = $row;

            if (count($batch) >= 1000) {
                DB::table('tbl_registration')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('tbl_registration')->insertOrIgnore($batch);
        }

        $this->command->info('Done. Inserted '.count($missingIds).' missing student(s) into tbl_registration.');
        $this->command->info('Now the Counselor Logs query can build “Students handled”.');
    }
}
