<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class Notify
{
    /** Small helper to check feature flags safely */
    private static function enabled(string $key, bool $default = false): bool
    {
        // config/notify.php → features.$key
        $cfg = config('notify.features', []);
        return array_key_exists($key, $cfg) ? (bool) $cfg[$key] : $default;
    }

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
        foreach ($usersOrIds as $u) self::to($u, $title, $body, $url);
    }

    public static function student(int $studentId, string $title, string $body = '', ?string $url = null): void
    {
        self::to($studentId, $title, $body, $url);
    }

    /**
     * Counselor helper.
     * Accepts counselors.id and maps it to users.id via user_id (if column exists) or email fallback.
     */
    public static function counselor(int $counselorId, string $title, string $body = '', ?string $url = null): void
    {
        $uid = null;

        if (Schema::hasColumn('tbl_counselors', 'user_id')) {
            $uid = DB::table('tbl_counselors')->where('id', $counselorId)->value('user_id');
        }

        if (!$uid) {
            $email = DB::table('tbl_counselors')->where('id', $counselorId)->value('email');
            if ($email) $uid = User::where('email', $email)->value('id');
        }

        if ($uid) {
            self::to((int)$uid, $title, $body, $url);
        } else {
            Log::warning('Notify::counselor: could not map counselor to user', ['counselor_id' => $counselorId]);
        }
    }

    public static function admin(int $adminUserId, string $title, string $body = '', ?string $url = null): void
    {
        self::to($adminUserId, $title, $body, $url);
    }

    /**
     * OPTIONAL feature: notify all users with a given role (requires users.role).
     * Guarded by config('notify.features.role_broadcast') or NOTIFY_ENABLE_ROLE_BROADCAST.
     */
    public static function role(string $role, string $title, string $body = '', ?string $url = null): void
    {
        if (!self::enabled('role_broadcast', false)) {
            Log::notice('Notify::role skipped (feature disabled)', ['role' => $role]);
            return;
        }

        if (!Schema::hasColumn((new User)->getTable(), 'role')) {
            Log::notice('Notify::role skipped — users.role column not found', ['role' => $role]);
            return;
        }

        User::query()
            ->whereRaw('LOWER(role) = ?', [mb_strtolower($role)])
            ->select(['id'])
            ->chunkById(500, function ($chunk) use ($title, $body, $url) {
                foreach ($chunk as $u) self::to((int)$u->id, $title, $body, $url);
            });
    }

    /**
     * OPTIONAL feature: shorthand to notify all admins (uses users.role='admin').
     * Guarded by config('notify.features.admins_broadcast') or NOTIFY_ENABLE_ADMINS_BROADCAST.
     */
    public static function admins(string $title, string $body = '', ?string $url = null): void
    {
        if (!self::enabled('admins_broadcast', false)) {
            Log::notice('Notify::admins skipped (feature disabled)');
            return;
        }
        self::role('admin', $title, $body, $url);
    }
      /** Notify a single admin user id */
    public static function admin(int $adminUserId, string $title, ?string $body = null, ?string $url = null): void
    {
        if ($u = User::find($adminUserId)) {
            $u->notify(new AdminGeneric($title, $body, self::safeUrl($url)));
        }
    }

    /** Notify all admins (role=admin) */
    public static function admins(string $title, ?string $body = null, ?string $url = null): int
    {
        $count = 0;
        User::where('role', 'admin')->chunkById(200, function ($chunk) use (&$count, $title, $body, $url) {
            foreach ($chunk as $u) {
                $u->notify(new AdminGeneric($title, $body, self::safeUrl($url)));
                $count++;
            }
        });
        return $count;
    }

    /** Keep internal links safe (don’t emit broken route names) */
    private static function safeUrl(?string $url): ?string
    {
        if (!$url) return null;
        if (preg_match('#^https?://#', $url) || str_starts_with($url, '/')) return $url;

        // if $url looks like a route name, only return if it exists
        return Route::has($url) ? route($url) : null;
    }
}
