<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /** Show login form (role-aware). */
    public function create(Request $request): View
    {
        // default
        $loginContext = 'student';

        // quick override for testing: /login?ctx=admin
        $ctxParam = strtolower((string) $request->query('ctx', ''));
        if (in_array($ctxParam, ['admin','student'], true)) {
            $loginContext = $ctxParam;
        } else {
            // if URL is /admin/login -> admin
            if ($request->is('admin') || $request->is('admin/*')) {
                $loginContext = 'admin';
            } else {
                // if redirected from admin page -> admin
                $intended = (string) $request->session()->get('url.intended', '');
                $intendedPath = parse_url($intended, PHP_URL_PATH) ?? '';
                if (Str::startsWith($intendedPath, '/admin')) {
                    $loginContext = 'admin';
                }
            }
        }

        return view('auth.login', ['loginContext' => $loginContext]);
    }

    /** Handle login. */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        // If user was trying to hit a specific protected page, respect it,
        // especially for /counselor/* pages.
        $intended = (string) $request->session()->pull('url.intended', '');
        if ($intended) {
            // Hard-stop counselors from being redirected to /admin
            if ($user->role === 'counselor' && str_starts_with($intended, '/counselor')) {
                return redirect()->to($intended);
            }
            if ($user->role === 'admin' && str_starts_with($intended, '/admin')) {
                return redirect()->to($intended);
            }
            // If intended is irrelevant (e.g., admin page for counselor), ignore it below.
        }

        // Route explicitly by role (counselor first)
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
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
