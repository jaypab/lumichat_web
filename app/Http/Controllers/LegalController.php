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
        $request->validate(['agree' => ['required','accepted']]);

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

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        // let them through if already accepted
        if ($user->tos_accepted_at) {
            return $next($request);
        }

        // allow consent + accept + logout while unaccepted
        if ($request->routeIs('legal.consent','legal.accept','logout')) {
            return $next($request);
        }

        // remember where they were going
        $request->session()->put('tos.intended', url()->full());

        return redirect()->route('legal.consent');
    }
}
