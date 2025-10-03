<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DiagnosisReportRepositoryInterface
{
    /** Baseline CRUD for consistency across repos */
    public function all(): Collection;
    public function findById(int $id, array $with = []): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    /**
     * Admin list with filters (dateKey: all|7d|30d|month) and free-text search.
     */
    public function paginateWithFilters(string $dateKey = 'all', string $q = '', int $perPage = 10): LengthAwarePaginator;

    /**
     * One report with relations for the show page.
     */
    public function findWithRelations(int $id, array $with): ?object;
}
