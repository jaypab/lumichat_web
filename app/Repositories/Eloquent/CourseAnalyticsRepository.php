<?php

namespace App\Repositories\Eloquent;

use App\Models\CourseAnalytics;
use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CourseAnalyticsRepository implements CourseAnalyticsRepositoryInterface
{
    /**
     * INDEX: list course summaries
     */
    public function listCourses(string $yearKey = 'all', string $search = ''): LengthAwarePaginator|Collection
    {
        $query = CourseAnalytics::query()
            ->orderBy('course')
            ->orderBy('year_level');

        if ($yearKey !== 'all') {
            $query->where('year_level', $yearKey);
        }

        if ($search !== '') {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('course', 'LIKE', "%{$search}%")
                  ->orWhere('common_diagnoses', 'LIKE', "%{$search}%");
            });
        }

        // Your Blade already supports both paginator or collection; paginate for convenience.
        return $query->paginate(25);
    }

    /**
     * SHOW: single course/year + breakdown
     */
    public function findCourseWithBreakdown(int $id)
    {
        return CourseAnalytics::find($id);
    }
}
