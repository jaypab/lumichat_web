<?php

use Illuminate\Support\Facades\Route;

// Auth controller for admin login form
use App\Http\Controllers\Auth\AuthenticatedSessionController;

// Admin controllers
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\AccountRequestController;
use App\Http\Controllers\Admin\CounselorController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\ChatbotSessionController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\SelfAssessmentController;
use App\Http\Controllers\Admin\CounselorLogController;
use App\Http\Controllers\Admin\CourseAnalyticsController;
use App\Http\Controllers\Admin\CaseNoteController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AnnouncementController;

// ---------------------- Public (guest) admin auth routes ----------------------
Route::prefix('admin')->name('admin.')->middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.post');
});

// ---------------------- Protected admin routes ----------------------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    // DASHBOARD
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // COUNSELORS
    Route::resource('counselors', CounselorController::class)
        ->parameters(['counselors' => 'counselor']);

    // STUDENTS
    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('/students',        [StudentController::class, 'store'])->name('students.store');
    Route::resource('students', StudentController::class)
        ->only(['index', 'show'])
        ->parameters(['students' => 'student']);

    Route::get('/students/export/pdf', [StudentController::class, 'exportPdf'])->name('students.export.pdf');
    Route::get('/students/{student}/export/pdf', [StudentController::class, 'exportShowPdf'])
        ->whereNumber('student')->name('students.show.export.pdf');

    // ACCOUNT REQUESTS
    Route::get('/account-requests', [AccountRequestController::class, 'index'])->name('account-requests.index');
    Route::get('/account-requests/live-status', [AccountRequestController::class, 'liveStatus'])->name('account-requests.live-status');
    Route::get('/account-requests/{accountRequest}', [AccountRequestController::class, 'show'])->name('account-requests.show');
    Route::post('/account-requests/{accountRequest}/approve', [AccountRequestController::class, 'approve'])->name('account-requests.approve');
    Route::post('/account-requests/{accountRequest}/reject', [AccountRequestController::class, 'reject'])->name('account-requests.reject');

    // RE-AUTH / SENSITIVE
    Route::post('reauth/confirm', [ChatbotSessionController::class, 'confirmPasswordAjax'])->name('reauth.confirm');
    Route::post('reauth/confirm-sensitive', [ChatbotSessionController::class, 'confirmSensitiveAjax'])->name('reauth.confirm_sensitive');

    Route::get('chatbot-sessions/{session}/sensitive', [ChatbotSessionController::class, 'sensitiveDetails'])
        ->whereNumber('session')->name('chatbot-sessions.sensitive');

    // CHATBOT SESSIONS
    Route::get('chatbot-sessions/export/pdf', [ChatbotSessionController::class, 'exportPdf'])->name('chatbot-sessions.export.pdf');
    Route::get('chatbot-sessions/{session}/calendar', [ChatbotSessionController::class, 'calendarCounts'])
        ->whereNumber('session')->name('chatbot-sessions.calendar');
    Route::get('chatbot-sessions/{session}/slots', [ChatbotSessionController::class, 'slots'])
        ->whereNumber('session')->name('chatbot-sessions.slots');
    Route::post('chatbot-sessions/{session}/book', [ChatbotSessionController::class, 'book'])
        ->whereNumber('session')->name('chatbot-sessions.book');
    Route::post('chatbot-sessions/{session}/reschedule', [ChatbotSessionController::class, 'reschedule'])
        ->whereNumber('session')->name('chatbot-sessions.reschedule');
    Route::get('chatbot-sessions/{session}/pdf', [ChatbotSessionController::class, 'exportOne'])
        ->whereNumber('session')->name('chatbot-sessions.pdf');
    Route::get('chatbot-sessions/{session}/high-risk-all', [ChatbotSessionController::class, 'highRiskAll'])
        ->whereNumber('session')->name('chatbot-sessions.highrisk_all');

    // --- Risk tagging (admin) ---
    Route::post('chatbot-sessions/{session}/set-risk', [ChatbotSessionController::class, 'setRisk'])
        ->whereNumber('session')->name('chatbot-sessions.setRisk');

    Route::get('chatbot-sessions/{session}/risk-history', [ChatbotSessionController::class, 'riskHistory'])
        ->whereNumber('session')->name('chatbot-sessions.riskHistory');

    Route::resource('chatbot-sessions', ChatbotSessionController::class)
        ->only(['index', 'show'])
        ->parameters(['chatbot-sessions' => 'session'])
        ->where(['session' => '[0-9]+']);

    // APPOINTMENTS
    Route::get('/appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/poll', [AdminAppointmentController::class, 'poll'])->name('appointments.poll');
    Route::get('/appointments/{id}', [AdminAppointmentController::class, 'show'])->whereNumber('id')->name('appointments.show');
    Route::get('/appointments/export/pdf', [AdminAppointmentController::class, 'exportPdf'])->name('appointments.export.pdf');
    Route::get('/appointments/{id}/export/pdf', [AdminAppointmentController::class, 'exportShowPdf'])->whereNumber('id')->name('appointments.export.show.pdf');
    Route::get('/appointments/{id}/assign', [AdminAppointmentController::class, 'assignForm'])->whereNumber('id')->name('appointments.assign.form');
    Route::patch('/appointments/{id}/assign', [AdminAppointmentController::class, 'assign'])->whereNumber('id')->name('appointments.assign');

    Route::match(['GET', 'POST'], '/appointments/{id}/change-request/{action}',
        [AdminAppointmentController::class, 'handleChangeRequest']
    )->whereNumber('id')->whereIn('action', ['approve','decline'])
     ->name('appointments.change_request.handle');

    // COUNSELOR LOGS
    Route::get('/counselor-logs', [CounselorLogController::class, 'index'])->name('counselor-logs.index');
    Route::get('/counselor-logs/{counselor}', [CounselorLogController::class, 'show'])->whereNumber('counselor')->name('counselor-logs.show');
    Route::get('/counselor-logs/export/pdf', [CounselorLogController::class, 'exportPdf'])->name('counselor-logs.export.pdf');
    Route::get('counselor-logs/{counselor}/export', [CounselorLogController::class, 'exportShowPdf'])->whereNumber('counselor')->name('counselor-logs.show.export');

    // CASE FORM SUMMARY
    Route::resource('case-notes', CaseNoteController::class)
        ->only(['index', 'show'])
        ->parameters(['case-notes' => 'note']);
    Route::get('/case-notes/export/pdf', [CaseNoteController::class, 'exportPdf'])->name('case-notes.export.pdf');
    Route::get('/case-notes/{note}/export/pdf', [CaseNoteController::class, 'exportOne'])->whereNumber('note')->name('case-notes.show.export.pdf');

    // COURSE ANALYTICS
    Route::get('course-analytics', [CourseAnalyticsController::class, 'index'])->name('course-analytics.index');
    Route::get('course-analytics/{course}', [CourseAnalyticsController::class, 'show'])->whereNumber('course')->name('course-analytics.show');
    Route::get('course-analytics/export/pdf', [CourseAnalyticsController::class, 'exportPdf'])->name('course-analytics.export.pdf');
    Route::get('course-analytics/{course}/export/pdf', [CourseAnalyticsController::class, 'exportShowPdf'])->whereNumber('course')->name('course-analytics.show.export.pdf');

    // ===================== ADMIN NOTIFICATIONS (no nested admin prefix!) =====================
    Route::get('/notifications',            [AdminNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed',       [AdminNotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/{id}/mark', [AdminNotificationController::class, 'mark'])->name('notifications.mark');
    Route::post('/notifications/mark-all',  [AdminNotificationController::class, 'markAll'])->name('notifications.mark_all');

    // ANNOUNCEMENTS
    Route::post('announcements/bulk-delete', [AnnouncementController::class, 'bulkDelete'])->name('announcements.bulk-delete');
    Route::resource('announcements', AnnouncementController::class)
        ->parameters(['announcements' => 'announcement']);
});
