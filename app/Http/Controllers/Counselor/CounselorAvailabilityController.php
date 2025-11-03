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

        // prune past dated rows
        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('date')
            ->where('date', '<', now()->toDateString())
            ->delete();

        // seed defaults if none
        $hasAny = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)->exists();

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
            'blocks.*.end'     => ['required_with:blocks','date_format:H:i','after:blocks.*.start'],
        ]);

        $weekday = (int) $data['weekday'];

        // Normalize & de-dup input (also skip locked lunch 12:00–13:00)
        $uniq = [];
        foreach (($data['blocks'] ?? []) as $b) {
            $s = substr($b['start'],0,5);
            $e = substr($b['end'],0,5);
            if ($s === '12:00' && $e === '13:00') continue; // lunch is locked
            $uniq["$s|$e"] = ['start'=>$s,'end'=>$e];
        }
        $pairs = array_values($uniq);

        return DB::transaction(function() use ($counselorId, $weekday, $pairs) {

            // 1) hard reset: remove ALL recurring blocked rows for this weekday (date IS NULL)
            DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $counselorId)
                ->whereNull('date')
                ->where('weekday', $weekday)
                ->delete();

            // 2) insert the new pattern (if any)
            if (empty($pairs)) {
                return response()->json(['ok' => true, 'cleared' => true], 200);
            }

            $now  = now();
            $rows = [];
            foreach ($pairs as $p) {
                $rows[] = [
                    'counselor_id' => $counselorId,
                    'date'         => null,          // recurring
                    'weekday'      => $weekday,
                    'slot_type'    => 'blocked',
                    'start_time'   => $p['start'],
                    'end_time'     => $p['end'],
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            DB::table('tbl_counselor_availabilities')->insert($rows);

            return response()->json(['ok' => true, 'saved' => count($rows)], 200);
        });
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

    /** Save ALL tiles for a specific date (opens + blocks) with conflict checks */
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

        // block conflict check against appointments
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
                $s = $b['start'];  $e = $b['end'];
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

        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereDate('date', $date)
            ->delete();

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

        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereDate('date', $date)
            ->where('slot_type', 'blocked')
            ->delete();

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

    /** Single window create (unchanged) */
    public function store(Request $request)
    {
        // This branch is for RECURRING AVAILABLE windows (weekday + no date).
        $validated = $request->validate([
            'weekday'    => 'required|integer|min:1|max:7',     // UI already blocks weekends
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'slot_type'  => 'required|in:available,blocked',    // <- fixed (was "block")
        ]);

        // 2) Normalize any legacy value "block" -> "blocked"
        $validated['slot_type'] = ($validated['slot_type'] === 'block')
            ? 'blocked'
            : $validated['slot_type'];      

        // We only merge for "available"
        if ($validated['slot_type'] !== 'available') {
            return response()->json([
                'message' => 'Only "available" recurring windows are supported here.'
            ], 422);
        }

        // Use the counselor-id helper (not the user id)
        $userId  = $this->counselorId();
        $weekday = (int) $validated['weekday'];
        $start   = $validated['start_time'];  // "HH:MM"
        $end     = $validated['end_time'];    // "HH:MM"

        if ($weekday >= 6) {
            return response()->json(['message' => 'Weekends are not allowed.'], 422);
        }

        // Treat adjacency as mergeable (09:00–12:00 + 12:00–16:00 -> 09:00–16:00)
        $overlaps = fn($row) => ($start < $row->end_time) && ($end > $row->start_time);
        $adjacent = fn($row) => ($start == $row->end_time) || ($end == $row->start_time);

        return DB::transaction(function () use ($userId, $weekday, $start, $end, $overlaps, $adjacent) {
            // Existing recurring "available" rows for this weekday
            $rows = Availability::where('counselor_id', $userId)
                ->whereNull('date')
                ->where('weekday', $weekday)
                ->where('slot_type', 'available')
                ->orderBy('start_time')
                ->lockForUpdate()
                ->get();

            // If fully covered by any single row → no change
            foreach ($rows as $r) {
                if ($start >= $r->start_time && $end <= $r->end_time) {
                    return response()->json([
                        'status'  => 'no_change',
                        'message' => 'Already covered by an existing window.',
                    ], 200);
                }
            }

            // Merge bounds with all overlapping/adjacent rows
            $newStart   = $start;
            $newEnd     = $end;
            $toMergeIds = [];

            foreach ($rows as $r) {
                if ($overlaps($r) || $adjacent($r)) {
                    if ($r->start_time < $newStart) $newStart = $r->start_time;
                    if ($r->end_time   > $newEnd)   $newEnd   = $r->end_time;
                    $toMergeIds[] = $r->id;
                }
            }

            if (!empty($toMergeIds)) {
                Availability::whereIn('id', $toMergeIds)->delete();

                Availability::create([
                    'counselor_id' => $userId,
                    'weekday'      => $weekday,
                    'date'         => null,
                    'start_time'   => $newStart,
                    'end_time'     => $newEnd,
                    'slot_type'    => 'available',
                ]);

                return response()->json([
                    'status'  => 'merged',
                    'message' => 'Merged with overlapping windows.',
                    'start'   => $newStart,
                    'end'     => $newEnd,
                ], 200);
            }

            // No overlaps/adjacency → create a new standalone row
            Availability::create([
                'counselor_id' => $userId,
                'weekday'      => $weekday,
                'date'         => null,
                'start_time'   => $start,
                'end_time'     => $end,
                'slot_type'    => 'available',
            ]);

            return response()->json([
                'status'  => 'created',
                'message' => 'Recurring window added.',
                'start'   => $start,
                'end'     => $end,
            ], 201);
        });
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
                    'slot_type'    => $row['slot_type'] ?? $data['slot_type'],
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

    /** SINGLE DELETE — now blocks if there are appointments inside this dated window */
    public function destroy(int $id)
    {
        $counselorId = $this->counselorId();

        $row = DB::table('tbl_counselor_availabilities')
            ->where('id', $id)
            ->where('counselor_id', $counselorId)
            ->first(['id','date','start_time','end_time','slot_type']);

        if (!$row) return back()->with('error', 'Entry not found.');

        // Only enforce on DATED rows
        if (!empty($row->date)) {
            $hasConflict = DB::table('tbl_appointments as a')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $row->date)
                ->whereIn('a.status', ['pending','confirmed','ongoing'])
                // slot is considered booked if starts within [start_time, end_time)
                ->whereRaw('TIME(a.scheduled_at) >= ?', [$row->start_time])
                ->whereRaw('TIME(a.scheduled_at) <  ?', [$row->end_time])
                ->exists();

            if ($hasConflict) {
                // Optional: fetch small list to show like your modal
                $items = DB::table('tbl_appointments as a')
                    ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id')
                    ->where('a.counselor_id', $counselorId)
                    ->whereDate('a.scheduled_at', $row->date)
                    ->whereIn('a.status', ['pending','confirmed','ongoing'])
                    ->whereRaw('TIME(a.scheduled_at) >= ?', [$row->start_time])
                    ->whereRaw('TIME(a.scheduled_at) <  ?', [$row->end_time])
                    ->orderByRaw('TIME(a.scheduled_at)')
                    ->get([
                        'a.id',
                        DB::raw('DATE_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),
                        DB::raw('COALESCE(u.name, "Student") as student'),
                    ]);

                $lis = '';
                foreach ($items as $it) {
                    $url = Route::has('counselor.appointments.show') ? route('counselor.appointments.show', $it->id) : null;
                    $t   = e($it->t12);
                    $nm  = e($it->student);
                    $lis .= $url
                        ? "<li><span class='px-2 py-1 text-xs rounded bg-slate-100 mr-2'>{$t}</span><a class='underline' href='{$url}'>{$nm}</a></li>"
                        : "<li><span class='px-2 py-1 text-xs rounded bg-slate-100 mr-2'>{$t}</span>{$nm}</li>";
                }

                $html = "<p>This date has appointment(s). You can’t delete this window.</p>"
                    . "<ul class='list-disc pl-5 text-sm mt-2'>{$lis}</ul>";

                return back()->with('swal', [
                    'icon'  => 'error',
                    'title' => "Can't delete – booked slot(s)",
                    'html'  => $html,
                    'confirmButtonColor' => '#e11d48',
                ]);
            }
        }

        DB::table('tbl_counselor_availabilities')
            ->where('id', $id)
            ->where('counselor_id', $counselorId)
            ->delete();

        return back()->with('success', 'Entry deleted.');
    }

    /** EDIT / UPDATE (unchanged) */
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

    /**
     * BULK DELETE (DATED): now blocks if ANY selected row has booked appointments.
     * If conflicts exist, nothing is deleted and a SweetAlert error is shown.
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return back()->with('error', 'Please select at least one row to delete.');
        }

        $counselorId = $this->counselorId();

        // fetch selected windows (DATED only)
        $rows = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereIn('id', array_map('intval', $ids))
            ->whereNotNull('date')
            ->get(['id','date','start_time','end_time'])
            ->map(fn($r)=> (array)$r)
            ->values()
            ->all();

        if (empty($rows)) {
            return back()->with('error', 'No valid rows were selected.');
        }

        // Quick fail: if ANY selected row overlaps a booked appointment, block immediately
        $hasAnyConflict = false;
        foreach ($rows as $r) {
            $exists = DB::table('tbl_appointments as a')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $r['date'])
                ->whereIn('a.status', ['pending','confirmed','ongoing'])
                ->whereRaw('TIME(a.scheduled_at) >= ?', [$r['start_time']])
                ->whereRaw('TIME(a.scheduled_at) <  ?', [$r['end_time']])
                ->exists();
            if ($exists) { $hasAnyConflict = true; break; }
        }

        if ($hasAnyConflict) {
            // reuse your nice SweetAlert HTML builder
            $conf = $this->buildDatedConflicts($counselorId, $rows);

            $byDate = [];
            foreach ($conf['items'] as $item) { $byDate[$item['date']][] = $item; }

            $chunks = [];
            foreach ($byDate as $date => $list) {
                $lis = '';
                foreach ($list as $it) {
                    $name = e($it['student']);
                    $time = e($it['t12']);
                    if (!empty($it['href'])) {
                        $lis .= "<li><span class=\"px-2 py-1 text-xs rounded bg-slate-100 mr-2\">{$time}</span><a href=\"{$it['href']}\" class=\"underline\">{$name}</a></li>";
                    } else {
                        $lis .= "<li><span class=\"px-2 py-1 text-xs rounded bg-slate-100 mr-2\">{$time}</span>{$name}</li>";
                    }
                }

                // 👇 pretty date: "November 4, 2025"
                $label = \Carbon\Carbon::parse($date)->format('F j, Y');

                $chunks[] = "<div class=\"text-left mt-2\"><div class=\"font-medium mb-1\">{$label}</div><ul class=\"list-disc pl-5 text-sm\">{$lis}</ul></div>";
            }

            $html = "<p>Some selected dates have appointment(s). You can’t delete those window(s).</p>"
                . implode('', $chunks)
                . "<p class=\"mt-3 text-xs text-slate-500\">Tip: open an appointment in a new tab to manage it.</p>";

            return back()->with('swal', [
                'icon'  => 'error',
                'title' => "Can't delete – booked slot(s)",
                'html'  => $html,
                'confirmButtonColor' => '#e11d48',
            ]);
        }
        /* >>> END INSERT <<< */

        // no conflicts: proceed
        $deleted = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereIn('id', array_map('intval', $ids))
            ->delete();

        if ($deleted > 0) {
            return back()->with('swal', [
                'icon'  => 'success',
                'title' => 'Deleted',
                'text'  => "{$deleted} window".($deleted>1?'s':'').' removed.',
            ]);
        }

        return back()->with('error', 'No rows were deleted.');
    }

    private function seedDefaultRecurring(int $counselorId, array $tiles = null): void
    {
        $tiles = $tiles ?? [
            ['09:00','12:00'],
            ['13:00','16:00'],
        ];

        $now  = now();
        $rows = [];
        foreach ([1,2,3,4,5] as $wd) { // Mon..Fri
            foreach ($tiles as [$s,$e]) {
                $rows[] = [
                    'counselor_id' => $counselorId,
                    'date'         => null,
                    'weekday'      => $wd,
                    'slot_type'    => 'available',
                    'start_time'   => $s,
                    'end_time'     => $e,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::table('tbl_counselor_availabilities')->insert($rows);
        }
    }

    /**
     * Build conflict payload for DATED availability rows.
     * @param int   $counselorId
     * @param array $rows array of ['id','date','start_time','end_time']
     * @return array ['items'=>[ ['date','t24','t12','student','href']... ]]
     */
    private function buildDatedConflicts(int $counselorId, array $rows): array
    {
        if (empty($rows)) return ['items'=>[]];

        // group rows by date to reduce queries
        $byDate = [];
        foreach ($rows as $r) {
            $d = Carbon::parse($r['date'])->format('Y-m-d');
            $byDate[$d][] = $r;
        }

        $items = [];
        foreach ($byDate as $date => $list) {
            // bounds for overlaps (OR of multiple windows)
            $apptsQ = DB::table('tbl_appointments as a')
                ->where('a.counselor_id', $counselorId)
                ->whereDate('a.scheduled_at', $date)
                ->whereIn('a.status', ['pending','confirmed','ongoing']);

            $apptsQ->where(function($q) use ($list){
                foreach ($list as $w) {
                    $s = $w['start_time']; $e = $w['end_time'];
                    $q->orWhere(function($x) use ($s,$e){
                        $x->whereRaw('TIME(a.scheduled_at) >= ?', [$s])
                          ->whereRaw('TIME(a.scheduled_at) <  ?', [$e]);
                    });
                }
            });

            // try to join students/users to fetch a display name if available
            $hasUsers = DB::getSchemaBuilder()->hasTable('tbl_users');
            if ($hasUsers) {
                $apptsQ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id');
            }

            $nameExpr = $hasUsers
                ? DB::raw('COALESCE(u.name, "Student") as student')
                : DB::raw('"Student" as student');

            $appts = $apptsQ
                ->orderByRaw('TIME(a.scheduled_at)')
                ->get([
                    'a.id',
                    DB::raw('DATE(a.scheduled_at) as adate'),
                    DB::raw('TIME_FORMAT(a.scheduled_at, "%H:%i") as t24'),
                    DB::raw('TIME_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),
                    $nameExpr,
                ]);

            foreach ($appts as $a) {
                $items[] = [
                    'date'    => $a->adate,
                    't'       => $a->t24,
                    't12'     => $a->t12,
                    'student' => $a->student,
                    'href'    => Route::has('counselor.appointments.show') ? route('counselor.appointments.show', $a->id) : null,
                ];
            }
        }

        return ['items'=>$items];
    }

    /** Render small HTML snippet for SweetAlert error (single delete) */
    private function renderConflictsHtml(string $date, array $items): string
    {
        if (empty($items)) return '';
        $d = Carbon::parse($date)->format('F j, Y'); 

        $lis = '';
        foreach ($items as $it) {
            $name = e($it['student'] ?? 'Student');
            $time = e($it['t12'] ?? '');
            if (!empty($it['href'])) {
                $lis .= "<li><span class=\"px-2 py-1 text-xs rounded bg-slate-100 mr-2\">{$time}</span><a href=\"{$it['href']}\" target=\"_blank\" class=\"underline\">{$name}</a></li>";
            } else {
                $lis .= "<li><span class=\"px-2 py-1 text-xs rounded bg-slate-100 mr-2\">{$time}</span>{$name}</li>";
            }
        }

        return "<p>There are appointment(s) on <strong>{$d}</strong> inside the time(s) you tried to delete.</p>"
             . "<ul class=\"list-disc pl-5 text-sm mt-2\">{$lis}</ul>"
             . "<p class=\"mt-3 text-xs text-slate-500\">You can’t delete this window while it has booking(s).</p>";
    }

    /** (From earlier) conflict list builder for save actions */
    private function conflictingAppointments(int $counselorId, string $date, array $blocks): array
    {
        $items = [];
        $times24 = [];

        try {
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

            $hasStudents = DB::getSchemaBuilder()->hasTable('tbl_students');
            if ($hasStudents) {
                $q->leftJoin('tbl_students as s', 's.id', '=', 'a.student_id');
            }

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

                $href = Route::has('counselor.appointments.show')
                    ? route('counselor.appointments.show', $r->id)
                    : (Route::has('counselor.appointments.index')
                        ? route('counselor.appointments.index', ['q' => $r->id])
                        : null);

                $items[] = [
                    'id'      => $r->id,
                    't'       => $r->t24,
                    't12'     => $r->t12,
                    'student' => $r->student,
                    'href'    => $href,
                ];
            }

            $times24 = array_values(array_unique($times24));
        } catch (\Throwable $e) {
            Log::warning('conflictingAppointments fallback', ['err'=>$e->getMessage()]);
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
                Log::error('conflictingAppointments hard-fail', ['err'=>$e2->getMessage()]);
            }
        }

        return ['items' => $items, 'times' => $times24];
    }

    /** TABLE view with pagination for both lists */
    public function table(Request $request)
    {
        $counselorId = $this->counselorId();

        DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('date')
            ->where('date', '<', now()->toDateString())
            ->delete();

        $hasAny = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)->exists();

        if (!$hasAny) {
            $now = now();
            $rows = [];
            foreach ([1,2,3,4,5] as $wd) {
                $rows[] = ['counselor_id'=>$counselorId,'date'=>null,'weekday'=>$wd,'slot_type'=>'available','start_time'=>'09:00','end_time'=>'12:00','created_at'=>$now,'updated_at'=>$now];
                $rows[] = ['counselor_id'=>$counselorId,'date'=>null,'weekday'=>$wd,'slot_type'=>'available','start_time'=>'13:00','end_time'=>'16:00','created_at'=>$now,'updated_at'=>$now];
            }
            DB::table('tbl_counselor_availabilities')->insert($rows);
        }

        $recurring = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->orderBy('weekday')->orderBy('start_time')
            ->paginate(10, ['id','weekday','slot_type','start_time','end_time'], 'recurring_page');

        $dated = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNotNull('date')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')->orderBy('start_time')
            ->paginate(10, ['id','date','weekday','slot_type','start_time','end_time'], 'dated_page');

        return view('Counselor_Interface.availability.table', compact('recurring','dated'));
    }

    /**
     * Bulk delete recurring weekday windows (date IS NULL).
     * (No conflict guard here yet; we can extend similarly if needed.)
     */
    public function bulkDestroyRecurring(Request $request)
    {
        $request->validate([
            'ids'   => ['required','array','min:1'],
            'ids.*' => ['integer'],
        ]);

        $counselorId = $this->counselorId();
        $ids = array_unique(array_map('intval', $request->input('ids', [])));

        // Delete selected recurring (date IS NULL)
        $deleted = DB::table('tbl_counselor_availabilities')
            ->whereIn('id', $ids)
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->delete();

        // If no recurring rows remain, regenerate defaults automatically
        $remain = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->count();

        $note = '';
        if ($remain === 0) {
            $this->seedDefaultRecurring($counselorId); // <-- regenerate defaults
            $note = ' • All recurring rows were cleared, defaults were regenerated.';
        }

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Deleted',
            'text'  => "{$deleted} recurring window".($deleted===1?'':'s')." removed.$note",
        ]);
    }

    public function weekdayDisablePrecheck(Request $request)
    {
        $counselorId = $this->counselorId();

        $wd = (int) $request->query('weekday'); // ISO 1..7 (Mon=1)
        if ($wd < 1 || $wd > 7) {
            return response()->json(['conflicts' => [], 'weekday_label' => ''], 200);
        }

        // ISO Mon=1..Sun=7  -> MySQL WEEKDAY() Mon=0..Sun=6
        $mysqlWd = ($wd + 6) % 7;

        $start = Carbon::now()->startOfDay();
        $end   = Carbon::now()->addMonths(2)->endOfDay(); // look-ahead window

        $blockingStatuses = ['pending','confirmed','ongoing'];

        // Pull appointments on that weekday within the window for THIS counselor
        $appts = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id')
            ->where('a.counselor_id', $counselorId)
            ->whereBetween('a.scheduled_at', [$start, $end])
            ->whereRaw('WEEKDAY(a.scheduled_at) = ?', [$mysqlWd])
            ->whereIn('a.status', $blockingStatuses)
            ->orderBy('a.scheduled_at')
            ->get([
                'a.id',
                DB::raw('TIME_FORMAT(a.scheduled_at, "%H:%i") as t24'),
                DB::raw('DATE_FORMAT(a.scheduled_at, "%h:%i %p") as t12'),
                DB::raw('COALESCE(u.name, "Student") as student_name'),
            ]);

        $conflicts = $appts->map(function ($a) {
            return [
                'time'         => $a->t24,
                'time12'       => $a->t12,
                'student_name' => $a->student_name,
                'appt_url'     => route('counselor.appointments.show', $a->id, false),
            ];
        })->values();

        // For dialog header (e.g., “Every Monday”)
        $weekdayLabel = Carbon::now()->startOfWeek()->addDays($wd - 1)->isoFormat('dddd');

        return response()->json([
            'conflicts'     => $conflicts,
            'weekday_label' => "Every {$weekdayLabel}",
        ]);
    }
}
