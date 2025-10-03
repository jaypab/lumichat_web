<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    // ==== Constants (dedupe strings / keep things consistent) ====
    private const VIEW_RESET            = 'auth.reset-password';
    private const FLASH_STATUS          = 'status';
    private const MSG_PASSWORD_UPDATED  = 'Password updated. You can now sign in.';
    private const ERR_INVALID_LINK      = 'Link invalid or expired—request a new one.';
    private const ACT_RESET_SUCCESS     = 'auth.password.reset_success';
    private const ACT_RESET_FAILED      = 'auth.password.reset_failed';

    /**
     * Show the reset password form.
     */
    public function create(Request $request): View
    {
        return view(self::VIEW_RESET, ['request' => $request]);
    }

    /**
     * Handle a reset password request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email', 'max:191'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $this->normalizeEmail($validated['email']);

        $status = Password::reset(
            [
                'email'                 => $email,
                'password'              => $validated['password'],
                'password_confirmation' => $request->password_confirmation, // same as validated confirm
                'token'                 => $validated['token'],
            ],
            function ($user) use ($request, $validated) {
                // Update password securely
                $user->forceFill([
                    'password'       => Hash::make($validated['password']),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));

                // Sign in, kill other devices, rotate session
                Auth::login($user);
                Auth::logoutOtherDevices($validated['password']);
                $request->session()->regenerate();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            ActivityLog::create([
                'event'        => self::ACT_RESET_SUCCESS,
                'description'  => null,
                'actor_id'     => Auth::id(),
                'subject_type' => User::class,
                'subject_id'   => Auth::id(),
                'meta'         => $this->makeMeta($request),
            ]);

            return redirect()
                ->route('login')
                ->with(self::FLASH_STATUS, self::MSG_PASSWORD_UPDATED);
        }

        // Generic failure (invalid/expired) + audit
        ActivityLog::create([
            'event'        => self::ACT_RESET_FAILED,
            'description'  => null,
            'actor_id'     => null,
            'subject_type' => null,
            'subject_id'   => null,
            'meta'         => [
                'email_hash' => hash('sha256', $email),
                'ip'         => $request->ip(),
                'ua'         => $request->userAgent(),
            ],
        ]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => self::ERR_INVALID_LINK]);
    }

    // ==== Private helpers (no logic change) ====

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function makeMeta(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
        ];
    }
}
