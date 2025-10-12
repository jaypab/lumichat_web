<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CounselorLogRepository implements CounselorLogRepositoryInterface
{
    // === Tables (kept from your controller) ===
    private const APPOINTMENTS_TBL = 'tbl_appointments';        // a.id, a.student_id, a.counselor_id, a.scheduled_at
    private const COUNSELORS_TBL   = 'tbl_counselors';          // c.id, c.name
    private const STUDENTS_TBL     = 'tbl_registration';        // s.id, s.full_name
    private const DIAG_REPORTS_TBL = 'tbl_diagnosis_reports';   // dr.id, dr.student_id, dr.counselor_id, dr.diagnosis_result

    /* =================== CRUD (baseline consistency) =================== */

    public function all(): Collection
    {
        return DB::table(self::COUNSELORS_TBL)->orderBy('name')->get();
    }

    public function findById(int $id): ?object
    {
        return DB::table(self::COUNSELORS_TBL)->where('id', $id)->first();
    }

    public function create(array $data): object
    {
        $id = DB::table(self::COUNSELORS_TBL)->insertGetId($data);
        return (object) array_merge(['id' => $id], $data);
    }

    public function update(int $id, array $data): bool
    {
        return (bool) DB::table(self::COUNSELORS_TBL)->where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) DB::table(self::COUNSELORS_TBL)->where('id', $id)->delete();
    }

    /* =================== Queries used by the Admin UI =================== */

    public function listCounselors(): Collection
    {
        return DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw('name as full_name'))
            ->orderBy('full_name')
            ->get();
    }

    public function availableYears(): Collection
    {
        return DB::table(self::APPOINTMENTS_TBL)
            ->selectRaw('DISTINCT YEAR(scheduled_at) as y')
            ->orderByDesc('y')
            ->pluck('y');
    }

    public function paginateLogs(array $filters = []): LengthAwarePaginator
    {
        $month = isset($filters['month']) ? (int) $filters['month'] : null;       // 1..12 | null
        $year  = isset($filters['year'])  ? (int) $filters['year']  : null;       // yyyy | null
        $cid   = isset($filters['counselor_id']) ? (int) $filters['counselor_id'] : null;
        $per   = isset($filters['per_page']) ? (int) $filters['per_page'] : 12;

        // --- Base GROUP (one row per counselor x month x year) ---
        $base = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->join(self::COUNSELORS_TBL.' as c', 'c.id', '=', 'a.counselor_id')
            ->leftJoin(self::STUDENTS_TBL.' as s', 's.id', '=', 'a.student_id')
            ->selectRaw("
                c.id  as counselor_id,
                c.name as counselor_name,
                MONTH(a.scheduled_at)  as month_num,
                YEAR(a.scheduled_at)   as year_num,
                DATE_FORMAT(a.scheduled_at, '%M %Y') as month_year,
                COUNT(DISTINCT a.student_id) as students_count,
                GROUP_CONCAT(DISTINCT s.full_name ORDER BY s.full_name SEPARATOR ' | ') as students_list
            ")
            ->when($cid,  fn($q) => $q->where('a.counselor_id', $cid))
            ->when($month, fn($q) => $q->whereRaw('MONTH(a.scheduled_at) = ?', [$month]))
            ->when($year,  fn($q) => $q->whereRaw('YEAR(a.scheduled_at)  = ?', [$year]))
            ->groupBy('c.id','c.name','month_num','year_num','month_year');

        // --- Diagnosis counts per counselor x month x year x result ---
        $dxAgg = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->join(self::DIAG_REPORTS_TBL.' as dr', function ($j) {
                $j->on('dr.student_id', '=', 'a.student_id')
                  ->on('dr.counselor_id', '=', 'a.counselor_id');
            })
            ->selectRaw("
                a.counselor_id,
                MONTH(a.scheduled_at) as month_num,
                YEAR(a.scheduled_at)  as year_num,
                dr.diagnosis_result,
                COUNT(*) as cnt
            ")
            ->whereNotNull('dr.diagnosis_result')
            ->where('dr.diagnosis_result','<>','')
            ->when($cid,  fn($q) => $q->where('a.counselor_id', $cid))
            ->when($month, fn($q) => $q->whereRaw('MONTH(a.scheduled_at) = ?', [$month]))
            ->when($year,  fn($q) => $q->whereRaw('YEAR(a.scheduled_at)  = ?', [$year]))
            ->groupBy('a.counselor_id','month_num','year_num','dr.diagnosis_result');

        // --- Pick the TOP diagnosis per counselor x month x year ---
        $dxTop = DB::query()
        ->fromSub($dxAgg, 't')
        ->selectRaw("
            t.counselor_id,
            t.month_num,
            t.year_num,
            -- ordered list by frequency; we’ll trim to 3 in SQL
            SUBSTRING_INDEX(
                GROUP_CONCAT(t.diagnosis_result ORDER BY t.cnt DESC SEPARATOR '||'),
                '||', 3
            ) as dx_list
        ")
        ->groupBy('t.counselor_id','t.month_num','t.year_num');

        // --- Final query: base LEFT JOIN top diagnosis ---
        $q = DB::query()
        ->fromSub($base, 'g')
        ->leftJoinSub($dxTop, 'd', function ($j) {
            $j->on('d.counselor_id','=','g.counselor_id')
            ->on('d.month_num','=','g.month_num')
            ->on('d.year_num','=','g.year_num');
        })
        ->selectRaw('g.*, d.dx_list')  
        ->orderByDesc('g.month_num')
        ->orderBy('g.counselor_name');

        return $q->paginate($per)->withQueryString();
    }

    public function counselorMonthDetail(int $counselorId, int $month, int $year): array
    {
        $c = DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw('name as full_name'))
            ->where('id', $counselorId)
            ->first();

        // Per-appointment list with latest diagnosis (if any) for that student+counselor
        $students = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->leftJoin(self::STUDENTS_TBL.' as s', 's.id', '=', 'a.student_id')
            ->selectRaw("
                a.id as appointment_id,
                s.full_name as student_name,
                DATE_FORMAT(a.scheduled_at,'%b %d, %Y %I:%i %p') as scheduled_at_fmt,
                COALESCE((
                    SELECT dr3.diagnosis_result
                    FROM ".self::DIAG_REPORTS_TBL." dr3
                    WHERE dr3.student_id = a.student_id
                      AND dr3.counselor_id = a.counselor_id
                    ORDER BY dr3.id DESC
                    LIMIT 1
                ), '—') as diagnosis_result
            ")
            ->where('a.counselor_id', $counselorId)
            ->whereRaw('MONTH(a.scheduled_at) = ?', [$month])
            ->whereRaw('YEAR(a.scheduled_at)  = ?', [$year])
            ->orderBy('a.scheduled_at', 'asc')
            ->get();

        // Diagnosis histogram within this month (count by result)
        $dxCounts = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->join(self::DIAG_REPORTS_TBL.' as dr', function ($j) {
                $j->on('dr.student_id', '=', 'a.student_id')
                  ->on('dr.counselor_id', '=', 'a.counselor_id');
            })
            ->selectRaw('dr.diagnosis_result, COUNT(*) as cnt')
            ->where('a.counselor_id', $counselorId)
            ->whereRaw('MONTH(a.scheduled_at) = ?', [$month])
            ->whereRaw('YEAR(a.scheduled_at)  = ?', [$year])
            ->whereNotNull('dr.diagnosis_result')
            ->where('dr.diagnosis_result','<>','')
            ->groupBy('dr.diagnosis_result')
            ->orderByDesc('cnt')
            ->get();

        return [
            'counselor' => $c,
            'students'  => $students,
            'dxCounts'  => $dxCounts,
        ];
    }
}
