<?php

namespace App\Repositories\Eloquent;

use App\Models\ChatSession;
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChatbotSessionRepository implements ChatbotSessionRepositoryInterface
{
    /** -------- CRUD (standardized) -------- */

    public function all(): Collection
    {
        return ChatSession::with('user')
            ->orderByRaw("
                CASE LOWER(COALESCE(risk_level,'')) 
                    WHEN 'high' THEN 0 
                    WHEN 'moderate' THEN 1 
                    WHEN 'low' THEN 2 
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

    public function paginateWithFilters(string $q = '', string $dateKey = 'all', int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->baseFilteredQuery($q, $dateKey);

        return $query
            ->orderByRaw("
                CASE LOWER(COALESCE(risk_level,'')) 
                    WHEN 'high' THEN 0 
                    WHEN 'moderate' THEN 1 
                    WHEN 'low' THEN 2 
                    ELSE 3 
                END
            ")
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** -------- Used by PDF export (same filters, no pagination) -------- */

    public function allWithFilters(string $q = '', string $dateKey = 'all'): Collection
    {
        return $this->baseFilteredQuery($q, $dateKey)
            ->orderByRaw("
                CASE LOWER(COALESCE(risk_level,'')) 
                    WHEN 'high' THEN 0 
                    WHEN 'moderate' THEN 1 
                    WHEN 'low' THEN 2 
                    ELSE 3 
                END
            ")
            ->orderByDesc('created_at')
            ->get();
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
        $query = ChatSession::query()->with('user');

        // free text search across id, topic_summary, user name/email
        $q = trim($q);
        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function (Builder $sub) use ($like, $q) {
                // numeric id search (keeps your existing behavior but stricter)
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
}
