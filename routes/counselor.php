<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorAvailabilityController;
use App\Http\Controllers\Counselor\HighriskReviewController;
use App\Http\Controllers\Counselor\AppointmentController as CounselorAppointmentController;

// Middleware note: Replace 'auth' and 'counselor' with your actual guards / gates.
Route::middleware(['auth', 'counselor'])->prefix('counselor')->name('counselor.')->group(function () {
    Route::get('/', [CounselorDashboardController::class, 'index'])->name('dashboard');

    Route::get('/availability',  [CounselorAvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability', [CounselorAvailabilityController::class, 'store'])->name('availability.store');
    Route::delete('/availability/{id}', [CounselorAvailabilityController::class, 'destroy'])->name('availability.destroy');

    Route::get('/highrisk',     [HighriskReviewController::class, 'index'])->name('highrisk.index');
    Route::get('/highrisk/{id}',[HighriskReviewController::class, 'show'])->name('highrisk.show');
    Route::put('/highrisk/{id}',[HighriskReviewController::class, 'update'])->name('highrisk.update');

    Route::get('/appointments',        [CounselorAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{id}',   [CounselorAppointmentController::class, 'show'])->whereNumber('id')->name('appointments.show');

    // After admin assigns, counselor manages:
    Route::patch('/appointments/{id}/status',
        [CounselorAppointmentController::class, 'updateStatus']
    )->whereNumber('id')->name('appointments.status');

    Route::post('/appointments/{id}/report',
        [CounselorAppointmentController::class, 'saveReport']
    )->whereNumber('id')->name('appointments.report');

    Route::get('/appointments/{id}/follow-up',
        [CounselorAppointmentController::class, 'followUpForm']
    )->whereNumber('id')->name('appointments.follow.form');

    Route::post('/appointments/{id}/follow-up',
        [CounselorAppointmentController::class, 'followUpStore']
    )->whereNumber('id')->name('appointments.follow.store');

    // already present in your routes: mark as no-show
    // Route::post('/appointments/{id}/no-show', ...)->name('appointments.no_show');

    // optional: counselor-side single PDF
    Route::get('/appointments/{id}/export/pdf',
        [CounselorAppointmentController::class, 'exportShowPdf']
    )->whereNumber('id')->name('appointments.export.show.pdf');



    // ✅ NEW: mark as No-Show (POST)
    Route::post('/appointments/{id}/no-show',
        [CounselorAppointmentController::class, 'markNoShow']
    )->whereNumber('id')->name('appointments.no_show');
});

// JSON endpoint that the student-side booking page can call to AUTO-UPDATE slots
Route::middleware(['auth'])->get('/api/counselors/{id}/slots', [CounselorAvailabilityController::class, 'slots'])
    ->name('api.counselors.slots');
