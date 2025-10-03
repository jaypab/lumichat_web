<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CounselorLogRepositoryInterface
{
    /** Standardized CRUD (for consistency across repos) */
    public function all(): Collection;
    public function findById(int $id): ?object;
    public function create(array $data): object;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;

    /** Dropdown list of counselors: id + full_name */
    public function listCounselors(): Collection;

    /** Distinct years from appointments (DESC) for the filter dropdown */
    public function availableYears(): Collection;

    /**
     * Paginated grouped rows (counselor x month x year) with:
     *  - counselor_id, counselor_name, month_num, year_num, month_year,
     *  - students_count,
     *  - students_list (pipe-separated, already ordered),
     *  - common_dx (top diagnosis for that (counselor,month,year))
     *
     * @param  array{month:?int, year:?int, counselor_id:?int, per_page?:int} $filters
     */
    public function paginateLogs(array $filters = []): LengthAwarePaginator;

    /**
     * Drilldown data for one counselor + month + year:
     *  - counselor: {id, full_name}
     *  - students:  collection of {appointment_id, student_name, scheduled_at_fmt, diagnosis_result}
     *  - dxCounts:  collection of {diagnosis_result, cnt} ordered desc
     *
     * @return array{counselor:object, students:Collection, dxCounts:Collection}
     */
    public function counselorMonthDetail(int $counselorId, int $month, int $year): array;
}
