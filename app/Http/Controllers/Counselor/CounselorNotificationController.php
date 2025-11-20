<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CounselorNotificationController extends Controller
{
    /** Custom notifications table name (if present) */
    private const CUSTOM_TABLE = 'tbl_notifications';

    /** Does the custom table exist? */
    private function useCustomTable(): bool
    {
        return Schema::hasTable(self::CUSTOM_TABLE);
    }

    /** Resolve the User model that holds notifications for the logged-in counselor */
    private function resolveUserForCounselor(): ?User
    {
        // Case 1: counselor logs in via same "web" guard as User
        if (Auth::check()) {
            return Auth::user();
        }

        // Case 2: counselor uses a separate guard (e.g., auth:counselor)
        $c = Auth::guard('counselor')->user();
        if (!$c) return null;

        // If tbl_counselors has user_id → map directly
        if (Schema::hasColumn('tbl_counselors', 'user_id')) {
            $uid = DB::table('tbl_counselors')->where('id', $c->id)->value('user_id');
            if ($uid) {
                $u = User::find((int)$uid);
                if ($u) return $u;
            }
        }

        // Fallback via email
        $email = $c->email ?? null;
        if ($email) return User::where('email', $email)->first();

        return null;
    }

    /** Keep only safe/valid URLs to avoid 404 from the Notifications UI */
    private function sanitizeUrl(?string $url): ?string
    {
        if (!$url) return null;

        // Absolute URL? keep.
        if (preg_match('#^https?://#i', $url)) return $url;

        // Absolute path? keep (your app can handle it).
        if (Str::startsWith($url, '/')) return $url;

        // If they accidentally stored a route NAME, try to build it (no params support here).
        if (Route::has($url)) {
            try { return route($url); } catch (\Throwable) { /* fallthrough */ }
        }

        // Otherwise drop it to prevent 404s
        return null;
    }

    /** ============ PAGE ============ */
    public function index(Request $request)
    {
        if ($this->useCustomTable()) {
            // Custom table path
            $user = $request->user(); // counselor is authenticated on web guard due to middleware
            abort_unless($user, 403);

            $rows = DB::table(self::CUSTOM_TABLE)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($n) {
                    $n->data = [
                        'title' => $n->title ?? 'Notification',
                        'body'  => $n->body ?? '',
                        'url'   => $this->sanitizeUrl($n->url ?? null),
                    ];
                    $n->created_at = $n->created_at ? Carbon::parse($n->created_at) : Carbon::now();
                    return $n;
                });

            // Return as a simple collection (your blade supports both collection and paginator)
            $notifications = $rows;

            return view('counselor.notifications.index', compact('notifications'));
        }

        // Fallback: Laravel database notifications
        $user = $this->resolveUserForCounselor();
        abort_unless($user, 403);

        $notifications = $user->notifications()
            ->latest()
            ->paginate(15)
            ->through(function ($n) {
                $data = (array) ($n->data ?? []);
                $n->data = [
                    'title' => $data['title'] ?? 'Notification',
                    'body'  => $data['body'] ?? '',
                    'url'   => $this->sanitizeUrl($data['url'] ?? null),
                ];
                return $n;
            });

        return view('counselor.notifications.index', compact('notifications'));
    }

    /** ============ JSON FEED ============ */
    public function feed(Request $request)
    {
        if ($this->useCustomTable()) {
            $user = $request->user();
            if (!$user) return response()->json(['unread_count' => 0, 'items' => []]);

            $rows = DB::table(self::CUSTOM_TABLE)
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $items = [];
            foreach ($rows as $n) {
                $items[] = [
                    'id'                    => (int) $n->id,
                    'title'                 => (string)($n->title ?? 'Notification'),
                    'body'                  => (string)($n->body ?? ''),
                    'url'                   => $this->sanitizeUrl($n->url ?? null),
                    'read_at'               => $n->read_at,
                    'created_at_human'      => $n->created_at ? Carbon::parse($n->created_at)->diffForHumans() : '',
                    'created_at_human_full' => $n->created_at ? Carbon::parse($n->created_at)->toDayDateTimeString() : '',
                ];
            }

            $unread = DB::table(self::CUSTOM_TABLE)
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->count();

            return response()->json(['unread_count' => $unread, 'items' => $items]);
        }

        // Fallback: Laravel database notifications
        $user = $this->resolveUserForCounselor();
        if (!$user) return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);

        $items = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($n) {
                $data = (array) ($n->data ?? []);
                $url  = $this->sanitizeUrl($data['url'] ?? null);

                return [
                    'id'                    => $n->id,
                    'title'                 => $data['title'] ?? 'Notification',
                    'body'                  => $data['body'] ?? '',
                    'url'                   => $url,
                    'read_at'               => $n->read_at,
                    'created_at_human'      => $n->created_at?->diffForHumans(),
                    'created_at_human_full' => $n->created_at?->toDayDateTimeString(),
                ];
            });

        return response()->json([
            'ok'           => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'items'        => $items,
        ]);
    }

    /** ============ MARK ONE ============ */
    public function mark(Request $request, string $id)
    {
        if ($this->useCustomTable()) {
            $user = $request->user();
            if (!$user) return response()->json(['ok' => false], 401);

            DB::table(self::CUSTOM_TABLE)
                ->where('id', $id)
                ->where('user_id', $user->id)
                ->update(['read_at' => now(), 'updated_at' => now()]);

            return response()->json(['ok' => true]);
        }

        $user = $this->resolveUserForCounselor();
        if (!$user) return response()->json(['ok' => false], 401);

        $n = $user->notifications()->where('id', $id)->first();
        if (!$n) return response()->json(['ok' => false, 'message' => 'Not found'], 404);

        if (is_null($n->read_at)) $n->markAsRead();
        return response()->json(['ok' => true]);
    }

    /** ============ MARK ALL ============ */
    public function markAll(Request $request)
    {
        if ($this->useCustomTable()) {
            $user = $request->user();
            if (!$user) return back();

            DB::table(self::CUSTOM_TABLE)
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now(), 'updated_at' => now()]);

            return back();
        }

        $user = $this->resolveUserForCounselor();
        if (!$user) return back();

        $user->unreadNotifications->markAsRead();
        return back();
    }
}
