<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $lastTouchedAt = (int) $request->session()->get('last_seen_touch_ts', 0);
            $nowTs = now()->timestamp;
            $user = $request->user();

            if ($user) {
                // Presence flag for fast online/offline checks in admin views.
                Cache::put('user-online-' . $user->id, true, now()->addMinutes(2));
            }

            // Update at most once per minute per session to reduce write load.
            if (($nowTs - $lastTouchedAt) >= 60) {
                if ($user) {
                    $user->forceFill(['last_seen_appt_at' => now()])->saveQuietly();
                    $request->session()->put('last_seen_touch_ts', $nowTs);
                }
            }
        }

        return $next($request);
    }
}
