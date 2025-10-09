<?php

namespace App\Repositories\Eloquent;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChatbotSessionRepository implements ChatbotSessionRepositoryInterface
{
    /** -------- CRUD -------- */

    public function all(): Collection
    {
        return ChatSession::with('user')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id, array $with = []): ?object
    {
        return ChatSession::with($with)->find($id);
    }

    public function create(array $data): object
    {
        return ChatSession::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $session = ChatSession::findOrFail($id);
        return (bool) $session->update($data);
    }

    public function delete(int $id): bool
    {
        $session = ChatSession::findOrFail($id);
        return (bool) $session->delete();
    }

    /** -------- Admin index: search + date + sort + pagination -------- */

    public function paginateWithFilters(string $q = '', string $dateKey = 'all', int $perPage = 10, string $sort = 'newest'): LengthAwarePaginator
    {
        $query = $this->baseFilteredQuery($q, $dateKey);
        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    /** -------- Export (same filters + sort, no pagination) -------- */

    public function allWithFilters(string $q = '', string $dateKey = 'all', string $sort = 'newest'): Collection
    {
        $query = $this->baseFilteredQuery($q, $dateKey);
        $this->applySort($query, $sort);

        return $query->get();
    }

    /** -------- Admin show -------- */

    public function findWithOrderedChats(int $id): ?object
    {
        return ChatSession::with([
            'user',
            'chats' => fn ($q) => $q->orderBy('created_at'), // oldest → newest
        ])->find($id);
    }

    /** -------- Calendar counts -------- */

    public function perDayCountsForUser(int $userId, string $fromDate, string $toDate): array
    {
        return ChatSession::query()
            ->where('user_id', $userId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    public function getUserIdBySessionId(int $sessionId): ?int
    {
        return ChatSession::query()->where('id', $sessionId)->value('user_id');
    }

    /** -------- Private helpers -------- */

    private function baseFilteredQuery(string $q, string $dateKey): Builder
    {
        $query = ChatSession::query()->with('user');

        // free text search across id, topic_summary, user name/email
        $q = trim($q);
        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function (Builder $sub) use ($like, $q) {
                // numeric id
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
                // codes like LMC-YYYY-#### (use the last 4 digits)
                if (preg_match('/^LMC-\d{4}-(\d{4})$/i', $q, $m)) {
                    $sub->orWhere('id', (int) $m[1]);
                }
                $sub->orWhere('topic_summary', 'like', $like)
                    ->orWhereHas('user', function (Builder $uq) use ($like) {
                        $uq->where('name', 'like', $like)
                           ->orWhere('email', 'like', $like);
                    });
            });
        }

        // relative date filters
        $this->applyDateKeyFilter($query, $dateKey);

        return $query;
    }

    private function applyDateKeyFilter(Builder $query, string $dateKey): Builder
    {
        if ($dateKey === '7d') {
            $query->where('created_at', '>=', now()->subDays(7));
        } elseif ($dateKey === '30d') {
            $query->where('created_at', '>=', now()->subDays(30));
        } elseif ($dateKey === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
        }
        return $query;
    }

    /**
     * Add computed flags used by “unresolved/handled” sorts:
     *  - has_active_after: any pending/confirmed appt created at/after this session
     *  - has_completed_after: any completed appt completed (updated_at) at/after this session
     *  - is_high: high if risk_level is 'high' (always) and, if present, risk_score >= 80
     */
    private function addResolutionComputedColumns(Builder $query): void
    {
        // Always select base table columns to avoid ambiguity when we join users later
        $query->select('chat_sessions.*');

        // Appointments created at/after this session (active)
        $query->selectSub(function ($sub) {
            $sub->from('tbl_appointments as a')
                ->whereColumn('a.student_id', 'chat_sessions.user_id')
                ->whereIn('a.status', ['pending', 'confirmed'])
                ->whereColumn('a.created_at', '>=', 'chat_sessions.created_at')
                ->selectRaw('1')
                ->limit(1);
        }, 'has_active_after');

        // Appointments completed at/after this session
        $query->selectSub(function ($sub) {
            $sub->from('tbl_appointments as a2')
                ->whereColumn('a2.student_id', 'chat_sessions.user_id')
                ->where('a2.status', 'completed')
                ->whereColumn('a2.updated_at', '>=', 'chat_sessions.created_at')
                ->selectRaw('1')
                ->limit(1);
        }, 'has_completed_after');

        // Build a safe "is_high" condition depending on the columns that actually exist
        $parts = [];

        // risk_level is very likely present
        if (Schema::hasColumn('chat_sessions', 'risk_level')) {
            $parts[] = "LOWER(COALESCE(risk_level,'')) IN ('high','high-risk','high_risk')";
        }

        // risk_score may or may not exist
        if (Schema::hasColumn('chat_sessions', 'risk_score')) {
            $parts[] = "COALESCE(risk_score,0) >= 80";
        }

        // If neither column exists, the condition should be false (0)
        $cond = $parts ? implode(' OR ', $parts) : '0';

        $query->selectRaw("CASE WHEN {$cond} THEN 1 ELSE 0 END as is_high");
    }

    /** Apply the chosen sort */
    private function applySort(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;

             case 'risk':
                $hasScore = Schema::hasColumn('chat_sessions', 'risk_score');
                if ($hasScore) {
                    // High (by level or score) → Moderate → Low → others, then newest
                    $query->orderByRaw("
                            CASE 
                                WHEN LOWER(COALESCE(risk_level,'')) IN ('high','high-risk','high_risk')
                                    OR COALESCE(risk_score,0) >= 80 THEN 0
                                WHEN LOWER(COALESCE(risk_level,'')) = 'moderate' THEN 1
                                WHEN LOWER(COALESCE(risk_level,'')) = 'low' THEN 2
                                ELSE 3
                            END
                        ")
                        ->orderByDesc('created_at');
                } else {
                    $query->orderByRaw("
                            CASE LOWER(COALESCE(risk_level,'')) 
                                WHEN 'high' THEN 0 
                                WHEN 'moderate' THEN 1 
                                WHEN 'low' THEN 2 
                                ELSE 3 
                            END
                        ")
                        ->orderByDesc('created_at');
                }
                break;

            case 'student_asc':
            // pick the correct users table at runtime
            $usersTable = Schema::hasTable('tbl_users')
                ? 'tbl_users'
                : (Schema::hasTable('users') ? 'users' : null);

            if ($usersTable) {
                // stable name sort, then newest session
                $query->leftJoin($usersTable . ' as u', 'u.id', '=', 'chat_sessions.user_id')
                    ->orderBy('u.name', 'asc')
                    ->orderByDesc('chat_sessions.created_at')
                    ->select('chat_sessions.*'); // keep base columns only
            } else {
                // fallback if no users table is available
                $query->orderBy('chat_sessions.user_id', 'asc')
                    ->orderByDesc('chat_sessions.created_at');
            }
            break;

            case 'session_asc':
                $query->orderBy('id', 'asc');
                break;

            case 'session_desc':
                $query->orderBy('id', 'desc');
                break;

            case 'unresolved':
                // actionable first: high-risk AND no active-after AND no completed-after
                $this->addResolutionComputedColumns($query);
                $query->orderByDesc(DB::raw("
                        CASE 
                            WHEN is_high = 1
                             AND COALESCE(has_active_after,0) = 0
                             AND COALESCE(has_completed_after,0) = 0
                            THEN 1 ELSE 0
                        END
                    "))
                    ->orderByDesc('is_high')
                    ->orderByDesc('created_at');
                break;

            case 'handled':
                // anything with a follow-up (active or completed) at/after this session first
                $this->addResolutionComputedColumns($query);
                $query->orderByDesc(DB::raw("
                        CASE 
                            WHEN COALESCE(has_active_after,0) = 1
                              OR COALESCE(has_completed_after,0) = 1
                            THEN 1 ELSE 0
                        END
                    "))
                    ->orderByDesc('created_at');
                break;

            default: // 'newest'
                $query->orderByDesc('created_at');
                break;
        }
    }
}
