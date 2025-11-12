<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Return JSON feed for the dropdown
    public function feed(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        try {
            $items = $user->notifications()
                ->latest()
                ->limit(15)
                ->get()
                ->map(function ($n) {
                    $data = is_array($n->data) ? $n->data : (array) $n->data;
                    $type = (string)($data['type'] ?? '');

                    // Optional: special formatting for counselor reassignment requests
                    if ($type === 'cr.request') {
                        $title   = $data['title']   ?? 'Reassignment requested';
                        $message = $data['message'] ?? 'Student requested counselor reassignment';
                        if (!empty($data['reason'])) {
                            $message .= ' — '.$data['reason'];
                        }

                        return [
                            'id'                   => (string) $n->id,
                            'type'                 => $type,                         // <—
                            'title'                => $title,
                            'body'                 => $message,
                            'url'                  => $data['url'] ?? null,
                            'appointment_id'       => $data['appointment_id'] ?? null, // <—
                            // optional UI hints if your blade/component supports them
                            'tone'                 => 'violet',
                            'icon'                 => 'swap',

                            'created_at'           => optional($n->created_at)->toIso8601String(),
                            'created_at_human'     => optional($n->created_at)->diffForHumans(),
                            'created_at_human_full'=> optional($n->created_at)->toDayDateTimeString(),
                            'read_at'              => optional($n->read_at)->toIso8601String(),
                        ];
                    }

                    // Default / other notification types
                    return [
                        'id'                   => (string) $n->id,
                        'type'                 => $type, // keep for client branching
                        'title'                => $data['title'] ?? ($data['subject'] ?? 'Notification'),
                        'body'                 => $data['body']  ?? ($data['message'] ?? ''),
                        'url'                  => $data['url']   ?? null,
                        'created_at'           => optional($n->created_at)->toIso8601String(),
                        'created_at_human'     => optional($n->created_at)->diffForHumans(),
                        'created_at_human_full'=> optional($n->created_at)->toDayDateTimeString(),
                        'read_at'              => optional($n->read_at)->toIso8601String(),
                    ];
                });

            return response()->json([
                'ok'           => true,
                'items'        => $items,
                'unread_count' => $user->unreadNotifications()->count(),
            ], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'feed_error'], 500);
        }
    }


    // Mark one as read (AJAX-safe)
    public function mark(Request $request, string $id)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        // If bell called via AJAX, return JSON; otherwise keep your original behavior
        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    // Optional: full page
    public function index()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->latest()
            ->take(50) // or paginate()
            ->get();

        // choose layout by role
        $layout = match (strtolower((string)($user->role ?? ''))) {
            'admin'     => 'layouts.admin',
            'counselor' => 'layouts.counselor',
            default     => 'layouts.app', // student
        };

        return view('notifications.index', [
            'notifications' => $notifications,
            'layout'        => $layout,
        ]);
    }

    // Mark all as read (AJAX-safe, keeps your flash for non-AJAX)
    public function markAll(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true], 200);
        }

        return back()->with('swal', ['icon'=>'success','title'=>'All caught up!']);
    }
}
