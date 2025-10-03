<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseAnalyticsRepository implements CourseAnalyticsRepositoryInterface
{
    private const USERS   = 'tbl_users';
    private const REPORTS = 'tbl_diagnosis_reports';
    private const ANALYT  = 'tbl_course_analytics';

    public function listCourses(string $yearKey = 'all', string $q = ''): Collection
    {
        $rows = DB::table(self::ANALYT)
            ->select('id', 'course', 'year_level', 'total_students', 'common_diagnosis', 'updated_at')
            ->when($this->isYearKey($yearKey), function ($qb) use ($yearKey) {
                $needle = match ($yearKey) {
                    '1' => '1st', '2' => '2nd', '3' => '3rd', '4' => '4th',
                    default => ''
                };
                $qb->whereRaw('LOWER(year_level) LIKE ?', ['%' . strtolower($needle) . '%']);
            })
            ->when($q !== '', function ($qb) use ($q) {
                $like = '%' . $q . '%';
                $qb->where(function ($sub) use ($like) {
                    $sub->where('course', 'like', $like)
                        ->orWhere('year_level', 'like', $like)
                        ->orWhere('common_diagnosis', 'like', $like);
                });
            })
            ->orderBy('course')
            ->orderBy('year_level')
            ->get();

        // Normalize for the blade: map JSON/CSV -> array, rename fields for display
        return $rows->map(function ($r) {
            return (object) [
                'id'               => $r->id,
                'course'           => $r->course,
                'year_level'       => $r->year_level,
                'student_count'    => (int) $r->total_students,
                'common_diagnoses' => $this->decodeCommon($r->common_diagnosis),
            ];
        });
    }

    public function findCourseWithBreakdown(int $id, int $limit = 20): ?object
    {
        $row = DB::table(self::ANALYT)
            ->select('id', 'course', 'year_level', 'total_students')
            ->where('id', $id)
            ->first();

        if (!$row) {
            return null;
        }

        // Build breakdown from diagnosis reports joined to users of the same course/year
        $breakdown = DB::table(self::REPORTS . ' as dr')
            ->join(self::USERS . ' as u', 'u.id', '=', 'dr.student_id')
            ->where('u.course', $row->course)
            ->where('u.year_level', $row->year_level)
            ->selectRaw('dr.diagnosis_result as label, COUNT(*) as cnt')
            ->groupBy('dr.diagnosis_result')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get()
            ->map(fn ($x) => ['label' => (string) $x->label, 'count' => (int) $x->cnt])
            ->all();

        return (object) [
            'course'        => $row->course,
            'year_level'    => $row->year_level,
            'student_count' => (int) $row->total_students,
            'breakdown'     => $breakdown,
            'notes'         => null,
        ];
    }

    public function refreshForStudent(int $studentId): void
    {
        $student = DB::table(self::USERS)
            ->select('course', 'year_level')
            ->where('id', $studentId)
            ->first();

        if (!$student || empty($student->course) || empty($student->year_level)) {
            return;
        }

        $course    = (string) $student->course;
        $yearLevel = (string) $student->year_level;

        // live count of students in this course/year
        $totalStudents = (int) DB::table(self::USERS)
            ->where('course', $course)
            ->where('year_level', $yearLevel)
            ->when(DB::getSchemaBuilder()->hasColumn(self::USERS, 'role'), fn ($q) => $q->where('role', 'student'))
            ->count();

        // top diagnosis labels (first 8) for this course/year
        $topDiag = DB::table(self::REPORTS . ' as dr')
            ->join(self::USERS . ' as u', 'u.id', '=', 'dr.student_id')
            ->where('u.course', $course)
            ->where('u.year_level', $yearLevel)
            ->selectRaw('dr.diagnosis_result as label, COUNT(*) as c')
            ->groupBy('dr.diagnosis_result')
            ->orderByDesc('c')
            ->limit(8)
            ->pluck('label')
            ->map(fn ($v) => (string) $v)
            ->all();

        $jsonList = json_encode(array_values($topDiag), JSON_UNESCAPED_UNICODE);

        DB::table(self::ANALYT)->updateOrInsert(
            ['course' => $course, 'year_level' => $yearLevel],
            [
                'total_students'   => $totalStudents,
                'common_diagnosis' => $jsonList,
                'generated_at'     => now(),
                'updated_at'       => now(),
                'created_at'       => now(), // insert only
            ]
        );
    }

    /* ================== helpers ================== */

    private function isYearKey(string $k): bool
    {
        return in_array($k, ['1', '2', '3', '4'], true);
    }

    private function decodeCommon(?string $raw): array
    {
        if ($raw === null || $raw === '') return [];
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }
        // fallback: CSV
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
