<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorAvailabilityController;
use App\Http\Controllers\Counselor\HighriskReviewController;
use App\Http\Controllers\Counselor\AppointmentController as CounselorAppointmentController;

Route::middleware(['auth', 'counselor'])
    ->prefix('counselor')
    ->name('counselor.')
    ->group(function () {

        Route::get('/', [CounselorDashboardController::class, 'index'])->name('dashboard');

        // Availability UI
        Route::get('/availability',  [CounselorAvailabilityController::class, 'index'])
            ->name('availability.index');

        // One-off create
        Route::post('/availability', [CounselorAvailabilityController::class, 'store'])
            ->name('availability.store');

        // Bulk create (dated)
        Route::post('/availability/bulk', [CounselorAvailabilityController::class, 'bulk'])
            ->name('availability.bulk');

        // Replace recurring weekday BLOCKS (AJAX from modal / disable)
        Route::post('/availability/weekday-blocks', [CounselorAvailabilityController::class, 'weekdayBlocks'])
            ->name('availability.weekdayBlocks');

        // NEW: Dated blocks API (open modal from calendar)
        Route::get('/availability/date-blocks',  [CounselorAvailabilityController::class, 'getDateBlocks'])
            ->name('availability.dateBlocks.get');
        Route::post('/availability/date-blocks', [CounselorAvailabilityController::class, 'saveDateBlocks'])
            ->name('availability.dateBlocks.save');
        Route::post('/availability/date-windows', [CounselorAvailabilityController::class, 'saveDateWindows'])
            ->name('availability.dateWindows.save');


        // Old single delete (kept)
        Route::delete('/availability/{id}', [CounselorAvailabilityController::class, 'destroy'])
            ->whereNumber('id')->name('availability.destroy');

        // Bulk delete (kept)
        Route::delete('/availability', [CounselorAvailabilityController::class, 'bulkDestroy'])
            ->name('availability.bulkDestroy');

        // Edit/update (kept)
        Route::get('/availability/{id}/edit', [CounselorAvailabilityController::class, 'edit'])
            ->whereNumber('id')->name('availability.edit');

        Route::put('/availability/{id}', [CounselorAvailabilityController::class, 'update'])
            ->whereNumber('id')->name('availability.update');

        // High-risk reviews
        Route::get('/highrisk',      [HighriskReviewController::class, 'index'])->name('highrisk.index');
        Route::get('/highrisk/{id}', [HighriskReviewController::class, 'show'])->whereNumber('id')->name('highrisk.show');
        Route::put('/highrisk/{id}', [HighriskReviewController::class, 'update'])->whereNumber('id')->name('highrisk.update');

        // Appointments
        Route::get('/appointments',        [CounselorAppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{id}',   [CounselorAppointmentController::class, 'show'])->whereNumber('id')->name('appointments.show');
        Route::patch('/appointments/{id}/status', [CounselorAppointmentController::class, 'status'])
            ->whereNumber('id')->name('appointments.status');
        Route::post('/appointments/{id}/report', [CounselorAppointmentController::class, 'saveReport'])
            ->whereNumber('id')->name('appointments.report');
        Route::get('/appointments/{id}/follow-up', [CounselorAppointmentController::class, 'followUpForm'])
            ->whereNumber('id')->name('appointments.follow.form');
        Route::post('/appointments/{id}/follow-up', [CounselorAppointmentController::class, 'followUpStore'])
            ->whereNumber('id')->name('appointments.follow.store');
        Route::post('/appointments/{id}/no-show', [CounselorAppointmentController::class, 'markNoShow'])
            ->whereNumber('id')->name('appointments.no_show');
        Route::get('/appointments/{id}/export/pdf', [CounselorAppointmentController::class, 'exportShowPdf'])
            ->whereNumber('id')->name('appointments.export.show.pdf');
    });

// Student slots API
Route::middleware(['auth'])
    ->get('/api/counselors/{id}/slots', [CounselorAvailabilityController::class, 'slots'])
    ->whereNumber('id')->name('api.counselors.slots');
