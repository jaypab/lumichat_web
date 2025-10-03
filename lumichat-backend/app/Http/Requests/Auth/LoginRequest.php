<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Sanitize & normalize before validation */
    protected function prepareForValidation(): void
    {
        $emailRaw = (string) $this->input('email', '');
        $email    = preg_replace('/^\s+|\s+$/u', '', $emailRaw);
        $email    = preg_replace('/\p{C}+/u', '', $email);
        $email    = filter_var(Str::lower($email), FILTER_SANITIZE_EMAIL);

        $passwordRaw = (string) $this->input('password', '');
        $password    = preg_replace('/\p{C}+/u', '', $passwordRaw);

        $this->merge([
            'email'    => $email,
            'password' => $password,
            'remember' => (bool) $this->boolean('remember'),
        ]);
    }

    public function rules(): array
    {
        return [
            'email'    => ['bail', 'required', 'string', 'max:254', 'email:rfc'],
            'password' => ['bail', 'required', 'string', 'min:8', 'max:72'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /** Attempt auth with lockout protection */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey()); // count this failure
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** Progressive lockout if too many attempts */
    public function ensureIsNotRateLimited(): void
    {
        $key         = $this->throttleKey();
        $maxAttempts = 5;

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        event(new Lockout($this));

        // Base time remaining from Laravel's limiter (seconds)
        $baseSeconds = (int) RateLimiter::availableIn($key);

        // Track how many times this particular key has been locked out recently
        $lockKey  = 'login:lockouts:' . sha1($key);
        $hits     = (int) Cache::get($lockKey, 0) + 1;
        Cache::put($lockKey, $hits, now()->addMinutes(10));

        // Exponential backoff multiplier: 1, 2, 4, 8, 16 (capped at 16x)
        $multiplier = 1 << min(4, $hits - 1);

        // Total wait we want to enforce
        $totalWait = max(1, $baseSeconds * $multiplier);

        // Prolong the limiter window to reflect our total wait
        // (This ensures the user can't retry earlier than shown.)
        RateLimiter::hit($key, $totalWait);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $totalWait,
                'minutes' => (int) ceil($totalWait / 60),
            ]),
        ]);
    }

    /** The unique rate-limit key for this request */
    public function throttleKey(): string
    {
        // email (normalized) + IP; Str::transliterate reduces weird chars
        return Str::transliterate(Str::lower((string) $this->string('email'))).'|'.$this->ip();
    }
}
