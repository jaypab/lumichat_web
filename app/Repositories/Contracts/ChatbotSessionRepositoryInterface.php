<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ChatbotSessionRepositoryInterface
{
    /** Basic CRUD */
    public function all(): Collection;
    public function findById(int $id, array $with = []): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    /** List with search + date filters + pagination for the Admin index */
    public function paginateWithFilters(string $q = '', string $dateKey = 'all', int $perPage = 10): LengthAwarePaginator;

    /** Same filters w/o pagination (used by PDF export) */
    public function allWithFilters(string $q = '', string $dateKey = 'all'): Collection;

    /** One session with user + chats ordered oldest→newest for the Admin show page */
    public function findWithOrderedChats(int $id): ?object;

    /** Used by calendarCounts() → per-day counts for a user between two dates (YYYY-MM-DD) */
    public function perDayCountsForUser(int $userId, string $fromDate, string $toDate): array;

    /** Convenience: fetch the user_id for a given session id */
    public function getUserIdBySessionId(int $sessionId): ?int;
}
