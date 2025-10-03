<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfirmablePasswordController extends Controller
{
    // ==== Constants ====
    private const VIEW_CONFIRM   = 'auth.confirm-password';
    private const GUARD          = 'web';
    private const SESSION_KEY    = 'auth.password_confirmed_at';

    /**
     * Show the confirm password view.
     */
    public function show(): View
    {
        return view(self::VIEW_CONFIRM);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        // Same credential validation as your original code
        if (! Auth::guard(self::GUARD)->validate([
            'email'    => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        // Same session flag
        $request->session()->put(self::SESSION_KEY, \time());

        // Same redirect behavior
        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
