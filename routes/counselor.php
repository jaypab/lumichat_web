<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Counselor\CounselorDashboardController;
use App\Http\Controllers\Counselor\CounselorAvailabilityController;
use App\Http\Controllers\Counselor\HighriskReviewController;

// Middleware note: Replace 'auth' and 'counselor' with your actual guards / gates.
Route::middleware(['auth', 'counselor'])->prefix('counselor')->name('counselor.')->group(function () {
    Route::get('/', [CounselorDashboardController::class, 'index'])->name('dashboard');

    Route::get('/availability', [CounselorAvailabilityController::class, 'index'])->name('availability.index');
    Route::post('/availability', [CounselorAvailabilityController::class, 'store'])->name('availability.store');
    Route::delete('/availability/{id}', [CounselorAvailabilityController::class, 'destroy'])->name('availability.destroy');

    Route::get('/highrisk', [HighriskReviewController::class, 'index'])->name('highrisk.index');
    Route::get('/highrisk/{id}', [HighriskReviewController::class, 'show'])->name('highrisk.show');
    Route::put('/highrisk/{id}', [HighriskReviewController::class, 'update'])->name('highrisk.update');
});

// JSON endpoint that the student-side booking page can call to AUTO-UPDATE slots
Route::middleware(['auth'])->get('/api/counselors/{id}/slots', [CounselorAvailabilityController::class, 'slots'])
    ->name('api.counselors.slots');
