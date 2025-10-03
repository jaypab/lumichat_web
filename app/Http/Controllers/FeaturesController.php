<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeaturesController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const FLASH_STATUS      = 'status';
    private const MSG_UNLOCKED      = 'Appointment booking unlocked.';
    private const SESSION_KEY       = 'appointment_enabled';
    private const USERS_TABLE       = 'users';
    private const COL_APPT_ENABLED  = 'appointment_enabled';
    private const ROUTE_BOOK_CREATE = 'appointment.create';

    /**
     * Enable appointment booking for the current session (and persist if column exists).
     */
    public function enableAppointment(Request $request): RedirectResponse
    {
        // Keep validation flexible for signed links carrying extra params
        $request->validate([
            'expires' => ['sometimes'],
        ]);

        // Session unlock (works immediately)
        session([self::SESSION_KEY => true]);

        // Persist if the boolean column exists (optional, no behavior change otherwise)
        $user = $request->user() ?? Auth::user();
        if ($user && Schema::hasColumn(self::USERS_TABLE, self::COL_APPT_ENABLED)) {
            DB::table(self::USERS_TABLE)
                ->where('id', $user->id)
                ->update([
                    self::COL_APPT_ENABLED => 1,
                    'updated_at'           => now(),
                ]);
        }

        // Redirect student to the booking form
        return redirect()
            ->route(self::ROUTE_BOOK_CREATE)
            ->with(self::FLASH_STATUS, self::MSG_UNLOCKED);
    }
}
