<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SelfAssessmentController;

/*
|--------------------------------------------------------------------------
| Web Routes (Student-facing)
|--------------------------------------------------------------------------
*/

// Landing → Chat
Route::get('/', fn () => redirect()->route('chat.index'))->name('home');

// Public pages
Route::view('/privacy-policy', 'privacy-policy')->name('privacy.policy');

/*
|--------------------------------------------------------------------------
| Registration (guest only)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Authenticated user area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /* ----------------------------- Chat ------------------------------ */
    Route::get('/chat',                 [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/new',             [ChatController::class, 'newChat'])->name('chat.new');
    Route::get('/chat/history',         [ChatController::class, 'history'])->name('chat.history');
    Route::post('/chat',                [ChatController::class, 'store'])
         ->middleware('throttle:chat-send')
         ->name('chat.store');
    Route::get('/chat/view/{id}',       [ChatController::class, 'viewSession'])->name('chat.view');
    Route::delete('/chat/session/{id}', [ChatController::class, 'deleteSession'])->name('chat.deleteSession');
    Route::delete('/chat/bulk-delete',  [ChatController::class, 'bulkDelete'])->name('chat.bulkDelete');
    Route::post('/chat/activate/{id}',  [ChatController::class, 'activate'])->name('chat.activate');

    /* ---------------------------- Profile ---------------------------- */
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile',      [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile',   [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* -------------------------- Appointment -------------------------- */
    // Unified entrypoint (used by the sidebar): decides view based on whether the user has appointments
    Route::get('/appointment', [AppointmentController::class, 'entrypoint'])->name('appointment.index');
    // routes/web.php (or your student routes file)
    Route::middleware(['auth'])->group(function () {
        // ... your existing routes
    Route::get('/api/appointments/unseen', [\App\Http\Controllers\AppointmentController::class, 'unseenCount'])
        ->name('appointments.unseen');
    });

    // Explicit pages (use these for buttons/links)
    Route::get('/appointment/book',     [AppointmentController::class, 'index'])->name('appointment.create');   // <-- FORCE booking form
    Route::get('/appointment/history',  [AppointmentController::class, 'history'])->name('appointment.history'); // <-- FORCE history
    Route::middleware('auth')->group(function () {
    Route::get('/appointment/history/export/pdf', [AppointmentController::class, 'exportHistoryPdf'])
        ->name('appointment.history.export.pdf');
    Route::get('/appointment/{id}/export/pdf', [\App\Http\Controllers\AppointmentController::class, 'exportShowPdf'])
        ->whereNumber('id')
        ->name('appointment.show.export.pdf');
});
    // Actions / APIs
    Route::post('/appointment',                  [AppointmentController::class, 'store'])->name('appointment.store');
    Route::get('/appointment/slots', [\App\Http\Controllers\AppointmentController::class, 'slots'])
    ->name('appointment.slots');
    Route::get('/appointment/slots-pooled', [\App\Http\Controllers\AppointmentController::class, 'slotsPooled'])
    ->name('appointment.slots.pooled');
    Route::get('/appointment/view/{id}',         [AppointmentController::class, 'show'])->name('appointment.view');
    Route::patch('/appointment/{id}/cancel',     [AppointmentController::class, 'cancel'])->name('appointment.cancel');

    /* ------------------------ Self-Assessment ------------------------ */
    Route::get('/self-assessment',        [SelfAssessmentController::class,'create'])->name('self-assessment.create');
    Route::post('/self-assessment/store', [SelfAssessmentController::class,'store'])->name('self-assessment.store');
    Route::get('/self-assessment/skip',   [SelfAssessmentController::class,'skip'])->name('self-assessment.skip');

    /* ---------------------- Feature toggle (signed) ------------------ */
    Route::get('/features/enable-appointment', [FeaturesController::class, 'enableAppointment'])
        ->name('features.enable_appointment')
        ->middleware('signed');
});

/*
|--------------------------------------------------------------------------
| Settings (if you require verification)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings',  [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
Route::view('/support/contact', 'support.contact')->name('support.contact');
Route::view('/support/bug', 'support.bug')->name('support.bug');
/*
|--------------------------------------------------------------------------
| Auth scaffolding (login, logout, password reset, etc.)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
require __DIR__.'/admin.php'; 
