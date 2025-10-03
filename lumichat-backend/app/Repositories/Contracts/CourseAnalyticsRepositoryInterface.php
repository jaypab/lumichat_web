<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CourseAnalyticsRepositoryInterface
{
    /**
     * List rows from tbl_course_analytics with the same filters you use in the controller:
     * - $yearKey: "all" | "1" | "2" | "3" | "4" (filters year_level loosely: 1st/2nd/3rd/4th)
     * - $q: search text for course / year_level / common_diagnosis
     *
     * Returns a Collection of stdClass objects with:
     *   id, course, year_level, student_count (int), common_diagnoses (array<string>)
     */
    public function listCourses(string $yearKey = 'all', string $q = ''): Collection;

    /**
     * Find ONE course analytics row by id and build the same object you render in show():
     *   (object){ course, year_level, student_count, breakdown: array{label,count}[] , notes:null }
     * Returns null when not found.
     */
    public function findCourseWithBreakdown(int $id, int $limit = 20): ?object;

    /**
     * Keep this for Appointments → when saving a diagnosis, refresh analytics row for
     * the student’s course/year.
     */
    public function refreshForStudent(int $studentId): void;
}
