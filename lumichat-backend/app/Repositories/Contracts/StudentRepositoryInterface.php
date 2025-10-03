<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StudentRepositoryInterface
{
    public function all(): Collection;

    /**
     * Supported filters:
     * - q: string search (name, email, contact_number, course, year_level)
     * - year: string|int year_level exact match
     */
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findById(int $id, array $with = []): ?object;

    public function create(array $data): object;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    /** Distinct year levels for dropdowns. */
    public function distinctYearLevels(): array;
}
