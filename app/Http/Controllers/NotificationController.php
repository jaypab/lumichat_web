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

        $items = $user->notifications()
            ->latest()
            ->limit(15)
            ->get()
            ->map(function ($n) {
                $data = is_array($n->data) ? $n->data : (array) $n->data;
                return [
                    'id' => (string) $n->id,
                    'title' => $data['title'] ?? ($data['subject'] ?? 'Notification'),
                    'body' => $data['body'] ?? ($data['message'] ?? ''),
                    'created_at' => $n->created_at,
                    'created_at_human' => $n->created_at->diffForHumans(),
                    'created_at_human_full' => $n->created_at->toDayDateTimeString(),
                    'read_at' => $n->read_at,
                ];
            });

        return response()->json([
            'items' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    // Mark one as read
    public function mark(string $id)
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        if (!$notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json(['status' => 'ok']);
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
public function markAll()
{
    $user = Auth::user();
    $user->unreadNotifications()->update(['read_at' => now()]);
    return back()->with('swal', ['icon'=>'success','title'=>'All caught up!']);
}


}
