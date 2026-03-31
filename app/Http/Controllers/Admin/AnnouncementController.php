<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('author')->latest()->paginate(10);
        return view('admin.announcements.index', compact('announcements'));
    }

    public function create()
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'priority'   => 'required|in:low,normal,high',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active'  => 'boolean',
        ]);

        Announcement::create([
            'title'      => $request->title,
            'content'    => $request->content,
            'author_id'  => Auth::id(),
            'priority'   => $request->priority,
            'is_active'  => $request->has('is_active'),
            'starts_at'  => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);

        return redirect()->route('admin.announcements.index')
            ->with('swal', [
                'title' => 'Success',
                'text' => 'Announcement created successfully.',
                'icon' => 'success',
            ]);
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'priority'   => 'required|in:low,normal,high',
            'starts_at'  => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active'  => 'boolean',
        ]);

        $announcement->update([
            'title'      => $request->title,
            'content'    => $request->content,
            'priority'   => $request->priority,
            'is_active'  => $request->has('is_active'),
            'starts_at'  => $request->starts_at,
            'expires_at' => $request->expires_at,
        ]);

        return redirect()->route('admin.announcements.index')
            ->with('swal', [
                'title' => 'Updated',
                'text' => 'Announcement updated successfully.',
                'icon' => 'success',
            ]);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect()->route('admin.announcements.index')
            ->with('swal', [
                'title' => 'Deleted',
                'text' => 'Announcement has been removed.',
                'icon' => 'success',
            ]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:tbl_announcements,id',
        ]);

        Announcement::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' announcements have been deleted.'
        ]);
    }
}
