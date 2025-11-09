<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorAvailabilityController;
use App\Http\Controllers\Counselor\AppointmentController as CounselorAppointmentController;
use App\Http\Controllers\NotificationController;
Route::middleware(['auth','counselor'])
  ->prefix('counselor')
  ->name('counselor.')
  ->group(function () {

    // Dashboard
    Route::get('/', [CounselorDashboardController::class,'index'])->name('dashboard');

    // Availability
    Route::post('/availability/accepting', [CounselorAvailabilityController::class,'setAccepting'])->name('availability.accepting');
    Route::get('/availability',  [CounselorAvailabilityController::class,'index'])->name('availability.index');
    Route::post('/availability', [CounselorAvailabilityController::class,'store'])->name('availability.store');
    Route::post('/availability/bulk', [CounselorAvailabilityController::class,'bulk'])->name('availability.bulk');
    Route::post('/availability/weekday-blocks', [CounselorAvailabilityController::class,'weekdayBlocks'])->name('availability.weekdayBlocks');
    Route::post('/availability/weekday-store',  [CounselorAvailabilityController::class,'weekdayStore'])->name('availability.weekdayStore');
    Route::get('/availability/weekday/precheck', [CounselorAvailabilityController::class,'weekdayDisablePrecheck'])->name('availability.weekdayDisable.precheck');
    Route::get('/availability/table', [CounselorAvailabilityController::class,'table'])->name('availability.table');
    Route::get('/availability/date-blocks',  [CounselorAvailabilityController::class,'getDateBlocks'])->name('availability.dateBlocks.get');
    Route::post('/availability/date-blocks', [CounselorAvailabilityController::class,'saveDateBlocks'])->name('availability.dateBlocks.save');
    Route::post('/availability/date-windows', [CounselorAvailabilityController::class,'saveDateWindows'])->name('availability.dateWindows.save');
    Route::get('/availability/{id}/edit', [CounselorAvailabilityController::class,'edit'])->whereNumber('id')->name('availability.edit');
    Route::put('/availability/{id}',       [CounselorAvailabilityController::class,'update'])->whereNumber('id')->name('availability.update');
    Route::delete('/availability/{id}',    [CounselorAvailabilityController::class,'destroy'])->whereNumber('id')->name('availability.destroy');
    Route::match(['POST','DELETE'],'/availability/bulk-destroy', [CounselorAvailabilityController::class,'bulkDestroy'])->name('availability.bulkDestroy');
    Route::match(['POST','DELETE'],'/availability/recurring/bulk-destroy', [CounselorAvailabilityController::class,'bulkDestroyRecurring'])->name('availability.bulkDestroyRecurring');

    // Follow-up (NOTE: {appointment} — implicit model binding)
    Route::get('/appointments/{appointment}/follow-up',       [CounselorAppointmentController::class,'followUpForm'])->whereNumber('appointment')->name('appointments.follow.form');
    Route::post('/appointments/{appointment}/follow-up',      [CounselorAppointmentController::class,'followUpStore'])->whereNumber('appointment')->name('appointments.follow.store');
    Route::get('/appointments/{appointment}/follow-up/slots', [CounselorAppointmentController::class,'followUpSlots'])->whereNumber('appointment')->name('appointments.follow.slots');

    // Appointments
    Route::get('/appointments',                 [CounselorAppointmentController::class,'index'])->name('appointments.index');
    Route::get('/appointments/{id}',            [CounselorAppointmentController::class,'show'])->whereNumber('id')->name('appointments.show');
    Route::patch('/appointments/{id}/status',   [CounselorAppointmentController::class,'status'])->whereNumber('id')->name('appointments.status');
    Route::post('/appointments/{id}/report',    [CounselorAppointmentController::class,'saveReport'])->whereNumber('id')->name('appointments.report');
    Route::post('/appointments/{id}/no-show',   [CounselorAppointmentController::class,'markNoShow'])->whereNumber('id')->name('appointments.no_show');
    Route::get('/appointments/{id}/export/pdf', [CounselorAppointmentController::class,'exportShowPdf'])->whereNumber('id')->name('appointments.export.show.pdf');
    Route::post('/appointments/{id}/case-note', [CounselorAppointmentController::class,'storeCaseNote'])->whereNumber('id')->name('appointments.case_note.store');
    Route::put('/appointments/{id}/case-note',  [CounselorAppointmentController::class,'storeCaseNote'])->whereNumber('id')->name('appointments.case_note.update');
    Route::get('/appointments/{id}/case-note/pdf', [CounselorAppointmentController::class,'caseNotePdf'])->whereNumber('id')->name('appointments.case_note.pdf');

    Route::prefix('notifications')->as('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/feed', [NotificationController::class, 'feed'])->name('feed');
        Route::post('/mark/{id}', [NotificationController::class, 'mark'])->whereUuid('id')->name('mark');   // UUID, not numeric
        Route::post('/mark-all', [NotificationController::class, 'markAll'])->name('mark_all');
    });
});

// (Optional) public slots API you already had:
Route::middleware(['auth'])
  ->get('/api/counselors/{id}/slots', [CounselorAvailabilityController::class,'slots'])
  ->whereNumber('id')->name('api.counselors.slots');
