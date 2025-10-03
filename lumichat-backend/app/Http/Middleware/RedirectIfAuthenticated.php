<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * If the user is already authenticated:
     *  - Admin or Counselor -> redirect to admin dashboard
     *  - Student            -> redirect to chat
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if ($user && method_exists($user, 'canAccessAdmin') && $user->canAccessAdmin()) {
                    return redirect()->route('admin.dashboard');
                }

                return redirect()->route('chat.index');
            }
        }

        return $next($request);
    }
}
