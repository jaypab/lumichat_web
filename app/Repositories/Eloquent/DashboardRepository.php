<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\DashboardRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardRepository implements DashboardRepositoryInterface
{
    /** Try these in order to find the appointments table present in your DB */
    private const APPT_TABLE_CANDIDATES = ['tbl_appointments', 'appointments', 'tbl_appointment'];

    /** Try these to find a datetime column for week-over-week calculations */
    private const APPT_WEEK_COL_CANDIDATES = ['scheduled_at', 'created_at', 'appointment_at', 'datetime'];

    /** Fallbacks for pure date+time schemas */
    private const APPT_DATE_FALLBACK_COL = 'date';
    private const APPT_TIME_FALLBACK_COL = 'time';

    /** Any of these mean the session is considered “handled” if an appt exists for that session */
    private const HANDLED_APPT_STATUSES = ['pending','confirmed','completed'];

    public function stats(): array
    {
        $now         = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek(); // Mon
        $endOfWeek   = $now->copy()->endOfWeek();   // Sun
        $lastStart   = $now->copy()->subWeek()->startOfWeek();
        $lastEnd     = $now->copy()->subWeek()->endOfWeek();

        /* ---------- Appointments KPI ---------- */
        $apptTable         = $this->resolveApptTable();
        $appointmentsTotal = $apptTable ? DB::table($apptTable)->count() : 0;
        $apptWeekCol       = $apptTable ? $this->resolveApptWeekColumn($apptTable) : null;

        $apptsThisWeek = 0;
        $apptsLastWeek = 0;
        if ($apptTable && $apptWeekCol) {
            $apptsThisWeek = DB::table($apptTable)
                ->whereBetween($apptWeekCol, [$startOfWeek, $endOfWeek])
                ->count();

            $apptsLastWeek = DB::table($apptTable)
                ->whereBetween($apptWeekCol, [$lastStart, $lastEnd])
                ->count();
        }
        $appointmentsTrend = $this->compareTrend($apptsThisWeek, $apptsLastWeek);

        /* ---------- Active Counselors KPI ---------- */
        $activeCounselors = Schema::hasTable('tbl_counselors')
            ? DB::table('tbl_counselors')
                ->when(Schema::hasColumn('tbl_counselors', 'status'),   fn ($q) => $q->where('status', 'active'))
                ->when(Schema::hasColumn('tbl_counselors', 'is_active'), fn ($q) => $q->orWhere('is_active', 1))
                ->count()
            : 0;

        /* ---------- Chat Sessions KPI (this week) ---------- */
        $sessionsThisWeek = 0;
        $sessionsLastWeek = 0;
        $chatSessionsTotal = 0; 
        if (Schema::hasTable('chat_sessions')) {
            // ✨ all-time total
            $chatSessionsTotal = DB::table('chat_sessions')->count(); // 👈 add

            // this week vs last week
            $sessionsThisWeek = DB::table('chat_sessions')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count();

            $sessionsLastWeek = DB::table('chat_sessions')
                ->whereBetween('created_at', [$lastStart, $lastEnd])
                ->count();
        }
        $sessionsTrend = $this->compareTrend($sessionsThisWeek, $sessionsLastWeek);

        /* ---------- Critical Cases KPI (UNHANDLED high-risk sessions, distinct users) ---------- */
        $criticalCasesTotal = 0;
        if (Schema::hasTable('chat_sessions')) {
            $criticalCasesTotal = $this->countUnhandledHighRiskUsers($apptTable);
        }

        /* ---------- Lists ---------- */
        $activities         = $this->recentActivities();
        $recentAppointments = $this->recentAppointments($apptTable);
        $recentChatSessions = $this->recentChatSessions();

         return [
            'kpis' => [
                'appointmentsTotal'    => $appointmentsTotal,
                'criticalCasesTotal'   => $criticalCasesTotal,
                'activeCounselors'     => $activeCounselors,
                'chatSessionsThisWeek' => $sessionsThisWeek,
                'chatSessionsTotal'    => $chatSessionsTotal,   
                'appointmentsTrend'    => $appointmentsTrend,
                'sessionsTrend'        => $sessionsTrend,
            ],
            'recentAppointments' => $recentAppointments,
            'activities'         => $activities,
            'recentChatSessions' => $recentChatSessions,
            'generatedAt'        => $now->toIso8601String(),
        ];
    }

    /* ===================== High-risk logic ===================== */

    /**
     * Count DISTINCT users who have at least one HIGH-risk chat session
     * that does NOT have any appointment for that SAME session (via chatbot_session_id)
     * in a handled status (pending/confirmed/completed).
     */
    private function countUnhandledHighRiskUsers(?string $apptTable): int
    {
        // Base: only high-risk chat sessions
        $cs = DB::table('chat_sessions as cs')
            ->whereIn(DB::raw("LOWER(COALESCE(cs.risk_level,''))"), ['high','high-risk','high_risk']);

        // If appointments table exists and has the needed columns, exclude sessions that are already handled
        if ($apptTable
            && Schema::hasColumn($apptTable, 'chatbot_session_id')
            && Schema::hasColumn($apptTable, 'status')) {

            $cs->whereNotExists(function ($q) use ($apptTable) {
                $q->select(DB::raw(1))
                  ->from($apptTable . ' as a')
                  ->whereColumn('a.chatbot_session_id', 'cs.id')
                  ->whereIn('a.status', self::HANDLED_APPT_STATUSES);
            });
        }

        // Distinct users still needing attention
        return $cs->distinct('cs.user_id')->count('cs.user_id');
    }

    /* ===================== Helpers ===================== */

    /** Resolve which appointments table exists (first found wins). */
    private function resolveApptTable(): ?string
    {
        foreach (self::APPT_TABLE_CANDIDATES as $name) {
            if (Schema::hasTable($name)) {
                return $name;
            }
        }
        return null;
    }

    /** Resolve the column used for “this week / last week” comparisons. */
    private function resolveApptWeekColumn(string $table): string
    {
        foreach (self::APPT_WEEK_COL_CANDIDATES as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }
        if (Schema::hasColumn($table, self::APPT_DATE_FALLBACK_COL)) {
            return self::APPT_DATE_FALLBACK_COL;
        }
        return 'created_at';
    }

    /** Compare week-over-week and return a human label. */
    private function compareTrend(int $thisWeek, int $lastWeek): string
    {
        if ($lastWeek === 0 && $thisWeek > 0)    return '↑ Higher than last week';
        if ($lastWeek === 0 && $thisWeek === 0)  return '= Same as last week';
        if ($thisWeek > $lastWeek)               return '↑ Higher than last week';
        if ($thisWeek < $lastWeek)               return '↓ Lower than last week';
        return '= Same as last week';
    }

    /** Pick the best datetime for a single appointment row. */
    private function pickApptWhen(array $row, string $table): ?string
    {
        foreach (['scheduled_at', 'appointment_at', 'datetime', 'created_at'] as $k) {
            if (!empty($row[$k])) {
                return Carbon::parse($row[$k])->toIso8601String();
            }
        }
        if (Schema::hasColumn($table, self::APPT_DATE_FALLBACK_COL) && !empty($row[self::APPT_DATE_FALLBACK_COL])) {
            $dt = trim(($row[self::APPT_DATE_FALLBACK_COL] ?? '') . ' ' . ($row[self::APPT_TIME_FALLBACK_COL] ?? '00:00:00'));
            return Carbon::parse($dt)->toIso8601String();
        }
        return null;
    }

    /** Build recent activities list (chats + registrations). */
    private function recentActivities(): array
    {
        $coalesceActor = function (string $table, string $alias): string {
            $parts = [];
            if (Schema::hasColumn($table, 'name'))      $parts[] = "$alias.name";
            if (Schema::hasColumn($table, 'full_name')) $parts[] = "$alias.full_name";
            $hasFirst = Schema::hasColumn($table, 'first_name');
            $hasLast  = Schema::hasColumn($table, 'last_name');
            if ($hasFirst && $hasLast)                  $parts[] = "CONCAT($alias.first_name,' ',$alias.last_name)";
            if (Schema::hasColumn($table, 'email'))     $parts[] = "$alias.email";
            $parts[] = "'User'";
            return 'COALESCE(' . implode(', ', $parts) . ')';
        };

        $activities = collect();

        if (Schema::hasTable('chat_sessions')) {
            $cq = DB::table('chat_sessions as cs')
                ->orderByDesc('cs.created_at')
                ->limit(5);

            if (Schema::hasTable('tbl_registration')) {
                $cq->leftJoin('tbl_registration as u', 'u.id', '=', 'cs.user_id');
                $actorExpr = $coalesceActor('tbl_registration', 'u');
            } elseif (Schema::hasTable('users')) {
                $cq->leftJoin('users as u', 'u.id', '=', 'cs.user_id');
                $actorExpr = $coalesceActor('users', 'u');
            } else {
                $actorExpr = "'User'";
            }

            $chatActs = $cq->selectRaw("cs.created_at, cs.topic_summary, $actorExpr as actor_name")
                ->get()
                ->map(fn ($r) => [
                    'event'      => 'chat_session.started',
                    'actor'      => $r->actor_name,
                    'meta'       => $r->topic_summary,
                    'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                ]);

            $activities = $activities->merge($chatActs);
        }

        if (Schema::hasTable('tbl_registration')) {
            $regActs = DB::table('tbl_registration')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(function ($r) {
                    $name    = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
                    $display = $name !== '' ? $name : ($r->email ?? 'User');
                    return [
                        'event'      => 'user.registered',
                        'actor'      => $display,
                        'meta'       => null,
                        'created_at' => Carbon::parse($r->created_at)->toIso8601String(),
                    ];
                });
            $activities = $activities->merge($regActs);
        }

        return $activities
            ->sortByDesc('created_at')
            ->values()
            ->take(5)
            ->all();
    }

    /** Build recent appointments (newest first) with defensive column detection. */
    private function recentAppointments(?string $apptTable): array
    {
        if (!$apptTable) return [];

        // Choose best order column
        $orderCol = null;
        foreach (['scheduled_at', 'created_at', 'appointment_at', 'datetime', 'id'] as $c) {
            if (Schema::hasColumn($apptTable, $c)) { $orderCol = $c; break; }
        }

        $q = DB::table($apptTable);
        if ($orderCol) $q->orderByDesc($orderCol);

        $rows = $q->limit(5)->get();

        $out = [];
        foreach ($rows as $r) {
            $arr = (array) $r;
            $out[] = [
                'id'           => $arr['id']           ?? null,
                'status'       => $arr['status']       ?? ($arr['state'] ?? 'scheduled'),
                'when'         => $this->pickApptWhen($arr, $apptTable),
                'student_id'   => $arr['student_id']   ?? null,
                'counselor_id' => $arr['counselor_id'] ?? null,
                'notes'        => $arr['notes']        ?? null,
            ];
        }

        return $out;
    }

    /** Build recent chat sessions with actor name coalescing. */
    private function recentChatSessions(): array
    {
        if (!Schema::hasTable('chat_sessions')) return [];

        $coalesceActor = function (string $table, string $alias): string {
            $parts = [];
            if (Schema::hasColumn($table, 'name'))      $parts[] = "$alias.name";
            if (Schema::hasColumn($table, 'full_name')) $parts[] = "$alias.full_name";
            $hasFirst = Schema::hasColumn($table, 'first_name');
            $hasLast  = Schema::hasColumn($table, 'last_name');
            if ($hasFirst && $hasLast)                  $parts[] = "CONCAT($alias.first_name,' ',$alias.last_name)";
            if (Schema::hasColumn($table, 'email'))     $parts[] = "$alias.email";
            $parts[] = "'User'";
            return 'COALESCE(' . implode(', ', $parts) . ')';
        };

        $cq = DB::table('chat_sessions as cs')
            ->orderByDesc('cs.created_at')
            ->limit(5);

        if (Schema::hasTable('tbl_registration')) {
            $cq->leftJoin('tbl_registration as u', 'u.id', '=', 'cs.user_id');
            $actorExpr = $coalesceActor('tbl_registration', 'u');
        } elseif (Schema::hasTable('users')) {
            $cq->leftJoin('users as u', 'u.id', '=', 'cs.user_id');
            $actorExpr = $coalesceActor('users', 'u');
        } else {
            $actorExpr = "'User'";
        }

        return $cq
            ->selectRaw("cs.created_at, cs.topic_summary, cs.risk_level, $actorExpr as actor_name")
            ->get()
            ->map(fn ($r) => [
                'created_at'    => Carbon::parse($r->created_at)->toIso8601String(),
                'topic_summary' => $r->topic_summary ?: 'Starting conversation…',
                'risk_level'    => $r->risk_level ?? 'low',
                'actor'         => $r->actor_name,
            ])
            ->all();
    }
}
