<?php

namespace App\Http\Controllers;

use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const VIEW_SETTINGS = 'settings';
    private const FLASH_SUCCESS = 'success';
    private const MSG_SAVED     = 'Settings saved.';

    /**
     * Show settings page.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $settings = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            []
        );

        return view(self::VIEW_SETTINGS, compact('settings', 'user'));
    }

    /**
     * Update settings (only allowed fields).
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validate kept fields (same rules)
        $request->validate([
            'autodelete_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'dark_mode'       => ['nullable', 'boolean'],
        ]);

        // Normalize inputs (same behavior)
        $normalized = [
            'dark_mode'       => (bool) $request->boolean('dark_mode'),
            'autodelete_days' => $request->filled('autodelete_days')
                ? (int) $request->input('autodelete_days')
                : null, // blank disables cleanup
        ];

        $settings = UserSetting::firstOrCreate(['user_id' => $user->id]);
        $settings->update($normalized);

        return back()->with(self::FLASH_SUCCESS, self::MSG_SAVED);
    }
}
