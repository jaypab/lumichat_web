<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use App\Models\CounselorAvailability as Availability;

class CounselorAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $counselorId = $this->counselorId();

        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('date')
            ->where('date', '<', now()->toDateString())
            ->delete();

        $hasAny = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->exists();

        if (!$hasAny) {
            $now = now();
            $rows = [];
            foreach ([1,2,3,4,5] as $wd) {
                $rows[] = ['counselor_id'=>$counselorId,'date'=>null,'weekday'=>$wd,'slot_type'=>'available','start_time'=>'09:00','end_time'=>'12:00','created_at'=>$now,'updated_at'=>$now];
                $rows[] = ['counselor_id'=>$counselorId,'date'=>null,'weekday'=>$wd,'slot_type'=>'available','start_time'=>'13:00','end_time'=>'16:00','created_at'=>$now,'updated_at'=>$now];
            }
            DB::table('tbl_counselor_availabilities')->insert($rows);
        }

        $recBlocks = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->where('slot_type', 'blocked')
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['weekday','start_time','end_time']);

        $blockedByWeekday = [];
        foreach ($recBlocks as $r) {
            $blockedByWeekday[$r->weekday][] = [substr($r->start_time, 0, 5), substr($r->end_time, 0, 5)];
        }
        return view('Counselor_Interface.availability.index', compact('blockedByWeekday'));
    }

    public function weekdayBlocks(Request $request)
    {
        $counselorId = $this->counselorId();

        $data = $request->validate([
            'weekday'          => ['required','integer','between:1,5'],
            'blocks'           => ['array'],
            'blocks.*.start'   => ['required_with:blocks','date_format:H:i'],
            'blocks.*.end'     => ['required_with:blocks','date_format:H:i','after:start'],
        ]);

        $weekday = (int) $data['weekday'];
        $pairs   = array_values($data['blocks'] ?? []);

        if (empty($pairs)) {
            DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $counselorId)
                ->whereNull('date')
                ->where('weekday', $weekday)
                ->where('slot_type', 'blocked')
                ->delete();
            return response()->json(['ok' => true, 'cleared' => true]);
        }

        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->where('weekday', $weekday)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $p) {
                    $q->orWhere(function ($x) use ($p) {
                        $x->where('start_time', $p['start'])->where('end_time', $p['end']);
                    });
                }
            })
            ->delete();

        $now  = now();
        $rows = [];
        foreach ($pairs as $p) {
            $rows[] = [
                'counselor_id' => $counselorId,
                'date'         => null,
                'weekday'      => $weekday,
                'slot_type'    => 'blocked',
                'start_time'   => $p['start'],
                'end_time'     => $p['end'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        try {
            if (!empty($rows)) {
                DB::table('tbl_counselor_availabilities')->insert($rows);
            }
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') return response()->json(['ok' => false, 'error' => 'duplicate'], 409);
            throw $e;
        }

        return response()->json(['ok' => true]);
    }

    public function getDateBlocks(Request $request)
    {
        $counselorId = $this->counselorId();

        $data = $request->validate(['date' => ['required','date']]);
        $date = Carbon::parse($data['date'])->format('Y-m-d');

        $rows = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereDate('date', $date)
            ->where('slot_type', 'blocked')
            ->orderBy('start_time')
            ->get(['start_time','end_time']);

        $blocks = $rows->map(fn($r) => ['start'=>substr($r->start_time,0,5), 'end'=>substr($r->end_time,0,5)])->values()->all();

        return response()->json(['date' => $date, 'blocks' => $blocks]);
    }

    /** Save ALL tiles for a specific date (defensive conflicts) */
    /**
     * Save ALL tiles for a specific date.
     * - deletes any existing dated rows for that date (both available & blocked)
     * - inserts "opens" as slot_type=available
     * - inserts "blocks" as slot_type=blocked
     */
    public function saveDateWindows(Request $request)
    {
        $counselorId = $this->counselorId();

        $data = $request->validate([
            'date'            => ['required','date'],
            'opens'           => ['array'],
            'opens.*.start'   => ['required_with:opens','date_format:H:i'],
            'opens.*.end'     => ['required_with:opens','date_format:H:i','after:opens.*.start'],
            'blocks'          => ['array'],
            'blocks.*.start'  => ['required_with:blocks','date_format:H:i'],
            'blocks.*.end'    => ['required_with:blocks','date_format:H:i','after:blocks.*.start'],
        ]);

        $date = \Carbon\Carbon::parse($data['date'])->format('Y-m-d');

        // 1) BLOCK CONFLICT CHECK — do not allow blocking any hour that has an appointment
        $blocks = $data['blocks'] ?? [];

        if (!empty($blocks)) {
            // Pull appointments + student name from tbl_users
            $appts = DB::table('tbl_appointments as a')
                ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $date)
                ->whereIn('a.status', ['pending','confirmed','ongoing'])
                ->get([
                    'a.id',
                    DB::raw('TIME_FORMAT(a.scheduled_at, "%H:%i") as t'),        // 24h for comparisons
                    DB::raw('DATE_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),   // 12h for UI
                    DB::raw('COALESCE(u.name, "Student") as student_name'),
                ]);

            $conflicts = [];
            foreach ($blocks as $b) {
                $s = $b['start'];  // 'HH:MM'
                $e = $b['end'];    // 'HH:MM'
                foreach ($appts as $a) {
                    if ($a->t >= $s && $a->t < $e) {
                        $conflicts[] = [
                            'time'         => $a->t,
                            'time12'       => $a->t12,
                            'student_name' => $a->student_name,
                            // open in new tab from the modal
                            'appt_url'     => route('counselor.appointments.show', $a->id, false),
                        ];
                    }
                }
            }

            if (!empty($conflicts)) {
                // 409 with rich conflict payload for the UI
                return response()->json([
                    'ok'        => false,
                    'reason'    => 'has_appointments',
                    'conflicts' => array_values($conflicts),
                    'message'   => 'There are booked appointment(s) inside the time(s) you are trying to disable.',
                ], 409);
            }
        }

        // 2) Clear existing dated rows for that day (both available & blocked)
        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereDate('date', $date)
            ->delete();

        // 3) Insert fresh rows
        $rows = [];
        $now  = now();
        $wd   = \Carbon\Carbon::parse($date)->dayOfWeekIso;

        foreach (($data['opens'] ?? []) as $o) {
            $rows[] = [
                'counselor_id' => $counselorId,
                'date'         => $date,
                'weekday'      => $wd,
                'slot_type'    => 'available',
                'start_time'   => $o['start'],
                'end_time'     => $o['end'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        foreach (($data['blocks'] ?? []) as $b) {
            $rows[] = [
                'counselor_id' => $counselorId,
                'date'         => $date,
                'weekday'      => $wd,
                'slot_type'    => 'blocked',
                'start_time'   => $b['start'],
                'end_time'     => $b['end'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('tbl_counselor_availabilities')->insert($rows);
        }

        return response()->json(['ok' => true, 'saved' => count($rows)]);
    }

    /** Replace blocked tiles for a specific DATE (defensive conflicts) */
    public function saveDateBlocks(Request $request)
    {
        $counselorId = $this->counselorId();

        $data = $request->validate([
            'date'            => ['required','date','after_or_equal:today'],
            'blocks'          => ['array'],
            'blocks.*.start'  => ['required_with:blocks','date_format:H:i'],
            'blocks.*.end'    => ['required_with:blocks','date_format:H:i','after:blocks.*.start'],
        ]);

        $date = \Carbon\Carbon::parse($data['date'])->format('Y-m-d');
        $wd   = \Carbon\Carbon::parse($date)->dayOfWeekIso;
        if ($wd >= 6) {
            return response()->json(['ok' => false, 'message' => 'Weekends are not allowed.'], 422);
        }

        // Conflict check (same as above, with users.name)
        $blocks = $data['blocks'] ?? [];
        if (!empty($blocks)) {
            $appts = DB::table('tbl_appointments as a')
                ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $date)
                ->whereIn('a.status', ['pending','confirmed','ongoing'])
                ->get([
                    'a.id',
                    DB::raw('TIME_FORMAT(a.scheduled_at, "%H:%i") as t'),
                    DB::raw('DATE_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),
                    DB::raw('COALESCE(u.name, "Student") as student_name'),
                ]);

            $conflicts = [];
            foreach ($blocks as $b) {
                $s = $b['start']; $e = $b['end'];
                foreach ($appts as $a) {
                    if ($a->t >= $s && $a->t < $e) {
                        $conflicts[] = [
                            'time'         => $a->t,
                            'time12'       => $a->t12,
                            'student_name' => $a->student_name,
                            'appt_url'     => route('counselor.appointments.show', $a->id, false),
                        ];
                    }
                }
            }

            if (!empty($conflicts)) {
                return response()->json([
                    'ok'        => false,
                    'reason'    => 'has_appointments',
                    'conflicts' => array_values($conflicts),
                    'message'   => 'There are booked appointment(s) inside the time(s) you are trying to disable.',
                ], 409);
            }
        }

        // Remove existing BLOCKED rows for that date
        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereDate('date', $date)
            ->where('slot_type', 'blocked')
            ->delete();

        // Insert new blocked rows
        $rows = [];
        $now  = now();
        foreach ($blocks as $b) {
            $rows[] = [
                'counselor_id' => $counselorId,
                'date'         => $date,
                'weekday'      => $wd,
                'slot_type'    => 'blocked',
                'start_time'   => $b['start'],
                'end_time'     => $b['end'],
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }
        if (!empty($rows)) {
            DB::table('tbl_counselor_availabilities')->insert($rows);
        }

        return response()->json(['ok' => true]);
    }

    /** Single window (unchanged) */
    public function store(Request $request)
    {
        $counselorId = $this->counselorId();

        $rules = [
            'date'       => ['nullable','date','after_or_equal:today'],
            'weekday'    => ['nullable','integer','between:1,5'],
            'start_time' => ['required','date_format:H:i'],
            'end_time'   => ['required','date_format:H:i','after:start_time'],
            'slot_type'  => ['required', Rule::in(['available','blocked'])],
        ];
        $data = $request->validate($rules);

        $date    = !empty($data['date']) ? Carbon::parse($data['date'])->format('Y-m-d') : null;
        $weekday = null;

        if ($date) {
            $w = Carbon::parse($date)->dayOfWeekIso;
            if ($w >= 6) return back()->withErrors(['date' => 'Weekends are not allowed.'])->withInput();
            $weekday = $w;
        } else {
            $weekday = (int) ($data['weekday'] ?? 0);
            if (!$weekday) return back()->withErrors(['date' => 'Pick a future weekday or enable “Repeat weekly”.'])->withInput();
        }

        $exists = DB::table('tbl_counselor_availabilities')->where([
            'counselor_id' => $counselorId,
            'date'         => $date,
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'slot_type'    => $data['slot_type'],
        ])->exists();

        if ($exists) {
            return back()->withInput()->with('swal', [
                'icon'  => 'info',
                'title' => 'Already exists',
                'text'  => 'That exact date/time window is already saved.',
            ]);
        }

        DB::table('tbl_counselor_availabilities')->insert([
            'counselor_id' => $counselorId,
            'date'         => $date,
            'weekday'      => $weekday,
            'slot_type'    => $data['slot_type'],
            'start_time'   => $data['start_time'],
            'end_time'     => $data['end_time'],
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Availability saved',
            'text'  => 'The window has been saved.',
        ]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'slot_type'                 => ['required','in:available,blocked'],
            'start_date'                => ['required','date','after_or_equal:today'],
            'end_date'                  => ['required','date','after_or_equal:start_date'],
            'availability'              => ['required','array','min:1'],
            'availability.*.weekday'    => ['required','integer','between:1,5'],
            'availability.*.start_time' => ['required','date_format:H:i'],
            'availability.*.end_time'   => ['required','date_format:H:i','after:availability.*.start_time'],
        ]);

        foreach ($data['availability'] as $row) {
            if (substr($row['start_time'],3,2) !== '00' || substr($row['end_time'],3,2) !== '00') {
                return back()->withInput()->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Times must be on the hour',
                    'text'  => 'Use whole hours only (e.g., 09:00, 10:00).',
                ]);
            }
        }

        $counselorId = $this->counselorId();
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end   = Carbon::parse($data['end_date'])->endOfDay();

        $byWeekday = collect($data['availability'])->groupBy('weekday');

        $rowsToInsert = [];
        foreach (CarbonPeriod::create($start, '1 day', $end) as $day) {
            $wd = (int) $day->isoWeekday();
            if ($wd >= 6) continue;
            if (!$byWeekday->has($wd)) continue;

            foreach ($byWeekday[$wd] as $row) {
                $rowsToInsert[] = [
                    'counselor_id' => $counselorId,
                    'date'         => $day->toDateString(),
                    'weekday'      => $wd,
                    'slot_type'    => $data['slot_type'],
                    'start_time'   => $row['start_time'],
                    'end_time'     => $row['end_time'],
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }

        if (empty($rowsToInsert)) {
            return back()->with('swal', [
                'icon'  => 'info',
                'title' => 'Nothing to save',
                'text'  => 'No weekdays inside the chosen range matched your selections.',
            ]);
        }

        $dates  = array_values(array_unique(array_column($rowsToInsert, 'date')));
        $starts = array_values(array_unique(array_column($rowsToInsert, 'start_time')));
        $ends   = array_values(array_unique(array_column($rowsToInsert, 'end_time')));
        $type   = $data['slot_type'];

        $existing = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereIn('date', $dates)
            ->whereIn('start_time', $starts)
            ->whereIn('end_time', $ends)
            ->where('slot_type', $type)
            ->get(['date','start_time','end_time','slot_type']);

        $existingKeys = $existing->map(fn($r) => "{$r->date}|{$r->start_time}|{$r->end_time}|{$r->slot_type}")->toArray();

        $filtered = collect($rowsToInsert)->reject(function($r) use ($existingKeys) {
            $key = "{$r['date']}|{$r['start_time']}|{$r['end_time']}|{$r['slot_type']}";
            return in_array($key, $existingKeys, true);
        })->values()->all();

        $skipped = count($rowsToInsert) - count($filtered);

        if (count($filtered) === 0) {
            return back()->with('swal', [
                'icon'  => 'info',
                'title' => 'No new windows',
                'text'  => 'All generated rows already exist. Duplicates were skipped.',
            ]);
        }

        try {
            DB::table('tbl_counselor_availabilities')->insert($filtered);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->with('swal', [
                    'icon'  => 'error',
                    'title' => 'Duplicates detected',
                    'text'  => 'Some of the generated windows already exist and were not saved.',
                ]);
            }
            throw $e;
        }

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Recurring availability saved',
            'text'  => "Saved ".count($filtered)." new window(s)".($skipped ? " • Skipped {$skipped} duplicate(s)" : '').".",
        ]);
    }

    public function destroy(int $id)
    {
        $counselorId = $this->counselorId();

        DB::table('tbl_counselor_availabilities')
            ->where('id', $id)
            ->where('counselor_id', $counselorId)
            ->delete();

        return back()->with('success', 'Entry deleted.');
    }

    public function edit($id)
    {
        $row = Availability::where('counselor_id', Auth::id())->findOrFail($id);
        $weekdayMap = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
        return view('Counselor_Interface.availability.edit', compact('row','weekdayMap'));
    }

    public function update(Request $request, $id)
    {
        $row = Availability::where('counselor_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'slot_type'   => ['required', Rule::in(['available','blocked'])],
            'start_time'  => ['required','date_format:H:i'],
            'end_time'    => ['required','date_format:H:i'],
        ]);

        if (strtotime($validated['end_time']) <= strtotime($validated['start_time'])) {
            return back()->withErrors(['end_time' => 'End time must be after start time.'])->withInput();
        }

        $row->slot_type  = $validated['slot_type'];
        $row->start_time = $validated['start_time'];
        $row->end_time   = $validated['end_time'];
        $row->save();

        return redirect()->route('counselor.availability.index')->with('swal', [
            'icon'  => 'success',
            'title' => 'Updated',
            'text'  => 'Availability window updated successfully.',
            'confirmButtonColor' => '#4f46e5',
        ]);
    }

    public function slots(Request $request, int $id)
    {
        $interval = (int) $request->integer('interval', 30);
        $date     = $request->date('date')?->format('Y-m-d') ?? date('Y-m-d');
        $capacity = max(1, (int) $request->integer('cap', 1));

        $weekday = Carbon::parse($date)->dayOfWeekIso;

        $rows = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $id)
            ->where(function ($q) use ($date, $weekday) {
                $q->whereDate('date', $date)
                  ->orWhere(function ($q) use ($weekday) {
                      $q->whereNull('date')->where('weekday', $weekday);
                  });
            })
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'slot_type']);

        $booked = DB::table('tbl_appointments')
            ->where('counselor_id', $id)
            ->whereDate('scheduled_at', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->selectRaw('TIME_FORMAT(scheduled_at, "%H:%i:00") as slot_time, COUNT(*) as used')
            ->groupBy('slot_time')
            ->pluck('used', 'slot_time');

        $windows = $rows->where('slot_type', 'available');
        $blocks  = $rows->where('slot_type', 'blocked');

        $free = [];
        foreach ($windows as $w) {
            $cursor = strtotime("$date {$w->start_time}");
            $end    = strtotime("$date {$w->end_time}");
            while ($cursor + $interval * 60 <= $end) {
                $slot = date('H:i:00', $cursor);

                $blocked = $blocks->first(fn($b) => ($slot >= $b->start_time && $slot < $b->end_time));
                if ($blocked) { $cursor += $interval * 60; continue; }

                $used = (int)($booked[$slot] ?? 0);
                if ($used < $capacity) {
                    $free[] = ['at'=>$date.' '.$slot,'used'=>$used,'capacity'=>$capacity,'available'=>$capacity-$used];
                }
                $cursor += $interval * 60;
            }
        }

        return response()->json(['date' => $date, 'slots' => $free]);
    }

    private function counselorId(): int
    {
        $u = Auth::user();
        if (!$u) abort(401, 'Unauthenticated.');

        if (isset($u->counselor_id) && $u->counselor_id) {
            $cid = (int) $u->counselor_id;
            $exists = DB::table('tbl_counselors')->where('id', $cid)->exists();
            if ($exists) return $cid;
        }

        if (!empty($u->email)) {
            $cid = DB::table('tbl_counselors')->where('email', $u->email)->value('id');
            if ($cid) return (int) $cid;
        }

        abort(422, 'This account is not linked to a counselor record. Ask admin to set users.counselor_id or match your email in tbl_counselors.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) return back()->with('error', 'Please select at least one row to delete.');

        $counselorId = $this->counselorId();

        $deleted = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereIn('id', $ids)
            ->delete();

        if ($deleted > 0) return back()->with('success', "Deleted {$deleted} window".($deleted>1?'s':'').".");

        return back()->with('error', 'No rows were deleted.');
    }

    /**
     * Build conflict payload defensively.
     * - Works even if tbl_students or name columns differ / don’t exist
     * - Never throws: logs the error and falls back to time-only
     */
    private function conflictingAppointments(int $counselorId, string $date, array $blocks): array
    {
        $items = [];
        $times24 = [];

        try {
            // Build overlap predicate once
            $overlaps = function ($q) use ($blocks) {
                foreach ($blocks as $b) {
                    $s = $b['start']; $e = $b['end'];
                    $q->orWhere(function($x) use ($s,$e){
                        $x->whereRaw('TIME(a.scheduled_at) >= ?', [$s])
                        ->whereRaw('TIME(a.scheduled_at) <  ?', [$e]);
                    });
                }
            };

            $q = DB::table('tbl_appointments as a')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $date)
                ->whereIn('a.status', ['pending','confirmed','ongoing'])
                ->where($overlaps)
                ->orderByRaw('TIME(a.scheduled_at)');

            // Try join to students if present
            $hasStudents = DB::getSchemaBuilder()->hasTable('tbl_students');
            if ($hasStudents) {
                $q->leftJoin('tbl_students as s', 's.id', '=', 'a.student_id');
            }

            // Best-effort name resolution across common schemas
            $nameExpr = $hasStudents
                ? DB::raw("COALESCE(
                        s.full_name,
                        CONCAT_WS(' ', s.first_name, s.last_name),
                        CONCAT_WS(' ', s.fname, s.lname),
                        CONCAT_WS(' ', s.given_name, s.surname),
                        s.name,
                        'Student'
                    ) as student")
                : DB::raw("'Student' as student");

            $rows = $q->get([
                'a.id',
                DB::raw('TIME_FORMAT(a.scheduled_at, "%H:%i") as t24'),
                DB::raw('TIME_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),
                $nameExpr,
            ]);

            foreach ($rows as $r) {
                $times24[] = $r->t24;

                $href = \Illuminate\Support\Facades\Route::has('counselor.appointments.show')
                    ? route('counselor.appointments.show', $r->id)
                    : (Route::has('counselor.appointments.index')
                        ? route('counselor.appointments.index', ['q' => $r->id])
                        : null);

                $items[] = [
                    'id'      => $r->id,
                    't'       => $r->t24, // keep 24h for logic if needed
                    't12'     => $r->t12, // display value
                    'student' => $r->student,
                    'href'    => $href,
                ];
            }

            $times24 = array_values(array_unique($times24));
        } catch (\Throwable $e) {
            \Log::warning('conflictingAppointments fallback', ['err'=>$e->getMessage()]);

            // Fallback to time-only (still 12h for display on the front-end)
            try {
                $overlaps = function ($q) use ($blocks) {
                    foreach ($blocks as $b) {
                        $s=$b['start']; $e=$b['end'];
                        $q->orWhere(function($x) use ($s,$e){
                            $x->whereRaw('TIME(scheduled_at) >= ?', [$s])
                            ->whereRaw('TIME(scheduled_at) <  ?', [$e]);
                        });
                    }
                };

                $times24 = DB::table('tbl_appointments')
                    ->where('counselor_id', $counselorId)
                    ->whereDate('scheduled_at', $date)
                    ->whereIn('status', ['pending','confirmed','ongoing'])
                    ->where($overlaps)
                    ->orderByRaw('TIME(scheduled_at)')
                    ->pluck(DB::raw('TIME_FORMAT(scheduled_at, "%H:%i")'))
                    ->unique()
                    ->values()
                    ->all();

                foreach ($times24 as $t) {
                    $items[] = ['id'=>null,'t'=>$t,'t12'=>date("g:i A", strtotime($t)),'student'=>'Student','href'=>null];
                }
            } catch (\Throwable $e2) {
                \Log::error('conflictingAppointments hard-fail', ['err'=>$e2->getMessage()]);
            }
        }

        return ['items' => $items, 'times' => $times24];
    }
}
