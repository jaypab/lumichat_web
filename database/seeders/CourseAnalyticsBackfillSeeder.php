<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourseAnalyticsBackfillSeeder extends Seeder
{
    /**
     * Backfills tbl_course_analytics by deriving:
     *  - total_students per (course, year_level)
     *  - top 3 diagnosis results among those students (from tbl_diagnosis_reports)
     *
     * It *only* uses existing data; nothing is randomized.
     */
    public function run(): void
    {
        $this->command->info('Backfilling Course Analytics from existing students and diagnosis reports…');

        /* ----------------------- detect tables/columns ----------------------- */

        // Target table to populate
        $target = 'tbl_course_analytics';
        if (!Schema::hasTable($target)) {
            $this->command->warn("❗ {$target} table not found. Aborting.");
            return;
        }

        // Students live in tbl_users, but course/year usually live in tbl_registration
        $usersTbl   = Schema::hasTable('tbl_users') ? 'tbl_users' : null;
        $regTbl     = Schema::hasTable('tbl_registration') ? 'tbl_registration' : null;
        $reportsTbl = Schema::hasTable('tbl_diagnosis_reports') ? 'tbl_diagnosis_reports' : null;

        if (!$usersTbl)   { $this->command->warn('❗ tbl_users not found. Aborting.'); return; }
        if (!$reportsTbl) { $this->command->warn('❗ tbl_diagnosis_reports not found. Aborting.'); return; }

        // Which diagnosis column is present?
        $dxCol = Schema::hasColumn($reportsTbl, 'diagnosis_result')
            ? 'diagnosis_result'
            : (Schema::hasColumn($reportsTbl, 'diagnosis') ? 'diagnosis' : null);

        if (!$dxCol) {
            $this->command->warn("❗ {$reportsTbl} has no diagnosis column. Aborting.");
            return;
        }

        // How do we reach course/year_level?
        // Preferred: tbl_registration(user_id → users.id). Fallback: course/year_level on tbl_users (if they exist).
        $hasRegUserId   = $regTbl && Schema::hasColumn($regTbl, 'user_id');
        $regHasCourse   = $regTbl && Schema::hasColumn($regTbl, 'course');
        $regHasYear     = $regTbl && (Schema::hasColumn($regTbl, 'year_level') || Schema::hasColumn($regTbl, 'year'));
        $usersHasCourse = Schema::hasColumn($usersTbl, 'course');
        $usersHasYear   = Schema::hasColumn($usersTbl, 'year_level') || Schema::hasColumn($usersTbl, 'year');

        if (!($hasRegUserId && $regHasCourse && $regHasYear) && !($usersHasCourse && $usersHasYear)) {
            $this->command->warn('❗ Could not find (course, year_level) on tbl_registration or tbl_users. Aborting.');
            return;
        }

        // Normalize column names for year_level
        $regYearCol   = Schema::hasColumn($regTbl ?? '', 'year_level') ? 'year_level' : 'year';
        $usersYearCol = Schema::hasColumn($usersTbl, 'year_level') ? 'year_level' : 'year';

        /* ----------------------- build student → (course,year) map ----------------------- */

        // Only student users (role = student)
        $studentIds = DB::table($usersTbl)
            ->where('role', 'student')
            ->pluck('id')
            ->all();

        if (empty($studentIds)) {
            $this->command->warn('No student users found.');
            return;
        }

        // Pull course/year per student. Prefer tbl_registration if available and linked by user_id.
        if ($hasRegUserId && $regHasCourse && $regHasYear) {
            $stuMeta = DB::table($regTbl)
                ->whereIn('user_id', $studentIds)
                ->select(['user_id as student_id', 'course', $regYearCol.' as year_level'])
                ->get();
            $getCourse = fn($sid) => optional($stuMeta->firstWhere('student_id', $sid))->course;
            $getYear   = fn($sid) => optional($stuMeta->firstWhere('student_id', $sid))->year_level;
        } else {
            // Fallback: read from tbl_users directly
            $stuMeta = DB::table($usersTbl)
                ->whereIn('id', $studentIds)
                ->select(['id as student_id', 'course', $usersYearCol.' as year_level'])
                ->get();
            $getCourse = fn($sid) => optional($stuMeta->firstWhere('student_id', $sid))->course;
            $getYear   = fn($sid) => optional($stuMeta->firstWhere('student_id', $sid))->year_level;
        }

        /* ----------------------- group students by (course,year) ----------------------- */

        $groups = []; // "course|||year" => ['course'=>..,'year_level'=>..,'student_ids'=>[]]

        foreach ($studentIds as $sid) {
            $course = (string) ($getCourse($sid) ?? '');
            $year   = (string) ($getYear($sid) ?? '');

            // Normalize blanks
            $courseNorm = trim($course) === '' ? '—' : trim($course);
            $yearNorm   = trim((string)$year) === '' ? '—' : (string)$year;

            $key = $courseNorm.'|||'.$yearNorm;

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'course'      => $courseNorm,
                    'year_level'  => $yearNorm,
                    'student_ids' => [],
                ];
            }
            $groups[$key]['student_ids'][] = (int) $sid;
        }

        if (empty($groups)) {
            $this->command->warn('No (course, year) groupings could be derived.');
            return;
        }

        /* ----------------------- compute top diagnoses per group ----------------------- */

        // We’ll scan diagnosis_reports once, bucket by student_id,
        // then reduce per (course,year) group.
        $allDxRows = DB::table($reportsTbl)
            ->whereIn('student_id', $studentIds)
            ->whereNotNull($dxCol)
            ->select(['student_id', $dxCol.' as dx'])
            ->get();

        $dxByStudent = [];
        foreach ($allDxRows as $r) {
            $sid = (int) $r->student_id;
            $dx  = trim((string) $r->dx);
            if ($dx === '') continue;
            $dxByStudent[$sid][] = $dx;
        }

        // Prepare rows for insertion
        $now   = Carbon::now();
        $batch = [];

        // (Re)start clean: you can switch this to a smarter "upsert" if you prefer
        DB::table($target)->truncate();

        foreach ($groups as $g) {
            $stuIds        = array_unique($g['student_ids']);
            $totalStudents = count($stuIds);

            // Accumulate diagnosis counts across all students in this course/year
            $counter = [];
            foreach ($stuIds as $sid) {
                if (empty($dxByStudent[$sid])) continue;
                foreach ($dxByStudent[$sid] as $dx) {
                    $counter[$dx] = ($counter[$dx] ?? 0) + 1;
                }
            }

            // Top 3 diagnoses (ties won’t matter much for display)
            arsort($counter);
            $top = array_slice(array_keys($counter), 0, 3);

            $common = empty($top) ? '—' : implode(', ', $top);

            $batch[] = [
                'course'           => $g['course'],
                'year_level'       => is_numeric($g['year_level']) ? (int) $g['year_level'] : (string) $g['year_level'],
                'total_students'   => $totalStudents,
                'common_diagnosis' => $common,
                'generated_at'     => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            if (count($batch) >= 500) {
                DB::table($target)->insert($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table($target)->insert($batch);
        }

        $this->command->info('Course Analytics backfill complete.');
    }
}
