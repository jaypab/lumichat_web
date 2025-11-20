<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AdminNotificationController extends Controller
{
    public function index(Request $request)
    {
    $user = auth()->user(); // admin uses the same User model
    abort_unless($user, 403);

    $notifications = $user->notifications()->latest()->paginate(15);

    // IMPORTANT: render the admin view, not the student one
    return view('admin.notifications.index', compact('notifications'));
    }

    /** JSON feed for bell + page refresh */
    public function feed(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['ok'=>false], 401);

        $items = $user->notifications()->latest()->limit(20)->get()->map(function ($n) {
            $data = (array)($n->data ?? []);
            $url  = $data['url'] ?? null;

            // keep absolute URLs; drop bad relative ones
            if ($url && !preg_match('/^https?:\/\//', $url) && !str_starts_with($url, '/')) {
                $url = null;
            }

            return [
                'id'                   => $n->id,
                'title'                => $data['title'] ?? 'Notification',
                'body'                 => $data['body'] ?? '',
                'url'                  => $url,
                'read_at'              => $n->read_at,
                'created_at_human'     => $n->created_at?->diffForHumans(),
                'created_at_human_full'=> $n->created_at?->toDayDateTimeString(),
            ];
        });

        return response()->json([
            'ok'           => true,
            'unread_count' => $user->unreadNotifications()->count(),
            'items'        => $items,
        ]);
    }

    public function mark(Request $request, string $id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['ok'=>false], 401);

        $n = $user->notifications()->where('id', $id)->first();
        if (!$n) return response()->json(['ok'=>false,'message'=>'Not found'], 404);

        if (is_null($n->read_at)) $n->markAsRead();
        return response()->json(['ok'=>true]);
    }

    public function markAll(Request $request)
    {
        $user = Auth::user();
        if ($user) $user->unreadNotifications->markAsRead();
        return back();
    }
}
