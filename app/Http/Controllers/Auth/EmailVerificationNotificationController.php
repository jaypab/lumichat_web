<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const FLASH_STATUS = 'status';
    private const STATUS_VERIFICATION_LINK_SENT = 'verification-link-sent';

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user(); // auth middleware should guarantee this

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $user->sendEmailVerificationNotification();

        return back()->with(self::FLASH_STATUS, self::STATUS_VERIFICATION_LINK_SENT);
    }
}
