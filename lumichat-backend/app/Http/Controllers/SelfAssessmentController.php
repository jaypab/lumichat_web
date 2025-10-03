<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelfAssessmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function create(Request $request)
    {
        if (session('sa_done', false)) {
            return redirect()->route('chat.index');
        }
        $moods = ['Happy','Calm','Sad','Anxious','Stressed'];
        return view('self-assessment', compact('moods'));
    }

    public function store(Request $request)
    {
        // Accept JSON or form-data
        $request->validate([
            'mood' => 'required|string|in:Happy,Calm,Sad,Anxious,Stressed',
            'note' => 'nullable|string|max:2000',
        ]);

        $user  = Auth::user();
        $mood  = (string) $request->input('mood');
        $note  = trim((string) $request->input('note', ''));
        $risk  = in_array($mood, ['Sad','Anxious','Stressed'], true) ? 'moderate' : 'low';

        DB::table('tbl_self_assessment')->insert([
            'student_id'                 => $user->id,
            'student_name'               => $user->name ?? ($user->full_name ?? null),
            'assessment_result'          => $note === '' ? $mood : $mood.' (with note)',
            // If you added a "notes" column, also save: 'notes' => $note,
            'initial_diagnosis_result'   => $risk,
            'initial_diagnosis_date_time'=> now(),
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        session(['sa_done' => true]);

        $goto = route('chat.index');
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['ok' => true, 'goto' => $goto]);
        }
        return redirect($goto);
    }

    public function skip(Request $request)
    {
        session(['sa_done' => true]);

        $goto = route('chat.index');
        if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
            return response()->json(['ok' => true, 'goto' => $goto]);
        }
        return redirect($goto);
    }
}
