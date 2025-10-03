<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    public function all(): Collection;

    /** List with LEFT JOINs + aliases (student_name, counselor_name, …) */
    public function paginateWithNames(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /** Find a single appointment with joined names/emails/phones for show page */
    public function findDetailedById(int $id): ?object;

    public function findById(int $id, array $with = []): ?object;

    public function create(array $data): object;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    /** Earliest appointment year for a given student (or null). */
    public function firstAppointmentYearForStudent(int $studentId): ?int;

    /** [monthNumber => count] for a student/year */
    public function monthlyCountsForStudent(int $studentId, int $year): array;

    /** Status distribution with same filters as index */
    public function statsByStatus(array $filters = []): array;

    /**
     * Save final report for a COMPLETED appointment.
     * Handles:
     *  - validation of current status
     *  - insert into tbl_diagnosis_reports
     *  - update finalized_by/at/final_note on tbl_appointments
     * Returns: ['ok'=>true] or ['ok'=>false,'reason'=>'...']
     */
    public function saveFinalReport(int $appointmentId, string $diagnosis, ?string $finalNote, int $finalizedBy): array;

    /**
     * Update status via action ('confirm' → confirmed, 'done' → completed)
     * Enforces the same business rules you had (must be confirmed before done, not before start time).
     * Returns: ['ok'=>true] or ['ok'=>false,'reason'=>'...']
     */
    public function updateStatusByAction(int $appointmentId, string $action): array;
}
