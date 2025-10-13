<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CounselorLogRepository implements CounselorLogRepositoryInterface
{
    private const APPOINTMENTS_TBL = 'tbl_appointments';
    private const COUNSELORS_TBL   = 'tbl_counselors';
    private const USERS_TBL        = 'tbl_users';
    private const DIAG_REPORTS_TBL = 'tbl_diagnosis_reports';
    private const LOGS_TBL         = 'tbl_counselor_logs';

    /* ---------- helpers ---------- */

    private function userNameExpr(string $u = 'u', string $a = 'a'): string
    {
        $parts = [];
        if (Schema::hasColumn(self::USERS_TBL, 'name'))         $parts[] = "NULLIF({$u}.name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'full_name'))    $parts[] = "NULLIF({$u}.full_name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'display_name')) $parts[] = "NULLIF({$u}.display_name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'email'))        $parts[] = "NULLIF({$u}.email,'')";
        $parts[] = "CONCAT('Student #', {$a}.student_id)";
        return 'COALESCE('.implode(', ', $parts).')';
    }

    /* ---------- CRUD / lists ---------- */

    public function all(): Collection
    {
        $hasFull = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name');
        return DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw(($hasFull ? 'full_name' : 'name') . ' as full_name'))
            ->orderBy('full_name')
            ->get();
    }

    public function findById(int $id): ?object
    {
        $hasFull = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name');
        return DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw(($hasFull ? 'full_name' : 'name') . ' as full_name'))
            ->where('id', $id)
            ->first();
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

    public function listCounselors(): Collection
    {
        $hasFull = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name');
        return DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw(($hasFull ? 'full_name' : 'name') . ' as full_name'))
            ->orderBy('full_name')
            ->get();
    }

    public function availableYears(): Collection
    {
        if (!Schema::hasTable(self::APPOINTMENTS_TBL)) return collect();
        return DB::table(self::APPOINTMENTS_TBL)
            ->selectRaw('DISTINCT YEAR(scheduled_at) as y')
            ->orderByDesc('y')
            ->pluck('y');
    }

    /* ---------- index (paginated) ---------- */

    public function paginateLogs(array $filters = []): LengthAwarePaginator
    {
        $month = isset($filters['month']) ? (int) $filters['month'] : null;
        $year  = isset($filters['year'])  ? (int) $filters['year']  : null;
        $cid   = isset($filters['counselor_id']) ? (int) $filters['counselor_id'] : null;
        $per   = isset($filters['per_page']) ? (int) $filters['per_page'] : 12;

        if (Schema::hasTable(self::LOGS_TBL)) {
            $nameCol = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name') ? 'full_name' : 'name';

            $q = DB::table(self::LOGS_TBL.' as g')
                ->join(self::COUNSELORS_TBL.' as c', 'c.id','=','g.counselor_id')
                ->selectRaw("
                    g.counselor_id,
                    c.$nameCol as counselor_name,
                    g.month as month_num,
                    g.year  as year_num,
                    DATE_FORMAT(STR_TO_DATE(CONCAT(g.year,'-',g.month,'-01'),'%Y-%m-%d'), '%M %Y') as month_year,
                    g.students_count,
                    g.students_list,
                    g.common_dx as common_dx
                ")
                ->when($cid,  fn($q) => $q->where('g.counselor_id', $cid))
                ->when($month, fn($q) => $q->where('g.month', $month))
                ->when($year,  fn($q) => $q->where('g.year',  $year))
                ->orderByDesc('g.year')->orderByDesc('g.month')->orderBy("c.$nameCol");

            return $q->paginate($per)->withQueryString();
        }

        $nameCol  = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name') ? 'full_name' : 'name';
        $nameExpr = $this->userNameExpr('u', 'a');

        $base = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->join(self::COUNSELORS_TBL.' as c', 'c.id', '=', 'a.counselor_id')
            ->leftJoin(self::USERS_TBL.' as u', 'u.id', '=', 'a.student_id')
            ->selectRaw("
                c.id  as counselor_id,
                COALESCE(c.$nameCol, c.name) as counselor_name,
                MONTH(a.scheduled_at)  as month_num,
                YEAR(a.scheduled_at)   as year_num,
                DATE_FORMAT(a.scheduled_at, '%M %Y') as month_year,
                COUNT(DISTINCT a.student_id) as students_count,
                GROUP_CONCAT(DISTINCT {$nameExpr} ORDER BY {$nameExpr} SEPARATOR ' | ') as students_list
            ")
            ->when($cid,  fn($q) => $q->where('a.counselor_id', $cid))
            ->when($month, fn($q) => $q->whereRaw('MONTH(a.scheduled_at) = ?', [$month]))
            ->when($year,  fn($q) => $q->whereRaw('YEAR(a.scheduled_at)  = ?', [$year]))
            ->groupBy('c.id','c.full_name','c.name','month_num','year_num','month_year');

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

        $dxTop = DB::query()
            ->fromSub($dxAgg, 't')
            ->selectRaw("
                t.counselor_id,
                t.month_num,
                t.year_num,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(t.diagnosis_result ORDER BY t.cnt DESC SEPARATOR '||'),
                    '||', 3
                ) as dx_list
            ")
            ->groupBy('t.counselor_id','t.month_num','t.year_num');

        return DB::query()
            ->fromSub($base, 'g')
            ->leftJoinSub($dxTop, 'd', function ($j) {
                $j->on('d.counselor_id','=','g.counselor_id')
                  ->on('d.month_num','=','g.month_num')
                  ->on('d.year_num','=','g.year_num');
            })
            ->selectRaw('g.*, d.dx_list')
            ->orderByDesc('g.month_num')
            ->orderBy('g.counselor_name')
            ->paginate($per)
            ->withQueryString();
    }

    /* ---------- show (detail) ---------- */

    public function counselorMonthDetail(int $counselorId, int $month, int $year): array
    {
        $nameCol = Schema::hasColumn(self::COUNSELORS_TBL, 'full_name') ? 'full_name' : 'name';
        $c = DB::table(self::COUNSELORS_TBL)
            ->select('id', DB::raw("$nameCol as full_name"))
            ->where('id', $counselorId)
            ->first();

        $nameExpr = $this->userNameExpr('u', 'a');

        $students = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->leftJoin(self::USERS_TBL.' as u', 'u.id', '=', 'a.student_id')
            ->selectRaw("
                a.student_id as student_id,
                {$nameExpr} as student_name,
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

    /* ---------- summary refresh/backfill ---------- */

    public function refreshMonth(int $counselorId, int $month, int $year): void
    {
        if (!Schema::hasTable(self::LOGS_TBL)) return;

        $nameExpr = $this->userNameExpr('u', 'a');

        $students = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->leftJoin(self::USERS_TBL.' as u', 'u.id', '=', 'a.student_id')
            ->where('a.counselor_id', $counselorId)
            ->whereRaw('MONTH(a.scheduled_at) = ?', [$month])
            ->whereRaw('YEAR(a.scheduled_at)  = ?', [$year])
            ->selectRaw("{$nameExpr} as sname")
            ->distinct()
            ->orderBy('sname')
            ->pluck('sname')
            ->all();

        $studentsCount = count($students);
        $studentsList  = $studentsCount ? implode(' | ', array_slice($students, 0, 50)) : null;

        $dxTop = DB::table(self::APPOINTMENTS_TBL.' as a')
            ->join(self::DIAG_REPORTS_TBL.' as dr', function ($j) {
                $j->on('dr.student_id','=','a.student_id')
                  ->on('dr.counselor_id','=','a.counselor_id');
            })
            ->where('a.counselor_id', $counselorId)
            ->whereRaw('MONTH(a.scheduled_at) = ?', [$month])
            ->whereRaw('YEAR(a.scheduled_at)  = ?', [$year])
            ->whereNotNull('dr.diagnosis_result')
            ->where('dr.diagnosis_result','<>','')
            ->selectRaw('dr.diagnosis_result as label, COUNT(*) as c')
            ->groupBy('dr.diagnosis_result')
            ->orderByDesc('c')
            ->limit(3)
            ->pluck('label')
            ->map(fn($v) => (string) $v)
            ->all();

        DB::table(self::LOGS_TBL)->updateOrInsert(
            ['counselor_id'=>$counselorId,'month'=>$month,'year'=>$year],
            [
                'students_count' => $studentsCount,
                'students_list'  => $studentsList,
                'common_dx'      => json_encode($dxTop, JSON_UNESCAPED_UNICODE),
                'generated_at'   => now(),
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }

    public function backfillAllLogs(): int
    {
        if (!Schema::hasTable(self::LOGS_TBL)) return 0;

        $groups = DB::table(self::APPOINTMENTS_TBL)
            ->selectRaw('counselor_id, MONTH(scheduled_at) as m, YEAR(scheduled_at) as y')
            ->groupBy('counselor_id', DB::raw('MONTH(scheduled_at)'), DB::raw('YEAR(scheduled_at)'))
            ->get();

        $n = 0;
        foreach ($groups as $g) {
            $this->refreshMonth((int)$g->counselor_id, (int)$g->m, (int)$g->y);
            $n++;
        }
        return $n;
    }
}
