<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Interfaces
use App\Repositories\Contracts\CounselorRepositoryInterface;
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface; 
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use App\Repositories\Contracts\CounselorLogRepositoryInterface; 
use App\Repositories\Contracts\DiagnosisReportRepositoryInterface;
use App\Repositories\Contracts\DashboardRepositoryInterface;


// Implementations
use App\Repositories\Eloquent\CounselorRepository;
use App\Repositories\Eloquent\StudentRepository;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\CourseAnalyticsRepository;
use App\Repositories\Eloquent\ChatbotSessionRepository;
use App\Repositories\Eloquent\CounselorLogRepository;
use App\Repositories\Eloquent\DiagnosisReportRepository;
use App\Repositories\Eloquent\DashboardRepository;


class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CounselorRepositoryInterface::class,        CounselorRepository::class);
        $this->app->bind(StudentRepositoryInterface::class,          StudentRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class,      AppointmentRepository::class);
        $this->app->bind(CourseAnalyticsRepositoryInterface::class,  CourseAnalyticsRepository::class); 
        $this->app->bind(ChatbotSessionRepositoryInterface::class,   ChatbotSessionRepository::class);
        $this->app->bind(CounselorLogRepositoryInterface::class,     CounselorLogRepository::class);
        $this->app->bind(DiagnosisReportRepositoryInterface::class,  DiagnosisReportRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class,        DashboardRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
