<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTosAccepted
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (! $user) return redirect()->route('login');

        if ($user->tos_accepted_at) {
            return $next($request);
        }

        if ($request->routeIs('legal.consent','legal.accept','logout')) {
            return $next($request);
        }

        $request->session()->put('tos.intended', url()->full());
        return redirect()->route('legal.consent');
    }
}
