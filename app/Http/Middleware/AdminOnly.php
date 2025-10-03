<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    /**
     * Allow only users who can access the admin area.
     * (Relies on App\Models\User::canAccessAdmin())
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'canAccessAdmin') || !$user->canAccessAdmin()) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
