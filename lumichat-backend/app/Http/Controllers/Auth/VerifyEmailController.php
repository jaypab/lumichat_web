<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const QS_VERIFIED = '?verified=1';

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->verifiedHome());
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($this->verifiedHome());
    }

    // ==== Private helpers (no logic change) ====

    private function verifiedHome(): string
    {
        return RouteServiceProvider::HOME . self::QS_VERIFIED;
    }
}
