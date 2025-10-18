<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\HighriskReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HighriskReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status', 'pending')->toString();

        $rows = HighriskReview::query()
            ->when(in_array($status, ['pending','accepted','downgraded'], true),
                fn($q) => $q->where('review_status', $status),
                fn($q) => $q->where('review_status', 'pending')
            )
            ->orderByDesc('occurred_at')
            ->paginate(12);

        return view('Counselor_Interface.highrisk.index', compact('rows'));
    }

    public function show(int $id)
    {
        $item = HighriskReview::findOrFail($id);
        return view('Counselor_Interface.highrisk.show', compact('item'));
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['pending','accepted','downgraded'])],
            'review_notes'  => ['nullable','string','max:2000'],
        ]);

        $item = HighriskReview::findOrFail($id);
        $item->fill($data);
        $item->reviewed_by = Auth::id();     // if your counselor guard uses same user IDs; otherwise map counselor ID
        $item->reviewed_at = now();
        $item->save();

        return back()->with('success', 'Review updated.');
    }
}
