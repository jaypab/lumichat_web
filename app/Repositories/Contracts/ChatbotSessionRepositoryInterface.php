<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ChatbotSessionRepositoryInterface
{
    public function all(): Collection;
    public function findById(int $id, array $with = []): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    /** Add $sort */
    public function paginateWithFilters(string $q = '', string $dateKey = 'all', int $perPage = 10, string $sort = 'newest'): LengthAwarePaginator;

    /** Add $sort (for export) */
    public function allWithFilters(string $q = '', string $dateKey = 'all', string $sort = 'newest'): Collection;

    public function findWithOrderedChats(int $id): ?object;
    public function perDayCountsForUser(int $userId, string $fromDate, string $toDate): array;
    public function getUserIdBySessionId(int $sessionId): ?int;
}
