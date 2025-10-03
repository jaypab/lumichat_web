<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CounselorRepositoryInterface
{
    /** List all counselors (usually for dropdowns). */
    public function all(): Collection;

    /**
     * Paginate counselors with optional filters.
     * Supported filters:
     * - q: string (search name/email/employee_no)
     * - active: bool|null
     * - with_availabilities_ordered: bool (include relation ordered by weekday,start_time)
     */
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /** Find by id, optionally eager loading relations. */
    public function findById(int $id, array $with = []): ?object;

    /**
     * Create counselor. If $data['availability'] is present (array of slots),
     * the repository will persist those as well inside a transaction.
     */
    public function create(array $data): object;

    /**
     * Update counselor. If 'availability' key is present, the repository will
     * REPLACE all availability slots with the provided ones (transaction).
     */
    public function update(int $id, array $data): bool;

    /** Delete counselor (and cascade slots as defined in your model/db). */
    public function delete(int $id): bool;
}
