<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CounselorAvailabilityController extends Controller
{
    /** List + form */
    public function index(Request $request)
    {
        $counselorId = $this->counselorId();

        $entries = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->orderByRaw('CASE WHEN date IS NULL THEN 1 ELSE 0 END') // dated first
            ->orderBy('date')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->paginate(12);

        return view('Counselor_Interface.availability.index', compact('entries'));
    }

// Store date-specific OR recurring weekday window
public function store(Request $request)
{
    $counselorId = $this->counselorId();

    // date must be today or future; weekday allowed only Mon..Fri
    $rules = [
        'date'       => ['nullable','date','after_or_equal:today'],
        'weekday'    => ['nullable','integer','between:1,5'], // 1=Mon..5=Fri
        'start_time' => ['required','date_format:H:i'],
        'end_time'   => ['required','date_format:H:i','after:start_time'],
        'slot_type'  => ['required', Rule::in(['available','blocked'])],
    ];
    $data = $request->validate($rules);

    // Enforce 1h steps (minutes = 00; duration >= 1h and whole hours)
    [$sh,$sm] = array_map('intval', explode(':', $data['start_time']));
    [$eh,$em] = array_map('intval', explode(':', $data['end_time']));
    if ($sm !== 0 || $em !== 0) {
        return back()->withErrors(['start_time' => 'Times must be on the hour (e.g., 09:00, 10:00).'])->withInput();
    }
    $mins = ($eh*60 + $em) - ($sh*60 + $sm);
    if ($mins < 60 || ($mins % 60) !== 0) {
        return back()->withErrors(['end_time' => 'Duration must be a whole number of hours (minimum 1 hour).'])->withInput();
    }

    // Normalize inputs:
    // - If a DATE is provided -> compute weekday from that date (Mon=1..Sun=7), keep date.
    // - If no date -> treat as RECURRING, use provided weekday (Mon..Fri), keep date = NULL.
    $date    = !empty($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : null;
    $weekday = null;

    if ($date) {
        // guard (weekends are blocked in UI but keep a server check)
        $w = Carbon::parse($date)->dayOfWeekIso; // 1..7
        if ($w >= 6) {
            return back()->withErrors(['date' => 'Weekends are not allowed.'])->withInput();
        }
        $weekday = $w; // <-- always store weekday for dated rows too
    } else {
        $weekday = (int) ($data['weekday'] ?? 0);
        if (!$weekday) {
            return back()->withErrors(['date' => 'Pick a future weekday or enable “Repeat weekly” (Mon–Fri only).'])->withInput();
        }
    }

    // Prevent overlaps in the proper scope (dated vs recurring)
    $overlapQuery = DB::table('tbl_counselor_availabilities')
        ->where('counselor_id', $counselorId)
        ->when($date,
            fn($q) => $q->whereDate('date', $date),                           // same specific date
            fn($q) => $q->whereNull('date')->where('weekday', $weekday)       // same recurring weekday
        )
        ->where(function($q) use ($data){
            $q->whereBetween('start_time', [$data['start_time'], $data['end_time']])
              ->orWhereBetween('end_time',   [$data['start_time'], $data['end_time']])
              ->orWhere(function($q) use ($data){
                  $q->where('start_time', '<=', $data['start_time'])
                    ->where('end_time',   '>=', $data['end_time']);
              });
        });

    if ($overlapQuery->exists()) {
        return back()->withErrors(['start_time' => 'Overlaps an existing window for that date/weekday.'])->withInput();
    }

    DB::table('tbl_counselor_availabilities')->insert([
        'counselor_id' => $counselorId,
        'date'         => $date,       // NULL for recurring; yyyy-mm-dd for dated
        'weekday'      => $weekday,    // <-- now always populated (1..7)
        'slot_type'    => $data['slot_type'],
        'start_time'   => $data['start_time'],
        'end_time'     => $data['end_time'],
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    return back()->with('success','Availability saved.');
}

    /** Delete availability */
    public function destroy(int $id)
    {
        $counselorId = $this->counselorId();

        DB::table('tbl_counselor_availabilities')
            ->where('id', $id)
            ->where('counselor_id', $counselorId)
            ->delete();

        return back()->with('success','Entry deleted.');
    }
    /**
     * Slots API used by the student-side picker.
     * Returns free 30-min slots within counselor windows, minus:
     *  - unavailable blocks
     *  - existing appointments (status: pending/confirmed)
     */
    public function slots(Request $request, int $id)
    {
        $interval = (int) $request->integer('interval', 30);          // minutes
        $date     = $request->date('date')?->format('Y-m-d') ?? date('Y-m-d');
        $capacity = max(1, (int) $request->integer('cap', 1));        // students per slot

        // pull both date-specific rows AND recurring rows for this weekday
        $weekday = \Carbon\Carbon::parse($date)->dayOfWeekIso;        // 1..7

        $rows = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $id)
            ->where(function($q) use ($date, $weekday){
                $q->whereDate('date', $date)        // dated entries for that day
                ->orWhere(function($q) use ($weekday){
                    $q->whereNull('date')         // recurring
                        ->where('weekday', $weekday);
                });
            })
            ->orderBy('start_time')
            ->get(['start_time','end_time','slot_type']);

        // existing bookings
        $booked = DB::table('tbl_appointments')
            ->where('counselor_id', $id)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['pending','confirmed'])
            ->selectRaw('TIME_FORMAT(scheduled_at, "%H:%i:00") as slot_time, COUNT(*) as used')
            ->groupBy('slot_time')
            ->pluck('used','slot_time');

        $windows = $rows->where('slot_type','available');
        $blocks  = $rows->where('slot_type','blocked');

        $free = [];
        foreach ($windows as $w) {
            $cursor = strtotime("$date {$w->start_time}");
            $end    = strtotime("$date {$w->end_time}");
            while ($cursor + $interval*60 <= $end) {
                $slot = date('H:i:00', $cursor);

                // blocked?
                $blocked = $blocks->first(function($b) use ($slot){
                    return ($slot >= $b->start_time && $slot < $b->end_time);
                });
                if ($blocked) { $cursor += $interval*60; continue; }

                // capacity left?
                $used = (int)($booked[$slot] ?? 0);
                if ($used < $capacity) {
                    $free[] = [
                        'at'        => $date . ' ' . $slot,
                        'used'      => $used,
                        'capacity'  => $capacity,
                        'available' => $capacity - $used,
                    ];
                }
                $cursor += $interval*60;
            }
        }

        return response()->json(['date'=>$date, 'slots'=>$free]);
    }

private function counselorId(): int
{
    $u = Auth::user();
    if (!$u) { abort(401, 'Unauthenticated.'); }

    // 1) Prefer an explicit linkage on users.counselor_id
    if (isset($u->counselor_id) && $u->counselor_id) {
        $cid = (int) $u->counselor_id;
        $exists = DB::table('tbl_counselors')->where('id', $cid)->exists();
        if ($exists) return $cid;
    }

    // 2) Fallback via email mapping (only if you keep counselor emails in sync)
    if (!empty($u->email)) {
        $cid = DB::table('tbl_counselors')->where('email', $u->email)->value('id');
        if ($cid) return (int)$cid;
    }

    // 3) Hard stop: no counselor record linked to this account
    abort(422, 'This account is not linked to a counselor record. Ask admin to set users.counselor_id or match your email in tbl_counselors.');
}
}