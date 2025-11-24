<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegalController extends Controller
{
    public function consent(Request $request)
    {
        return view('legal.consent', [
            'tosVersion' => config('legal.tos_version', 1),
        ]);
    }

    public function accept(Request $request)
    {
        $request->validate(['agree' => ['required', 'accepted']]);

        $user = $request->user();
        $user->forceFill([
            'tos_version'     => (int) config('legal.tos_version', 1),
            'tos_accepted_at' => now(),
            'tos_ip'          => $request->ip(),
            'tos_user_agent'  => (string) $request->userAgent(),
        ])->save();

        $intended = (string) $request->session()->pull('tos.intended', '');

        if ($intended && !str_contains($intended, '/legal/consent')) {
            return redirect()->to($intended);
        }
        return redirect()->route('chat.index');
    }

       public function decline(Request $request)
    {
        // If logged in, log out + invalidate session
        if (Auth::check()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Balik sa login page with info toast
        return redirect()
            ->route('login')
            ->with('info', 'You can only use LumiCHAT after accepting the Terms & Conditions.');
    }
}
