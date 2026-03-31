<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentAnnouncementController extends Controller
{
    /**
     * Display a listing of active announcements for students.
     */
    public function index()
    {
        // 1. Fetch Pinned (High Priority) - limited to top 3 for clarity
        $pinnedAnnouncements = Announcement::active()
            ->where('priority', 'high')
            ->latest()
            ->take(3)
            ->get();

        // 2. Fetch all paginated announcements (main feed)
        $announcements = Announcement::active()->latest()->paginate(8);

        // 3. Mark last seen if logged in
        if (Auth::check()) {
            Auth::user()->update([
                 'last_seen_announcement_at' => now()
            ]);
        }

        return view('student.announcements', compact('announcements', 'pinnedAnnouncements'));
    }
}
