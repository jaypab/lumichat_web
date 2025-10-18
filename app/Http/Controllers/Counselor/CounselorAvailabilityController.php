<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CounselorAvailabilityController extends Controller
{
    /** List + form */
    public function index(Request $request)
    {
        $counselorId = $this->counselorId();

        $entries = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->orderBy('weekday')       // 1..7 (Mon..Sun)
            ->orderBy('start_time')    // earliest first
            ->paginate(12);

        return view('Counselor_Interface.availability.index', compact('entries'));
    }

    // STORE – only what exists in your table
    public function store(Request $request)
    {
        $counselorId = $this->counselorId();

        $request->validate([
            'weekday'     => ['required','integer','between:1,7'],
            'start_time'  => ['required','date_format:H:i'],
            'end_time'    => ['required','date_format:H:i','after:start_time'],
        ]);

        DB::table('tbl_counselor_availabilities')->insert([
            'counselor_id' => $counselorId,
            'weekday'      => (int) $request->weekday,
            'start_time'   => $request->start_time,
            'end_time'     => $request->end_time,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return back()->with('success', 'Availability saved.');
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

    /** Map the logged-in user to a counselor id. Adjust to your auth model. */
    private function counselorId(): int
    {
        // If counselors log in with tbl_users and role='counselor':
        $u = Auth::user();
        if ($u && (string)$u->role === 'counselor') {
            // if you mirror id to tbl_counselors, replace with a join/lookup
            return (int)($u->counselor_id ?? $u->id);
        }
        // Or: return the counselor row tied to this account/email
        $id = DB::table('tbl_counselors')->where('email',$u->email ?? '')->value('id');
        return (int)($id ?: 0);
    }
}
