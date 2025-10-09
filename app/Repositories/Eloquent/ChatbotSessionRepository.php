<?php

namespace App\Repositories\Eloquent;

use App\Models\ChatSession;
use App\Models\User;
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChatbotSessionRepository implements ChatbotSessionRepositoryInterface
{
    /** -------- CRUD (standardized) -------- */

    public function all(): Collection
    {
        // Keep an opinionated default for generic "all()"
        return ChatSession::with('user')
            ->orderByRaw("
                CASE
                  WHEN LOWER(COALESCE(risk_level,'')) IN ('high','high-risk','high_risk')
                       OR COALESCE(risk_score,0) >= 80
                  THEN 0
                  WHEN LOWER(COALESCE(risk_level,'')) = 'moderate' THEN 1
                  WHEN LOWER(COALESCE(risk_level,'')) = 'low' THEN 2
                  ELSE 3
                END
            ")
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

    /** -------- Admin index: search + date filters + pagination -------- */

    public function paginateWithFilters(
        string $q = '',
        string $dateKey = 'all',
        int $perPage = 10,
        string $sort = 'newest'   // ✅ supports ?sort=
    ): LengthAwarePaginator {
        $query = $this->baseFilteredQuery($q, $dateKey);

        $this->applySort($query, $sort);

        return $query->paginate($perPage)->appends([
            'q'    => $q,
            'date' => $dateKey,
            'sort' => $sort,
        ]);
    }

    /** -------- Used by PDF export (same filters, no pagination) -------- */

    public function allWithFilters(
        string $q = '',
        string $dateKey = 'all',
        string $sort = 'newest'   // ✅ keep export consistent with UI
    ): Collection {
        $query = $this->baseFilteredQuery($q, $dateKey);

        $this->applySort($query, $sort);

        return $query->get();
    }

    /** -------- Admin show: one session + ordered chats -------- */

    public function findWithOrderedChats(int $id): ?object
    {
        return ChatSession::with([
            'user',
            'chats' => fn ($q) => $q->orderBy('created_at'), // oldest → newest
        ])->find($id);
    }

    /** -------- Calendar heatmap: per-day counts for a user -------- */

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
        $table = (new ChatSession)->getTable();               // e.g. 'chat_sessions'
        $query = ChatSession::query()->with('user')->select("{$table}.*");

        // free text search across id, topic_summary, user name/email
        $q = trim($q);
        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function (Builder $sub) use ($like, $q) {
                // numeric id search
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
                // support codes like LMC-YYYY-#### (extract last 4 digits)
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
     * Centralized ordering used by list + export.
     *
     * Supported $sort values:
     *  - newest | oldest
     *  - risk
     *  - unresolved | handled
     *  - student_asc
     *  - session_asc | session_desc
     */
    private function applySort(Builder $query, string $sort): void
    {
        $table    = (new ChatSession)->getTable(); // 'chat_sessions'
        $usersTbl = (new User)->getTable();        // 'users'
        $apptTbl  = 'tbl_appointments';            // your appointments table name

        switch ($sort) {
            case 'oldest':
                $query->orderBy("{$table}.created_at", 'asc');
                break;

            case 'student_asc':
                // join users for alphabetical sort
                $query->leftJoin("{$usersTbl} as u", "u.id", "=", "{$table}.user_id")
                      ->orderBy('u.name', 'asc')
                      ->select("{$table}.*");
                break;

            case 'session_asc':
                $query->orderBy("{$table}.id", 'asc');
                break;

            case 'session_desc':
                $query->orderBy("{$table}.id", 'desc');
                break;

            case 'risk':
                // High-risk first (label high/high-risk/high_risk OR score >= 80),
                // then higher score, then newest
                $query->orderByRaw("
                    CASE
                      WHEN LOWER(COALESCE({$table}.risk_level,'')) IN ('high','high-risk','high_risk')
                           OR COALESCE({$table}.risk_score,0) >= 80
                      THEN 0 ELSE 1
                    END ASC
                ")
                ->orderByDesc("{$table}.risk_score")
                ->orderByDesc("{$table}.created_at");
                break;

            case 'unresolved':
                // Unresolved first = no active/completed appointment AFTER the session
                $query->orderByRaw("
                    CASE
                      WHEN (
                        EXISTS (
                          SELECT 1 FROM {$apptTbl} a
                           WHERE a.student_id = {$table}.user_id
                             AND a.status IN ('pending','confirmed')
                             AND a.created_at >= {$table}.created_at
                        )
                        OR EXISTS (
                          SELECT 1 FROM {$apptTbl} a2
                           WHERE a2.student_id = {$table}.user_id
                             AND a2.status = 'completed'
                             AND a2.updated_at >= {$table}.created_at
                        )
                      ) THEN 1 ELSE 0
                    END ASC
                ")
                ->orderByDesc("{$table}.created_at");
                break;

            case 'handled':
                // Handled/Completed first = the opposite
                $query->orderByRaw("
                    CASE
                      WHEN (
                        EXISTS (
                          SELECT 1 FROM {$apptTbl} a
                           WHERE a.student_id = {$table}.user_id
                             AND a.status IN ('pending','confirmed')
                             AND a.created_at >= {$table}.created_at
                        )
                        OR EXISTS (
                          SELECT 1 FROM {$apptTbl} a2
                           WHERE a2.student_id = {$table}.user_id
                             AND a2.status = 'completed'
                             AND a2.updated_at >= {$table}.created_at
                        )
                      ) THEN 0 ELSE 1
                    END ASC
                ")
                ->orderByDesc("{$table}.created_at");
                break;

            case 'newest':
            default:
                $query->orderBy("{$table}.created_at", 'desc');
                break;
        }
    }
}
