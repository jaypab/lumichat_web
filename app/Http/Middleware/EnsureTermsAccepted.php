<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTermsAccepted
{
    /**
     * Block access until T&C accepted.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Allow if already accepted
        if ($user->tos_accepted_at) {
            return $next($request);
        }

        // Allow consent/accept/decline/logout while unaccepted
        if ($request->routeIs('legal.consent', 'legal.accept', 'legal.decline', 'logout')) {
            return $next($request);
        }

        // Remember destination and send to consent
        $request->session()->put('tos.intended', url()->full());

        return redirect()->route('legal.consent');
    }
}
