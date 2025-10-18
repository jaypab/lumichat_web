<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCounselor
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        // Gate: must be logged in AND role=counselor
        if (!$u || (string)$u->role !== 'counselor') {
            abort(403, 'Counselor access only.');
        }
        return $next($request);
    }
}
