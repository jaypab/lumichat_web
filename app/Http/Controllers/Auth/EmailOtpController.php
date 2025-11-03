<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailOtpController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required','email:rfc,dns','max:255'],
        ]);

        $email = Str::lower($request->input('email'));

        // Rate limit per email (server-side)
        $keyCooldown = "otp:cooldown:$email";
        if (Cache::has($keyCooldown)) {
            return response()->json(['message' => 'Please wait before requesting another code.'], 429);
        }

        // Generate and store OTP for 10 minutes
        $code = random_int(100000, 999999);
        Cache::put("otp:code:$email", $code, now()->addMinutes(10));
        Cache::put($keyCooldown, true, now()->addSeconds(60));

        // Send email
        Mail::raw("Your LumiCHAT verification code is: $code (valid for 10 minutes).", function($m) use ($email){
            $m->to($email)->subject('LumiCHAT Email Verification Code');
        });

        return response()->json(['message' => 'Verification code sent. Please check your inbox.']);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'email' => ['required','email:rfc,dns','max:255'],
            'code'  => ['required','digits:6'],
        ]);

        $email = Str::lower($request->input('email'));
        $code  = $request->input('code');

        $stored = Cache::get("otp:code:$email");
        if (!$stored || (string)$stored !== (string)$code) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        // Issue a short-lived token to unlock submit
        $token = Str::uuid()->toString();
        Cache::put("otp:token:$email", $token, now()->addMinutes(15));

        // Optional: consume OTP to prevent reuse
        Cache::forget("otp:code:$email");

        return response()->json(['token' => $token]);
    }

    // Helper you can use in RegisterRequest if you want to enforce server-side check
    public static function validateToken(string $email, ?string $token): bool
    {
        if (!$token) return false;
        $saved = Cache::get("otp:token:".Str::lower($email));
        return $saved && hash_equals($saved, $token);
    }
}
