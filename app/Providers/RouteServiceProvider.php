<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/chat';

    public function boot(): void
    {
        // API limiter (existing)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // 🔒 Forgot-password limiter: 3 requests/min per email+IP
        RateLimiter::for('forgot-password', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));
            return Limit::perMinute(3)->by($email.'|'.$request->ip()); // 3/min per email+IP
        });
        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::middleware('web')
                ->group(base_path('routes/admin.php'));
        });

         // OTP send: 1 per 60s in prod; generous in local
        RateLimiter::for('otp-send', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::perMinute(60)->by($request->ip()); // dev friendly
            }
            // key by email+ip to avoid shared-ip lockouts
            $key = strtolower((string)$request->input('email')).'|'.$request->ip();
            return Limit::perMinutes(1, 1)->by($key); // 1 per 60s
        });

        // OTP verify: 6 per 10 minutes in prod; generous in local
        RateLimiter::for('otp-verify', function (Request $request) {
            if (app()->environment('local')) {
                return Limit::perMinute(120)->by($request->ip()); // dev friendly
            }
            $key = strtolower((string)$request->input('email')).'|'.$request->ip();
            return Limit::perMinutes(10, 6)->by($key);
        });

    }
}
