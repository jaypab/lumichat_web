<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class CounselorLogRepository implements CounselorLogRepositoryInterface
{
    // ===================== Generic CRUD (mostly unused) =====================

    public function all(): Collection
    {
        return $this->baseAggregateQuery()
            ->orderBy('year_num', 'desc')
            ->orderBy('month_num', 'desc')
            ->orderBy('counselor_name')
            ->get();
    }

    public function findById(int $id): ?object
    {
        // No single primary key for an aggregate row – not used.
        return null;
    }

    public function create(array $data): object
    {
        throw new \BadMethodCallException('create() not supported on CounselorLogRepository (read-only aggregate).');
    }

    public function update(int $id, array $data): bool
    {
        throw new \BadMethodCallException('update() not supported on CounselorLogRepository (read-only aggregate).');
    }

    public function delete(int $id): bool
    {
        throw new \BadMethodCallException('delete() not supported on CounselorLogRepository (read-only aggregate).');
    }

    // ===================== Actual log methods =====================

    /**
     * Dropdown list of counselors (id + full_name alias).
     */
    public function listCounselors(): Collection
    {
        return DB::table('tbl_counselors')
            ->select([
                'id',
                DB::raw("name as full_name"), // alias only, you don't have full_name column
            ])
            ->orderBy('full_name')
            ->get();
    }

    public function availableYears(): Collection
    {
        return DB::table('tbl_case_notes')
            ->select(DB::raw('DISTINCT YEAR(note_date) as year'))
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    /**
     * Paginated aggregated logs for index page.
     */
    public function paginateLogs(array $filters = []): LengthAwarePaginator
    {
        $month      = (int)($filters['month'] ?? 0);
        $year       = (int)($filters['year'] ?? 0);
        $counselor  = (int)($filters['counselor_id'] ?? 0);
        $perPage    = (int)($filters['per_page'] ?? 12);

        $qb = $this->baseAggregateQuery();

        // Filter on aliases => use HAVING
        if ($year > 0) {
            $qb->having('year_num', '=', $year);
        }
        if ($month > 0) {
            $qb->having('month_num', '=', $month);
        }
        if ($counselor > 0) {
            $qb->having('counselor_id', '=', $counselor);
        }

        $qb->orderBy('year_num', 'desc')
           ->orderBy('month_num', 'desc')
           ->orderBy('counselor_name');

        return $qb->paginate($perPage);
    }

    /**
     * All rows (used for PDF export from index).
     */
    public function allLogs(array $filters = []): Collection
    {
        $month      = (int)($filters['month'] ?? 0);
        $year       = (int)($filters['year'] ?? 0);
        $counselor  = (int)($filters['counselor_id'] ?? 0);

        $qb = $this->baseAggregateQuery();

        if ($year > 0) {
            $qb->having('year_num', '=', $year);
        }
        if ($month > 0) {
            $qb->having('month_num', '=', $month);
        }
        if ($counselor > 0) {
            $qb->having('counselor_id', '=', $counselor);
        }

        $qb->orderBy('year_num', 'desc')
           ->orderBy('month_num', 'desc')
           ->orderBy('counselor_name');

        return $qb->get();
    }

    /**
     * Drilldown: one counselor + month/year.
     * Now uses PRESENTING PROBLEM from tbl_case_notes.
     */
    public function counselorMonthDetail(int $counselorId, int $month, int $year): array
    {
        // Counselor basic info
        $counselor = DB::table('tbl_counselors')
            ->select(
                'id',
                DB::raw('name as full_name'),
                'email'
            )
            ->where('id', $counselorId)
            ->first();

        if (!$counselor) {
            return ['counselor' => null];
        }

        // Students handled + case notes + presenting problem
        $students = DB::table('tbl_case_notes as cn')
            ->leftJoin('tbl_users as u', 'u.id', '=', 'cn.student_id')
            ->leftJoin('tbl_diagnosis_reports as dr', function ($join) {
                $join->on('dr.appointment_id', '=', 'cn.appointment_id');
            })
            ->select([
                'cn.student_id',
                DB::raw('COALESCE(u.name, cn.student_name) as student_name'),
                'u.email as student_email',
                'cn.note_date',
                'cn.program_year',
                'cn.presenting_problem',
                'cn.observations',
                'cn.interventions',
                'cn.response',
                'cn.plan_followup',

                // kept for reference, but UI will use presenting_problem
                'dr.diagnosis_result',
                'dr.notes as diagnosis_notes',
            ])
            ->where('cn.counselor_id', $counselorId)
            ->whereYear('cn.note_date', $year)
            ->whereMonth('cn.note_date', $month)
            ->orderBy('student_name')
            ->get();

        // Aggregate: counts per PRESENTING PROBLEM (not per diagnosis)
        $dxCounts = DB::table('tbl_case_notes as cn')
            ->where('cn.counselor_id', $counselorId)
            ->whereYear('cn.note_date', $year)
            ->whereMonth('cn.note_date', $month)
            ->whereNotNull('cn.presenting_problem')
            ->whereRaw("TRIM(cn.presenting_problem) <> ''")
            ->groupBy('cn.presenting_problem')
            ->pluck(DB::raw('COUNT(*) as cnt'), 'cn.presenting_problem')
            ->toArray();

        return [
            'counselor' => $counselor,
            'students'  => $students,
            'dxCounts'  => $dxCounts, // now keyed by presenting_problem text
        ];
    }

    // ===================== Base aggregate query (index + export) =====================

    protected function baseAggregateQuery()
    {
        return DB::table('tbl_case_notes as cn')
            ->join('tbl_counselors as c', 'c.id', '=', 'cn.counselor_id')
            ->leftJoin('tbl_users as u', 'u.id', '=', 'cn.student_id')
            ->leftJoin('tbl_diagnosis_reports as dr', function ($join) {
                $join->on('dr.appointment_id', '=', 'cn.appointment_id');
            })
            ->select([
                DB::raw('c.id as counselor_id'),
                DB::raw('c.name as counselor_name'),

                DB::raw('MONTH(cn.note_date) as month_num'),
                DB::raw('YEAR(cn.note_date) as year_num'),

                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT COALESCE(u.name, cn.student_name)
                        ORDER BY COALESCE(u.name, cn.student_name)
                        SEPARATOR ' | '
                    ) as students_list
                "),
                DB::raw('COUNT(DISTINCT cn.student_id) as students_count'),

                // IMPORTANT:
                // we keep the alias name 'dx_list' for compatibility,
                // but it now contains DISTINCT PRESENTING PROBLEMS joined by '||'
                DB::raw("
                    GROUP_CONCAT(
                        DISTINCT TRIM(cn.presenting_problem)
                        ORDER BY cn.presenting_problem
                        SEPARATOR '||'
                    ) as dx_list
                "),

                DB::raw('NULL as common_dx'),
            ])
            ->groupBy(
                'c.id',
                'counselor_name',
                DB::raw('YEAR(cn.note_date)'),
                DB::raw('MONTH(cn.note_date)')
            );
    }
}
