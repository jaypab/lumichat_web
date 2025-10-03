<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const VALIDATION_BAG = 'updatePassword';
    private const FLASH_STATUS   = 'status';
    private const STATUS_UPDATED = 'password-updated';

    /**
     * Update the authenticated user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // Fail-fast validation (same rules and bag)
        $validated = $request->validateWithBag(self::VALIDATION_BAG, [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        // Persist new password (same behavior)
        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Consistent flash response
        return back()->with(self::FLASH_STATUS, self::STATUS_UPDATED);
    }
}
