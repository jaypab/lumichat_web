<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class Notify
{
    public static function to(User|int|null $userOrId, string $title, string $body = '', ?string $url = null): void
    {
        if (!$userOrId) return;
        $user = $userOrId instanceof User ? $userOrId : User::find((int)$userOrId);
        if (!$user) return;
        $user->notify(new SimpleDatabaseNotification($title, $body, $url));
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
     * Accepts **counselors.id**; maps to users.id via:
     *   1) counselors.user_id if the column exists
     *   2) counselors.email -> users.email (fallback)
     */
    public static function counselor(int $counselorId, string $title, string $body = '', ?string $url = null): void
    {
        $uid = null;

        // Use user_id only if the column exists
        if (Schema::hasColumn('tbl_counselors', 'user_id')) {
            $uid = DB::table('tbl_counselors')->where('id', $counselorId)->value('user_id');
        }

        // Fallback via email → users.email
        if (!$uid) {
            $email = DB::table('tbl_counselors')->where('id', $counselorId)->value('email');
            if ($email) {
                $uid = User::where('email', $email)->value('id');
            }
        }

        if ($uid) {
            self::to((int)$uid, $title, $body, $url);
        } else {
            Log::warning('Notify::counselor: could not map counselor to user', [
                'counselor_id' => $counselorId,
            ]);
        }
    }

    public static function admin(int $adminUserId, string $title, string $body = '', ?string $url = null): void
    {
        self::to($adminUserId, $title, $body, $url);
    }
}
