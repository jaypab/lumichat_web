<?php

use Illuminate\Support\Facades\Route;


// Auth controller for admin login form (reuse your auth controller)
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Admin controllers
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CounselorController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\ChatbotSessionController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\SelfAssessmentController;
use App\Http\Controllers\Admin\DiagnosisReportController;
use App\Http\Controllers\Admin\CounselorLogController;
use App\Http\Controllers\Admin\CourseAnalyticsController;


/*
|--------------------------------------------------------------------------
| Public (guest) admin auth routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.post');
});

/*
|--------------------------------------------------------------------------
| Protected admin routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    /* DASHBOARD */
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    /* COUNSELORS */
    Route::resource('counselors', CounselorController::class)
        ->parameters(['counselors' => 'counselor']);

    /* STUDENTS */
    Route::resource('students', StudentController::class)->only(['index','show'])
        ->parameters(['students' => 'student']);
    Route::get('/students/export/pdf', [StudentController::class, 'exportPdf'])->name('students.export.pdf');
    
    // Single-student PDF (details page)
    Route::get('/students/{student}/export/pdf',
        [StudentController::class, 'exportShowPdf']
    )->whereNumber('student')->name('students.show.export.pdf');

    /* CHATBOT SESSIONS (custom routes first) */
    Route::get('chatbot-sessions/export/pdf',
        [ChatbotSessionController::class, 'exportPdf']
    )->name('chatbot-sessions.export.pdf');

    Route::get('chatbot-sessions/{session}/calendar',
        [ChatbotSessionController::class, 'calendarCounts']
    )->whereNumber('session')->name('chatbot-sessions.calendar');

    Route::get('chatbot-sessions/{session}/slots',
        [ChatbotSessionController::class, 'slots']
    )->whereNumber('session')->name('chatbot-sessions.slots');

    Route::post('chatbot-sessions/{session}/book',
        [ChatbotSessionController::class, 'book']
    )->whereNumber('session')->name('chatbot-sessions.book');

    Route::post('chatbot-sessions/{session}/reschedule',
        [ChatbotSessionController::class, 'reschedule']
    )->whereNumber('session')->name('chatbot-sessions.reschedule');

    Route::get('chatbot-sessions/{session}/pdf',
        [ChatbotSessionController::class, 'exportOne']
    )->whereNumber('session')->name('chatbot-sessions.pdf');

    /* Resource (index/show) AFTER the custom ones */
    Route::resource('chatbot-sessions', ChatbotSessionController::class)
        ->only(['index','show'])
        ->parameters(['chatbot-sessions' => 'session'])
        ->where(['session' => '[0-9]+']); // helps prevent 'export' being treated as {session}


   /* APPOINTMENTS (Admin) */

    // Put capacity BEFORE the {id} routes (or keep it here and also add whereNumber below)
    Route::get('/appointments/capacity', [AdminAppointmentController::class, 'capacity'])
        ->name('appointments.capacity');

    Route::get('/appointments', [AdminAppointmentController::class, 'index'])
        ->name('appointments.index');

    Route::get('/appointments/{id}', [AdminAppointmentController::class, 'show'])
        ->whereNumber('id')->name('appointments.show');

    Route::get('/appointments/{id}/assign', [AdminAppointmentController::class, 'assignForm'])
        ->whereNumber('id')->name('appointments.assign.form');

    Route::patch('/appointments/{id}/assign', [AdminAppointmentController::class, 'assign'])
        ->whereNumber('id')->name('appointments.assign');

    Route::patch('/appointments/{id}/status', [AdminAppointmentController::class, 'updateStatus'])
        ->whereNumber('id')->name('appointments.status');

    Route::post('/appointments/{id}/report', [AdminAppointmentController::class, 'saveReport'])
        ->whereNumber('id')->name('appointments.report');

    Route::get('/appointments/{id}/export/pdf', [AdminAppointmentController::class, 'exportShowPdf'])
        ->whereNumber('id')->name('appointments.export.show.pdf');

    Route::get('/appointments/{id}/follow-up', [AdminAppointmentController::class, 'followUpForm'])
        ->whereNumber('id')->name('appointments.follow.form');

    Route::post('/appointments/{id}/follow-up', [AdminAppointmentController::class, 'followUpStore'])
        ->whereNumber('id')->name('appointments.follow.store');

    Route::get('/appointments/export/pdf', [AdminAppointmentController::class, 'exportPdf'])
        ->name('appointments.export.pdf');

    /* COUNSELOR LOGS */
    Route::get('/counselor-logs', [CounselorLogController::class, 'index'])->name('counselor-logs.index');
    Route::get('/counselor-logs/{counselor}', [CounselorLogController::class, 'show'])
        ->whereNumber('counselor')->name('counselor-logs.show');
    Route::get('/counselor-logs/export/pdf', [CounselorLogController::class, 'exportPdf'])
        ->name('counselor-logs.export.pdf');
     // Counselor Logs (list export already exists)
    Route::get('counselor-logs/export', [\App\Http\Controllers\Admin\CounselorLogController::class, 'exportPdf'])
        ->name('counselor-logs.export');

    // ✅ NEW: export the single counselor/month view
    Route::get('counselor-logs/{counselor}/export', [\App\Http\Controllers\Admin\CounselorLogController::class, 'exportShowPdf'])
        ->name('counselor-logs.show.export');


    /* DIAGNOSIS REPORTS */
    Route::resource('diagnosis-reports', DiagnosisReportController::class)
        ->only(['index','show'])
        ->parameters(['diagnosis-reports' => 'report']);

    Route::get('/diagnosis-reports/export/pdf',
        [DiagnosisReportController::class, 'exportPdf']
    )->name('diagnosis-reports.export.pdf');

    /* ✅ single report direct download for the show page */
    Route::get('/diagnosis-reports/{report}/export/pdf',
        [DiagnosisReportController::class, 'exportOne']
    )->whereNumber('report')->name('diagnosis-reports.show.export.pdf');

    /* COURSE ANALYTICS */
    Route::get('course-analytics', [CourseAnalyticsController::class, 'index'])->name('course-analytics.index');
    Route::get('course-analytics/{course}', [CourseAnalyticsController::class, 'show'])
        ->whereNumber('course')->name('course-analytics.show');
    Route::get('course-analytics/export/pdf', [CourseAnalyticsController::class, 'exportPdf'])
        ->name('course-analytics.export.pdf');
    Route::get('course-analytics/{course}/export/pdf', [CourseAnalyticsController::class, 'exportShowPdf'])
        ->whereNumber('course')->name('course-analytics.show.export.pdf');
});
