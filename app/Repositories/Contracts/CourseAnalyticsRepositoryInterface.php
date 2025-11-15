<?php

namespace App\Repositories\Contracts;

interface CourseAnalyticsRepositoryInterface
{
    /**
     * List course summaries (for the index page)
     *
     * @param string $yearKey  'all' | '1' | '2' | '3' | '4'
     * @param string $search   free text or course code
     */
    public function listCourses(string $yearKey = 'all', string $search = '');

    /**
     * Find one course summary (for the show page)
     */
    public function findCourseWithBreakdown(int $id);
}
