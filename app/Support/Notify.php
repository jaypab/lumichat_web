<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;   // generic (student/counselor/etc.)
use App\Notifications\AdminGeneric;                 // admin-focused notification
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Notify
{
    /** Feature flags: config/notify.php → ['features' => ['role_broadcast' => bool, 'admins_broadcast' => bool]] */
    private static function enabled(string $key, bool $default = false): bool
    {
        $cfg = config('notify.features', []);
        return array_key_exists($key, $cfg) ? (bool)$cfg[$key] : $default;
    }

    /* -----------------------------------------------------------------------
     | Core generic helpers (database notifications via SimpleDatabaseNotification)
     * ----------------------------------------------------------------------*/

    public static function to(User|int|null $userOrId, string $title, string $body = '', ?string $url = null): void
    {
        if (!$userOrId) return;
        $user = $userOrId instanceof User ? $userOrId : User::find((int)$userOrId);
        if (!$user) return;

        try {
            $user->notify(new SimpleDatabaseNotification($title, $body, $url));
        } catch (\Throwable $e) {
            Log::warning('Notify::to failed', ['user_id' => $user?->id, 'e' => $e->getMessage()]);
        }
    }

    public static function toMany(iterable $usersOrIds, string $title, string $body = '', ?string $url = null): void
    {
        foreach ($usersOrIds as $u) {
            self::to($u, $title, $body, $url);
        }
    }

    public static function student(int $studentId, string $title, string $body = '', ?string $url = null): void
    {
        self::to($studentId, $title, $body, $url);
    }

    /**
     * Map counselors.id → users.id via tbl_counselors.user_id (if exists) or email fallback.
     */
    public static function counselor(int $counselorId, string $title, string $body = '', ?string $url = null): void
    {
        $uid = null;

        if (Schema::hasColumn('tbl_counselors', 'user_id')) {
            $uid = DB::table('tbl_counselors')->where('id', $counselorId)->value('user_id');
        }

        if (!$uid) {
            $email = DB::table('tbl_counselors')->where('id', $counselorId)->value('email');
            if ($email) {
                $uid = User::where('email', $email)->value('id');
            }
        }

        if ($uid) {
            self::to((int)$uid, $title, $body, $url);
        } else {
            Log::warning('Notify::counselor map failed', ['counselor_id' => $counselorId]);
        }
    }

    /**
     * Broadcast by role (requires users.role). Guarded by feature flag.
     */
    public static function role(string $role, string $title, string $body = '', ?string $url = null): void
    {
        if (!self::enabled('role_broadcast', false)) {
            Log::notice('Notify::role skipped (feature disabled)', ['role' => $role]);
            return;
        }

        if (!Schema::hasColumn((new User)->getTable(), 'role')) {
            Log::notice('Notify::role skipped — users.role not found', ['role' => $role]);
            return;
        }

        User::query()
            ->whereRaw('LOWER(role) = ?', [mb_strtolower($role)])
            ->select(['id'])
            ->chunkById(500, function ($chunk) use ($title, $body, $url) {
                foreach ($chunk as $u) {
                    self::to((int)$u->id, $title, $body, $url);
                }
            });
    }

    /* -----------------------------------------------------------------------
     | Admin-focused helpers (use AdminGeneric + safe internal URLs)
     * ----------------------------------------------------------------------*/

    /** Notify one admin user (role check not enforced here). */
    public static function admin(int $adminUserId, string $title, string $body = '', ?string $url = null): void
    {
        if (!$u = User::find($adminUserId)) return;

        try {
            $u->notify(new AdminGeneric($title, $body, self::safeUrl($url)));
        } catch (\Throwable $e) {
            Log::warning('Notify::admin failed', ['admin_id' => $adminUserId, 'e' => $e->getMessage()]);
        }
    }

    /**
     * Notify all admins (users.role = 'admin'). Returns count sent.
     * Guarded by feature flag admins_broadcast (defaults to false unless enabled).
     */
    public static function admins(string $title, string $body = '', ?string $url = null): int
    {
        if (!self::enabled('admins_broadcast', false)) {
            Log::notice('Notify::admins skipped (feature disabled)');
            return 0;
        }

        $count = 0;
        User::where('role', 'admin')->select('id')->chunkById(200, function ($chunk) use (&$count, $title, $body, $url) {
            foreach ($chunk as $u) {
                try {
                    $u->notify(new AdminGeneric($title, $body, self::safeUrl($url)));
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('Notify::admins item failed', ['admin_id' => $u->id, 'e' => $e->getMessage()]);
                }
            }
        });

        return $count;
    }

    /** Keep internal links safe (if you pass a route name, ensure it exists). */
    private static function safeUrl(?string $url): ?string
    {
        if (!$url) return null;
        if (preg_match('#^https?://#', $url) || str_starts_with($url, '/')) {
            return $url; // absolute or absolute-path
        }

        // treat as route name if possible
        return Route::has($url) ? route($url) : null;
    }
}
