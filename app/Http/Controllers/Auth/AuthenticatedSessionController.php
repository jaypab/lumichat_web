<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginRequest; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /** Show login form (role-aware). */
    public function create(Request $request): View
    {
        $loginContext = 'student';

        $ctxParam = strtolower((string) $request->query('ctx', ''));
        if (in_array($ctxParam, ['admin','student'], true)) {
            $loginContext = $ctxParam;
        } else {
            if ($request->is('admin') || $request->is('admin/*')) {
                $loginContext = 'admin';
            } else {
                $intended = (string) $request->session()->get('url.intended', '');
                $intendedPath = parse_url($intended, PHP_URL_PATH) ?? '';
                if (Str::startsWith($intendedPath, '/admin')) {
                    $loginContext = 'admin';
                }
            }
        }

        return view('auth.login', ['loginContext' => $loginContext]);
    }

    /** Handle login with rate-limit: 5 bad attempts then cooldown. */
    public function store(LoginRequest $request): RedirectResponse
    {
        $maxAttempts  = 5;
        $decaySeconds = 120;

        $email       = Str::lower((string) $request->input('email'));
        $throttleKey = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email','remember'))
                ->with('cooldown', $seconds)                      // 👈 pass seconds to the view
                ->withErrors(['email' => 'Too many login attempts. Please try again later.']);
        }

        if (! Auth::attempt(['email' => $email, 'password' => (string)$request->input('password')], (bool)$request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, $decaySeconds);
            return back()
                ->withInput($request->only('email','remember'))
                ->withErrors(['email' => 'Invalid credentials.']);
        }

        // Success -> clear counters and proceed
        RateLimiter::clear($throttleKey);

        // Regenerate session (protects against fixation)
        $request->session()->regenerate();

        // Ensure no stale re-auth window
        $request->session()->forget('admin.reauth_until');

        $user = $request->user();

        // Respect intended URL if it matches role
        $intended = (string) $request->session()->pull('url.intended', '');
        if ($intended) {
            if ($user->role === 'counselor' && str_starts_with($intended, '/counselor')) {
                return redirect()->to($intended);
            }
            if ($user->role === 'admin' && str_starts_with($intended, '/admin')) {
                return redirect()->to($intended);
            }
        }

        // Role routing
        if ($user->role === 'counselor') {
            return redirect()->route('counselor.dashboard');
        }
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        // Default student landing
        return redirect()->intended(route('chat.index'));
    }

    /** Logout. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin.reauth_until');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
