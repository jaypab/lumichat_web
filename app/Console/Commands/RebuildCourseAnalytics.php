<?php

namespace App\Console\Commands;

use App\Models\CourseAnalytics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildCourseAnalytics extends Command
{
    protected $signature   = 'analytics:rebuild-courses {--dry-run}';
    protected $description = 'Rebuild tbl_course_analytics from tbl_case_notes + tbl_users (presenting problems)';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $this->info('Rebuilding course analytics from tbl_case_notes ...');

        // Group case notes by course + year_level
        $groups = DB::table('tbl_case_notes as cn')
            ->join('tbl_users as s', 's.id', '=', 'cn.student_id')
            ->selectRaw('
                s.course as course,
                s.year_level as year_level,
                COUNT(DISTINCT cn.student_id) as student_count
            ')
            ->whereNull('cn.deleted_at')
            ->whereNotNull('s.course')
            ->where('s.course', '<>', '')
            ->groupBy('s.course', 's.year_level')
            ->orderBy('s.course')
            ->orderBy('s.year_level')
            ->get();

        if ($groups->isEmpty()) {
            $this->warn('No case notes found to build analytics from.');
            return self::SUCCESS;
        }

        $now = now();

        if (!$dry) {
            // Wipe current analytics so we only keep fresh, case-note-based data
            DB::table('tbl_course_analytics')->truncate();
        }

        foreach ($groups as $group) {
            $course    = (string) $group->course;
            $yearLevel = (string) $group->year_level;
            $studCount = (int) $group->student_count;

            // --- Build breakdown from presenting_problem in case notes ---
            $dxRows = DB::table('tbl_case_notes as cn')
                ->join('tbl_users as s', 's.id', '=', 'cn.student_id')
                ->selectRaw('TRIM(cn.presenting_problem) as label, COUNT(*) as cnt')
                ->whereNull('cn.deleted_at')
                ->where('s.course', $course)
                ->where('s.year_level', $yearLevel)
                ->whereRaw('TRIM(cn.presenting_problem) <> ""')
                ->groupBy('label')
                ->orderByDesc('cnt')
                ->get();

            $breakdown = $dxRows->map(function ($row) {
                return [
                    'label' => (string) $row->label,
                    'count' => (int) $row->cnt,
                ];
            })->values()->all();

            // common_diagnoses column now stores common *presenting concerns*
            $common = $dxRows->pluck('label')->implode('||');

            if ($dry) {
                $this->line(sprintf(
                    '[DRY] %s / Year %s: %d students, %d concern types',
                    $course,
                    $yearLevel,
                    $studCount,
                    count($breakdown)
                ));
                continue;
            }

            CourseAnalytics::create([
                'course'           => $course,
                'year_level'       => $yearLevel,
                'student_count'    => $studCount,
                'common_diagnoses' => $common ?: null,   // still same column name
                'breakdown'        => $breakdown ?: null,
                'generated_at'     => $now,
                'created_by'       => null,
                'updated_by'       => null,
            ]);
        }

        $this->info('Course analytics rebuild completed (based on case notes only).');

        return self::SUCCESS;
    }
}
