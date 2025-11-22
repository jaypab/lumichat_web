<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth; // ✅ needed for Auth::check()

use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\AppointmentController;

use App\Http\Controllers\FeaturesController;
use App\Http\Controllers\SelfAssessmentController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\CounselorChangeRequestController;



// Consent page + accept action
// Legal
Route::get('/legal/consent', [LegalController::class, 'consent'])->name('legal.consent');
Route::post('/legal/accept', [LegalController::class, 'accept'])->name('legal.accept');
Route::post('/legal/decline', [LegalController::class, 'decline'])->name('legal.decline');
/*
|--------------------------------------------------------------------------
| Web Routes (Student-facing)
|--------------------------------------------------------------------------
*/
// Mark tutorial as seen
Route::post('/tour/complete', function () {
    if (Auth::check()) {
        Auth::user()->forceFill(['has_seen_tutorial' => true])->save();
    }
    return response()->noContent();
})->name('tour.complete')->middleware('auth');

// Landing → Chat
Route::get('/', fn () => redirect()->route('chat.index'))->name('home');

// Public pages
Route::view('/privacy-policy', 'privacy-policy')->name('privacy.policy');

/*
|--------------------------------------------------------------------------
| ✅ RASA Health Check (for staging / production)
|--------------------------------------------------------------------------
*/
Route::get('/rasa-health', function () {
    $base  = rtrim(env('RASA_PUBLIC_BASE', 'https://bot.lumichat.site'), '/');
    $token = env('RASA_TOKEN', '');

    try {
        $res = Http::timeout(5)->get($base . '/status', ['token' => $token]);

        return response()->json([
            'ok'     => $res->ok(),
            'status' => $res->status(),
            'body'   => $res->json(),
        ], $res->status());
    } catch (\Throwable $e) {
        return response()->json([
            'ok'   => false,
            'error'=> $e->getMessage(),
            'hint' => 'Check systemctl status rasa/rasa-actions and Nginx proxy on the VPS.',
        ], 503);
    }
})->name('rasa.health');


/*
|--------------------------------------------------------------------------
| Authenticated user area
|--------------------------------------------------------------------------
*/
Route::middleware('auth' , 'tos')->group(function () {

    /* ----------------------------- Chat ------------------------------ */
    
    Route::get('/chat',                 [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/new',             [ChatController::class, 'newChat'])->name('chat.new');
    Route::get('/chat/history',         [ChatController::class, 'history'])->name('chat.history');
    Route::post('/chat',                [ChatController::class, 'store'])
         ->middleware('throttle:60,1')
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
    // Unified entrypoint (used by the sidebar)
    Route::get('/appointment', [AppointmentController::class, 'entrypoint'])->name('appointment.index');

    // Appointment unseen count (kept under auth)
    Route::get('/api/appointments/unseen', [AppointmentController::class, 'unseenCount'])
        ->name('appointments.unseen');

    Route::get('/appointment/counselors', [AppointmentController::class,'counselors'])
        ->name('appointment.counselors');

    // Explicit pages (buttons/links)
    Route::get('/appointment/book',     [AppointmentController::class, 'index'])->name('appointment.create');
    Route::get('/appointment/history',  [AppointmentController::class, 'history'])->name('appointment.history');

    Route::get('/appointment/history/export/pdf', [AppointmentController::class, 'exportHistoryPdf'])
        ->name('appointment.history.export.pdf');

    Route::get('/appointment/{id}/export/pdf', [AppointmentController::class, 'exportShowPdf'])
        ->whereNumber('id')
        ->name('appointment.show.export.pdf');

     // Student: request a different counselor
    Route::post('/appointments/{id}/request-change', [AppointmentController::class, 'requestCounselorChange'])
    ->name('appointment.request_change');

    // Actions / APIs
    Route::post('/appointment', [AppointmentController::class, 'store'])->name('appointment.store');
    Route::get('/appointment/slots', [AppointmentController::class, 'slots'])->name('appointment.slots');
    Route::get('/appointment/slots-pooled', [AppointmentController::class, 'slotsPooled'])->name('appointment.slots.pooled');
    Route::get('/appointment/view/{id}', [AppointmentController::class, 'show'])->name('appointment.view');
    Route::patch('/appointment/{id}/cancel', [AppointmentController::class, 'cancel'])->name('appointment.cancel');
    Route::patch('/appointment/{appointment}/confirm', [AppointmentController::class, 'studentConfirm'])
    ->name('appointment.confirm');

    /* ------------------------ Self-Assessment ------------------------ */
    Route::get('/self-assessment',        [SelfAssessmentController::class,'create'])->name('self-assessment.create');
    Route::post('/self-assessment/store', [SelfAssessmentController::class,'store'])->name('self-assessment.store');
    Route::get('/self-assessment/skip',   [SelfAssessmentController::class,'skip'])->name('self-assessment.skip');

    /* ---------------------- Feature toggle (signed) ------------------ */
    Route::get('/features/enable-appointment', [FeaturesController::class, 'enableAppointment'])
        ->name('features.enable_appointment')
        ->middleware('signed');


 // Notifications (student side)
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/{id}/mark', [NotificationController::class, 'mark'])->name('notifications.mark');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    Route::post('/notifications/mark-all', [NotificationController::class, 'markAll'])
  ->name('notifications.mark_all')
  ->middleware('auth');


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

/*
|--------------------------------------------------------------------------
| Support & About
|--------------------------------------------------------------------------
*/
Route::view('/support/contact', 'support.contact')->name('support.contact');
Route::view('/support/bug', 'support.bug')->name('support.bug');

Route::get('/about', [AboutController::class, 'index'])->name('about.index');

/*
|--------------------------------------------------------------------------
| EMAIL OTP VERIFICATION ROUTES (guest; with throttles)
|--------------------------------------------------------------------------
*/
// web.php (keep as you have, just swap the throttle bits)
Route::prefix('auth/email/otp')->middleware(['guest'])->group(function () {
    Route::post('/send',  [\App\Http\Controllers\Auth\EmailOtpController::class, 'send'])
        ->middleware('throttle:otp-send')
        ->name('auth.email.otp.send');

    Route::post('/verify',[\App\Http\Controllers\Auth\EmailOtpController::class, 'verify'])
        ->middleware('throttle:otp-verify')
        ->name('auth.email.otp.verify');
});

/*
|--------------------------------------------------------------------------
| Auth scaffolding (login, logout, password reset, etc.)
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/counselor.php';
