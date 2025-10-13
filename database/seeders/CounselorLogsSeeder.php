<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CounselorLogsSeeder extends Seeder
{
    private const LOGS_TBL     = 'tbl_counselor_logs';
    private const APPTS_TBL    = 'tbl_appointments';
    private const REPORTS_TBL  = 'tbl_diagnosis_reports';
    private const USERS_TBL    = 'tbl_users';

    public function run(): void
    {
        if (!Schema::hasTable(self::APPTS_TBL) || !Schema::hasTable(self::LOGS_TBL)) {
            $this->command?->warn('Missing tables: tbl_appointments / tbl_counselor_logs. Skipping.');
            return;
        }

        $groups = DB::table(self::APPTS_TBL)
            ->selectRaw('counselor_id, MONTH(scheduled_at) as m, YEAR(scheduled_at) as y')
            ->groupBy('counselor_id', DB::raw('MONTH(scheduled_at)'), DB::raw('YEAR(scheduled_at)'))
            ->get();

        $n = 0;
        foreach ($groups as $g) {
            $this->refreshOne((int) $g->counselor_id, (int) $g->m, (int) $g->y);
            $n++;
        }

        $this->command?->info("Backfilled {$n} counselor log group(s).");
    }

    /** Build a safe COALESCE for user names based on columns that exist. */
    private function userNameExpr(string $u = 'u', string $a = 'a'): string
    {
        $parts = [];
        if (Schema::hasColumn(self::USERS_TBL, 'name'))         $parts[] = "NULLIF({$u}.name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'full_name'))    $parts[] = "NULLIF({$u}.full_name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'display_name')) $parts[] = "NULLIF({$u}.display_name,'')";
        if (Schema::hasColumn(self::USERS_TBL, 'email'))        $parts[] = "NULLIF({$u}.email,'')";
        // final fallback to ID label
        $parts[] = "CONCAT('Student #', {$a}.student_id)";
        return 'COALESCE('.implode(', ', $parts).')';
    }

    /** Recompute and upsert one (counselor, month, year) row. */
    private function refreshOne(int $counselorId, int $month, int $year): void
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        $nameExpr = $this->userNameExpr('u', 'a');

        // Distinct student names for the month
        $students = DB::table(self::APPTS_TBL.' as a')
            ->leftJoin(self::USERS_TBL.' as u', 'u.id', '=', 'a.student_id')
            ->where('a.counselor_id', $counselorId)
            ->whereBetween('a.scheduled_at', [$start, $end])
            ->selectRaw("DISTINCT {$nameExpr} as sname")
            ->orderBy('sname')
            ->pluck('sname')
            ->map(fn($v) => (string) $v)
            ->all();

        $studentsCount = count($students);
        $studentsList  = $studentsCount ? implode(' | ', array_slice($students, 0, 200)) : null;

        // Top 3 diagnoses
        $dxCol = null;
        if (Schema::hasTable(self::REPORTS_TBL)) {
            if (Schema::hasColumn(self::REPORTS_TBL, 'diagnosis_result')) $dxCol = 'diagnosis_result';
            elseif (Schema::hasColumn(self::REPORTS_TBL, 'diagnosis'))    $dxCol = 'diagnosis';
        }

        $dxLabels = [];
        if ($dxCol) {
            $dxLabels = DB::table(self::APPTS_TBL.' as a')
                ->join(self::REPORTS_TBL.' as dr', function ($j) {
                    $j->on('dr.student_id','=','a.student_id')
                      ->on('dr.counselor_id','=','a.counselor_id');
                })
                ->where('a.counselor_id', $counselorId)
                ->whereBetween('a.scheduled_at', [$start, $end])
                ->whereNotNull("dr.{$dxCol}")
                ->where("dr.{$dxCol}", '<>', '')
                ->selectRaw("dr.{$dxCol} as label, COUNT(*) as c")
                ->groupBy("dr.{$dxCol}")
                ->orderByDesc('c')
                ->limit(3)
                ->pluck('label')
                ->map(fn($v) => (string) $v)
                ->all();
        }

        DB::table(self::LOGS_TBL)->updateOrInsert(
            ['counselor_id' => $counselorId, 'month' => $month, 'year' => $year],
            [
                'students_count' => $studentsCount,
                'students_list'  => $studentsList,
                'common_dx'      => json_encode(array_values($dxLabels), JSON_UNESCAPED_UNICODE),
                'generated_at'   => now(),
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );
    }
}
