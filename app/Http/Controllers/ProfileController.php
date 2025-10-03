<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Registration;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ProfileController extends Controller
{
    // ==== Constants (dedupe magic strings) ====
    private const VIEW_EDIT        = 'profile.edit';
    private const ROUTE_EDIT       = 'profile.edit';
    private const FLASH_STATUS     = 'status';
    private const FLASH_SUCCESS    = 'success';
    private const MSG_UPDATED_KEY  = 'profile-updated';
    private const MSG_UPDATED      = 'Profile updated';
    private const MSG_SAVE_ERROR   = 'Something went wrong while saving. Please try again.';
    private const MSG_DELETE_OK    = 'Account has been successfully deleted.';

    /**
     * Show the Profile page.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Link registration by email (fallback by name)
        $registration = Registration::query()
            ->where('email', $user->email)
            ->orWhere('full_name', $user->name)
            ->first();

        return view(self::VIEW_EDIT, [
            'user'         => $user,
            'registration' => $registration,
        ]);
    }

    /**
     * Update profile (name, email, course/year/phone).
     * Validation + sanitation are handled by ProfileUpdateRequest.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated(); // phone is REQUIRED here

        try {
            DB::transaction(function () use ($user, $data) {
                $originalEmail = $user->email;

                // Update Users table
                $user->fill([
                    'name'  => $data['name'],
                    'email' => $data['email'],
                ]);

                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }

                $user->save();

                // Upsert Registration by email (schema without user_id)
                Registration::updateOrCreate(
                    ['email' => $user->email],
                    [
                        'full_name'      => $user->name,
                        'email'          => $user->email,
                        'course'         => $data['course']      ?? null,
                        'year_level'     => $data['year_level']  ?? null,
                        'contact_number' => $data['contact_number'], // required by FormRequest
                    ]
                );

                // Clean any stale row left under the old email
                if ($originalEmail !== $user->email) {
                    Registration::where('email', $originalEmail)->delete();
                }
            });

            return Redirect::route(self::ROUTE_EDIT)
                ->with(self::FLASH_STATUS, self::MSG_UPDATED_KEY)
                ->with(self::FLASH_SUCCESS, self::MSG_UPDATED);
        } catch (\Throwable $e) {
            Log::error('Profile update failed', ['user_id' => $user->id, 'err' => $e]);

            return back()
                ->withInput()
                ->with('error', self::MSG_SAVE_ERROR);
        }
    }

    /**
     * Permanently delete the user account (and related data).
     * - Confirms current password
     * - Deletes dependent rows first to avoid FK violations
     * - Deletes tbl_registration row(s)
     * - Deletes auth artifacts (sanctum tokens, reset tokens, sessions)
     * - Deletes the user (forceDelete if SoftDeletes)
     * - Logs out and invalidates session
     */
    public function destroy(Request $request): RedirectResponse
{
    // Validate the current password into a separate error bag used by your modal
    $request->validateWithBag('userDeletion', [
        'password' => ['required', 'current_password'],
    ]);

    $user   = $request->user();
    $userId = $user->id;
    $email  = $user->email;
    $type   = get_class($user);

    try {
        DB::transaction(function () use ($user, $userId, $email, $type) {

            /* -------------------- 1) Domain dependents -------------------- */
            $this->deleteIfExists('tbl_appointments',     ['student_id' => $userId]);
            $this->deleteIfExists('tbl_appointments',     ['email'      => $email]);
            $this->deleteIfExists('tbl_chatbot_sessions', ['user_id'    => $userId]);
            $this->deleteIfExists('tbl_chat_messages',    ['user_id'    => $userId]);
            $this->deleteIfExists('tbl_self_assessment',  ['user_id'    => $userId]);
            $this->deleteIfExists('tbl_diagnosis',        ['user_id'    => $userId]);

            /* -------------------- 2) Registration rows ------------------- */
            if (Schema::hasTable('tbl_registration')) {
                if (Schema::hasColumn('tbl_registration', 'user_id')) {
                    DB::table('tbl_registration')->where('user_id', $userId)->delete();
                }
                if (Schema::hasColumn('tbl_registration', 'email')) {
                    DB::table('tbl_registration')->where('email', $email)->delete();
                }
            }

            /* -------------------- 3) Auth artifacts ---------------------- */
            // Sanctum tokens
            if (class_exists(\Laravel\Sanctum\PersonalAccessToken::class)
                && Schema::hasTable('personal_access_tokens')) {
                \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $userId)
                    ->where('tokenable_type', $type)
                    ->delete();
            }

            // Password reset tokens (Laravel 10 table)
            if (Schema::hasTable('password_reset_tokens')
                && Schema::hasColumn('password_reset_tokens', 'email')) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
            // Legacy name (just in case)
            if (Schema::hasTable('password_resets')
                && Schema::hasColumn('password_resets', 'email')) {
                DB::table('password_resets')->where('email', $email)->delete();
            }

            // DB sessions (if using database driver)
            if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
                DB::table('sessions')->where('user_id', $userId)->delete();
            }

            /* -------------------- 4) Delete the user (once) -------------- */
            $deletedUsers = 0;

            // Preferred: hard-delete from your custom users table
            if (Schema::hasTable('tbl_users')) {
                $deletedUsers = DB::table('tbl_users')
                    ->where('id', $userId)
                    ->when(Schema::hasColumn('tbl_users', 'email'), function ($q) use ($email) {
                        $q->orWhere('email', $email);
                    })
                    ->delete();

                Log::info('tbl_users hard delete', [
                    'user_id' => $userId,
                    'email'   => $email,
                    'deleted' => $deletedUsers,
                ]);
            }

            // Fallback: let Eloquent delete using the model's table mapping
            if ($deletedUsers === 0) {
                $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($user), true);
                $usesSoftDeletes ? $user->forceDelete() : $user->delete();

                Log::info('Fallback Eloquent delete executed', [
                    'user_id' => $userId,
                    'table'   => method_exists($user, 'getTable') ? $user->getTable() : 'unknown',
                ]);
            }
        });

        // Logout AFTER the transaction has removed the account
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            // This is what your alerts.blade listens for
            ->with('status', 'Account has been successfully deleted.');

    } catch (\Throwable $e) {
        Log::error('Account deletion error', [
            'user_id' => $userId,
            'email'   => $email,
            'err'     => $e,
        ]);

        // Surface the error in the same bag your modal uses
        $msg = config('app.debug')
            ? 'Could not delete account: '.$e->getMessage()
            : 'Could not delete account. Please try again.';

        return back()->withErrors(['password' => $msg], 'userDeletion');
    }
}

    /**
     * Helper to delete rows from a table if it exists.
     * Logs the action for audit purposes.
     */
    private function deleteIfExists(string $table, array $where): void
    {
        // table must exist
        if (!Schema::hasTable($table)) return;

        // every referenced column must exist
        foreach (array_keys($where) as $col) {
            if (!Schema::hasColumn($table, $col)) {
                // silently skip when column isn't present
                Log::info('Skip delete: column missing', compact('table','col','where'));
                return;
            }
        }

        $deleted = DB::table($table)->where($where)->delete();
        if ($deleted > 0) {
            Log::info('Deleted dependent rows', compact('table','where','deleted'));
        }
    }
}