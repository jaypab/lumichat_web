<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /** Minutes per slot (now hourly) */
    private const STEP_MINUTES = 60;
    /** Grid step for building slots (must divide ranges) */
    private const SLOT_MINUTES = 60;

    /** Statuses that block a time from being offered again */
    private const BLOCKING_STATUSES = ['pending', 'confirmed', 'completed'];

    /** Student “active” statuses that block new bookings */
    private const STUDENT_ACTIVE_STATUSES = ['pending', 'confirmed'];

    /** Mon–Fri only (1=Mon ... 5=Fri with isoWeekday) */
    private const WEEKDAY_MIN = 1; // Monday
    private const WEEKDAY_MAX = 5; // Friday

    /* --------------------------- Booking page --------------------------- */
    private function floorToSlot(Carbon $dt): Carbon
    {
        $m = (int) floor($dt->minute / self::SLOT_MINUTES) * self::SLOT_MINUTES;
        return $dt->copy()->setTime($dt->hour, $m, 0);
    }

    private function apptRepo(): \App\Repositories\Contracts\AppointmentRepositoryInterface
    {
        return app(\App\Repositories\Contracts\AppointmentRepositoryInterface::class);
    }

    public function index()
    {
        // If the student already has an active appointment, send to History with a blocking modal
        if (Auth::check()) {
            $hasActive = DB::table('tbl_appointments')
                ->where('student_id', Auth::id())
                ->whereIn('status', self::STUDENT_ACTIVE_STATUSES)
                ->exists();

            if ($hasActive) {
                return redirect()
                    ->route('appointment.history')
                    ->with('swal', [
                        'icon'  => 'warning',
                        'title' => 'You already have an active appointment',
                        'text'  => 'Complete or cancel it before booking another.',
                    ]);
            }
        }

        // No counselor list (pooled availability)
        return view('appointment.index');
    }

    /* ---------- Availability helper: prefer date-specific over recurring ---------- */
    /**
     * Returns availability ranges for a counselor on a specific date.
     * If there are date-specific rows for that date, they are returned.
     * Otherwise, falls back to recurring rows for that weekday.
     */
    private function rangesForCounselorOnDate(int $cid, Carbon $date): Collection
    {
        $dow = $date->isoWeekday(); // 1..7

        // 1) exact date rows (override)
        $dated = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereDate('date', $date->toDateString())
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'slot_type']);

        if ($dated->count() > 0) {
            return $dated;
        }

        // 2) fallback to recurring weekday rows
        return DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereNull('date')
            ->where('weekday', $dow)
            ->orderBy('start_time')
            ->get(['start_time', 'end_time', 'slot_type']);
    }

    // GET /appointment/counselors?date=YYYY-MM-DD&time=HH:MM
    public function counselors(Request $request)
    {
        $request->validate([
            'date' => ['required','date_format:Y-m-d'],
            'time' => ['required','regex:/^\d{2}:\d{2}$/'],
        ]);

        $slot = Carbon::parse($request->date.' '.$request->time.':00')->second(0);

        // weekday + future guards (same rules as store)
        if ($slot->isoWeekday() < 1 || $slot->isoWeekday() > 5) {
            return response()->json(['counselors'=>[], 'reason'=>'weekend', 'message'=>'Weekends are closed.']);
        }
        if ($slot->lte(now())) {
            return response()->json(['counselors'=>[], 'reason'=>'past', 'message'=>'Past time.']);
        }

        // Use the same date-aware helper as slots()
        $freeIds = $this->counselorsFreeAt($slot);

        if (empty($freeIds)) {
            return response()->json(['counselors'=>[]]);
        }

        $rows = DB::table('tbl_counselors')
            ->whereIn('id', $freeIds)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name','email','phone']);

        return response()->json([
            'counselors' => $rows->map(fn($r)=>[
                'id'    => (int)$r->id,
                'name'  => (string)$r->name,
                'email' => (string)$r->email,
                'phone' => (string)($r->phone ?? ''),
            ])->values(),
        ]);
    }

    /* -------------- Optional landing: decide index vs history ----------- */
    private function workingCounselorsAt(Carbon $slotStart): int
    {
        $date    = $slotStart->copy()->startOfDay();
        $slotEnd = $slotStart->copy()->addMinutes(self::SLOT_MINUTES);

        $cids = DB::table('tbl_counselors')->where('is_active', 1)->pluck('id')->all();
        if (empty($cids)) return 0;

        $count = 0;
        foreach ($cids as $cid) {
            $ranges = $this->rangesForCounselorOnDate($cid, $date)
                ->filter(fn($r) => !isset($r->slot_type) || $r->slot_type === 'available');

            foreach ($ranges as $r) {
                if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') {
                    continue;
                }
                $start = Carbon::parse($date->toDateString().' '.$r->start_time);
                $end   = Carbon::parse($date->toDateString().' '.$r->end_time);

                // slot fully inside range (end is exclusive)
                if ($slotStart->gte($start) && $slotEnd->lte($end)) {
                    $count++;
                    break; // no need to check other ranges for the same counselor
                }
            }
        }
        return $count;
    }

    // --- pooled capacity remaining at this exact slot ---
    private function remainingCapacityAt(Carbon $slotStart): int
    {
        $working = $this->workingCounselorsAt($slotStart);

        // Any appointment at this exact time (assigned or not) consumes capacity
        $booked = DB::table('tbl_appointments')
            ->where('scheduled_at', $slotStart)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->count();

        $remain = $working - $booked;
        return $remain > 0 ? $remain : 0;
    }

    public function entrypoint(Request $request)
    {
        $userId = Auth::id();

        $hasActive = DB::table('tbl_appointments')
            ->where('student_id', $userId)
            ->whereIn('status', self::STUDENT_ACTIVE_STATUSES)
            ->exists();

        if ($hasActive) {
            return redirect()
                ->route('appointment.history')
                ->with('swal', [
                    'icon'               => 'warning',
                    'title'              => 'You already have a pending/confirmed appointment',
                    'text'               => 'Complete or cancel it before booking another.',
                    'confirmButtonText'  => 'OK',
                    'allowOutsideClick'  => false,
                    'allowEscapeKey'     => false,
                ]);
        }

        return $this->index();
    }

    /* -------------------------- Slots (AJAX) ---------------------------- */
    // GET /appointment/slots?date=YYYY-MM-DD
    // Returns pooled slots: [{ value:"HH:MM", label:"g:i A", available:int }]
    public function slots(Request $request)
    {
        $dateStr = (string) $request->query('date', '');
        if (!$dateStr || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['slots'=>[], 'reason'=>'bad_request', 'message'=>'Provide date=YYYY-MM-DD.'], 400);
        }

        $date  = Carbon::parse($dateStr)->startOfDay();
        $today = now();

        // Mon..Fri only (isoWeekday 1..5)
        $dowIso = $date->isoWeekday();
        if ($dowIso < 1 || $dowIso > 5) {
            return response()->json(['slots'=>[], 'reason'=>'weekend', 'message'=>'Appointments are available Monday to Friday only.']);
        }

        // Build candidate HH:MM from active counselors’ windows for THIS DATE (date-specific overrides recurring)
        $cids = DB::table('tbl_counselors')->where('is_active', 1)->pluck('id')->all();
        if (empty($cids)) {
            return response()->json(['slots'=>[], 'reason'=>'no_counselor', 'message'=>'No counselors are currently available.']);
        }

        // Collect unique candidate times for the day (now stepping hourly)
        $candidate = [];
        foreach ($cids as $cid) {
            $ranges = $this->rangesForCounselorOnDate($cid, $date)
                ->filter(fn($r) => !isset($r->slot_type) || $r->slot_type === 'available');

            foreach ($ranges as $r) {
                if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') {
                    continue;
                }
                $cursor = Carbon::parse($date->toDateString().' '.$r->start_time)->second(0);
                $end    = Carbon::parse($date->toDateString().' '.$r->end_time)->second(0);

                // walk by 60 minutes
                while ($cursor->lt($end)) {
                    $slot = $this->floorToSlot($cursor);
                    $next = $slot->copy()->addMinutes(self::SLOT_MINUTES);
                    if ($next->gt($end)) break;

                    // Hide past times for today
                    if ($date->isSameDay($today) && $slot->lte($today)) {
                        $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
                        continue;
                    }

                    $candidate[$slot->format('H:i')] = $slot->copy(); // de-duplicate by HH:MM
                    $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
                }
            }
        }

        // Compute remaining pooled capacity per unique candidate time
        $slots = [];
        foreach ($candidate as $hhmm => $slotStart) {
            $remaining = $this->remainingCapacityAt($slotStart);
            $slots[] = [
                'value'     => $hhmm,                 // 'HH:MM'
                'label'     => $slotStart->format('g:i A'),
                'available' => $remaining,            // 0..N (pooled)
            ];
        }

        usort($slots, fn($a,$b)=>strcmp($a['value'],$b['value']));
        return response()->json(['slots'=>$slots]);
    }

    /* --------------------------- Store booking -------------------------- */
    // Student submits date + time + consent; system DOES NOT assign counselor
    // We reserve capacity anonymously; admin assigns counselor later.
    public function store(Request $request)
    {
        $request->validate([
            'date'    => ['required','date_format:Y-m-d'],
            'time'    => ['required','regex:/^\d{2}:\d{2}$/'],
            'consent' => ['accepted'],
        ], [], ['date'=>'date', 'time'=>'time']);

        $studentId = Auth::id();

        // Parse & snap to hourly grid
        $raw  = Carbon::parse($request->date.' '.$request->time.':00')->second(0);
        $slot = $this->floorToSlot($raw); // e.g., 09:17 -> 09:00

        // Reject off-grid inputs (e.g., 09:30) to keep UI honest
        if ($raw->ne($slot)) {
            return back()->withErrors(['time'=>'Please choose a 60-minute step (e.g., 09:00, 10:00).'])->withInput();
        }

        // One ACTIVE appointment at a time (pending/confirmed)
        $hasActiveAny = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::STUDENT_ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveAny) {
            return back()->withErrors([
                'error' => 'You already have a pending/confirmed appointment. Complete or cancel it before booking another.',
            ])->withInput();
        }

        // Mon–Fri only + not past
        $dowIso = $slot->isoWeekday(); // 1..7
        if ($dowIso < 1 || $dowIso > 5) {
            return back()->withErrors(['date'=>'Appointments are available Monday to Friday only.'])->withInput();
        }
        if ($slot->lte(now())) {
            return back()->withErrors(['time'=>'Please choose a future time.'])->withInput();
        }

        // One appointment per day (any blocking status)
        $hasSameDay = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereDate('scheduled_at', $slot->toDateString())
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->exists();
        if ($hasSameDay) {
            return back()->withErrors(['date'=>'You already have an appointment on this date.'])->withInput();
        }

        // RACE-SAFE pooled capacity reservation
        try {
            DB::transaction(function () use ($studentId, $slot) {

                // Re-check remaining capacity **inside** the transaction
                $remaining = $this->remainingCapacityAt($slot);
                if ($remaining <= 0) {
                    throw new \RuntimeException('FULL');
                }

                // Insert a pending appointment WITHOUT counselor (consumes pooled capacity)
                DB::table('tbl_appointments')->insert([
                    'student_id'   => $studentId,
                    'counselor_id' => null,            // assigned later by admin
                    'scheduled_at' => $slot,           // exact slot start
                    'status'       => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }, 3); // retry deadlocks up to 3 times
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'FULL') {
                return back()->withInput()->with('swal', [
                    'icon'  => 'info',
                    'title' => 'Time slot unavailable',
                    'text'  => 'That time just filled up. Please pick another slot.',
                ]);
            }
            throw $e;
        }

        // Success
        return redirect()
            ->route('appointment.history')
            ->with('swal', [
                'icon'  => 'success',
                'title' => 'Appointment booked!',
                'html'  => sprintf(
                    '<div style="text-align:left">
                    <div><b>Date:</b> %s</div>
                    <div><b>Time:</b> %s</div>
                    <div style="margin-top:.25rem;color:#475569"><em>A counselor has not been assigned yet. You’ll be notified once an admin assigns one.</em></div>
                    </div>',
                    e($slot->format('M d, Y')),
                    e($slot->format('g:i A'))
                ),
                'confirmButtonText' => 'OK',
            ]);
    }

    /* ----------------------------- History ----------------------------- */
    public function history(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $period = (string) ($request->query('period', $request->query('preoid', 'all')));
        $q      = trim((string) $request->query('q', ''));

        $now = now();

        $query = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id') // allow null counselor
            ->select([
                'a.id','a.student_id','a.counselor_id','a.scheduled_at','a.status',
                'c.name as counselor_name','c.email as counselor_email','c.phone as counselor_phone',
                'a.final_note','a.finalized_at',
            ])
            ->where('a.student_id', Auth::id());

        if ($status !== 'all') $query->where('a.status', $status);

        switch ($period) {
            case 'today':
                $query->whereDate('a.scheduled_at', $now->toDateString()); break;
            case 'upcoming':
                $query->where('a.scheduled_at', '>=', $now); break;
            case 'this_week':
                $query->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]); break;
            case 'this_month':
                $query->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]); break;
            case 'past':
                $query->where('a.scheduled_at', '<', $now); break;
            case 'all':
            default:
                // no date filter
                break;
        }

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('c.name', 'like', "%{$q}%")
                  ->orWhereNull('c.id'); // include “awaiting assignment”
            });
        }

        // Completed at bottom + period-aware ordering
        $query->orderByRaw("CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END ASC");

        if ($period === 'past') {
            $query->orderBy('a.scheduled_at', 'desc');
        } elseif (in_array($period, ['today','upcoming','this_week','this_month'], true)) {
            $query->orderBy('a.scheduled_at', 'asc');
        } else {
            $query->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN 0 ELSE 1 END", [$now]) // future then past
                  ->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN a.scheduled_at END ASC",  [$now]) // future asc
                  ->orderByRaw("CASE WHEN a.scheduled_at <  ? THEN a.scheduled_at END DESC", [$now]) // past desc
                  ->orderByRaw("CASE WHEN a.status = 'completed' THEN a.scheduled_at END DESC");      // completed desc at bottom
        }

        $appointments = $query->paginate(10)->withQueryString();

        // Build the view first
        $view = view('appointment.history', [
            'appointments' => $appointments,
            'status'       => $status,
            'period'       => $period,
            'q'            => $q,
        ]);

        // Then stamp “seen”
        \App\Models\User::where('id', Auth::id())->update([
            'last_seen_appt_at' => now(),
        ]);

        return $view;
    }

    public function unseenCount(Request $request)
    {
        $user   = Auth::user();
        if (!$user) return response()->json(['count' => 0]);

        $last = $user->last_seen_appt_at ?? Carbon::createFromTimestamp(0);

        $count = DB::table('tbl_appointments')
            ->where('student_id', $user->id)
            ->where('updated_at', '>', $last)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function exportHistoryPdf(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $period = (string) ($request->query('period', $request->query('preoid', 'all')));
        $q      = trim((string) $request->query('q', ''));
        $now    = now();

        $query = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select([
                'a.id','a.student_id','a.counselor_id','a.scheduled_at','a.status',
                'c.name as counselor_name','c.email as counselor_email','c.phone as counselor_phone',
                'a.final_note','a.finalized_at',
            ])
            ->where('a.student_id', Auth::id());

        if ($status !== 'all') $query->where('a.status', $status);

        switch ($period) {
            case 'today':      $query->whereDate('a.scheduled_at', $now->toDateString()); break;
            case 'upcoming':   $query->where('a.scheduled_at', '>=', $now); break;
            case 'this_week':  $query->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]); break;
            case 'this_month': $query->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]); break;
            case 'past':       $query->where('a.scheduled_at', '<', $now); break;
            default: /* all */ break;
        }

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('c.name', 'like', "%{$q}%")->orWhereNull('c.id');
            });
        }

        $query->orderByRaw("CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END ASC");
        if ($period === 'past') {
            $query->orderBy('a.scheduled_at', 'desc');
        } elseif (in_array($period, ['today','upcoming','this_week','this_month'], true)) {
            $query->orderBy('a.scheduled_at', 'asc');
        } else {
            $query->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN 0 ELSE 1 END", [$now])
                  ->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN a.scheduled_at END ASC",  [$now])
                  ->orderByRaw("CASE WHEN a.scheduled_at <  ? THEN a.scheduled_at END DESC", [$now])
                  ->orderByRaw("CASE WHEN a.status = 'completed' THEN a.scheduled_at END DESC");
        }

        $appointments = $query->get();

        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 96,
            'isPhpEnabled'         => true,
        ]);

        $pdf->loadView('appointment.history-pdf', [
            'appointments' => $appointments,
            'status'       => $status,
            'period'       => $period,
            'q'            => $q,
            'generatedAt'  => now()->format('Y-m-d H:i'),
            'logoData'     => $logoData,
        ]);

        $filename = 'My_Appointments_' . now()->format('Ymd_His') . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }
        return $pdf->stream($filename);
    }

    public function exportShowPdf(Request $request, int $id)
    {
        $userId = Auth::id();

        $appointment = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select('a.*','c.name as counselor_name','c.email as counselor_email','c.phone as counselor_phone')
            ->where('a.id', $id)
            ->where('a.student_id', $userId)
            ->first();

        abort_unless($appointment, 404);

        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 96,
            'isPhpEnabled'         => true,
        ]);

        $pdf->loadView('appointment.pdf-show', [
            'appointment' => $appointment,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        $filename = 'Appointment_' . $appointment->id . '_' . now()->format('Ymd_His') . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }
        return $pdf->stream($filename);
    }

    public function show($id)
    {
        $userId = Auth::id();

        $appointment = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select(
                'a.*',
                'c.name  as counselor_name',
                'c.email as counselor_email',
                'c.phone as counselor_phone'
            )
            ->where('a.id', $id)
            ->where('a.student_id', $userId)
            ->first();

        abort_unless($appointment, 404);

        return view('appointment.show', compact('appointment'));
    }

    /* ------------------------------ Helpers ---------------------------- */

    /** Return counselor IDs who are free at the exact $scheduledAt slot (date-aware, hourly). */
    private function counselorsFreeAt(Carbon $scheduledAt): array
    {
        $date      = $scheduledAt->copy()->startOfDay();
        $endOfSlot = $scheduledAt->copy()->addMinutes(self::STEP_MINUTES);

        $active = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->pluck('id')->all();
        if (empty($active)) return [];

        $free = [];
        foreach ($active as $cid) {
            // availability that fits this slot (date-specific first, else recurring)
            $ranges = $this->rangesForCounselorOnDate($cid, $date)
                ->filter(fn($r) => !isset($r->slot_type) || $r->slot_type === 'available');

            $fits = false;
            foreach ($ranges as $r) {
                if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') {
                    continue;
                }
                $start = Carbon::parse($date->toDateString().' '.$r->start_time);
                $end   = Carbon::parse($date->toDateString().' '.$r->end_time);
                if ($scheduledAt->gte($start) && $endOfSlot->lte($end)) { $fits = true; break; }
            }
            if (!$fits) continue;

            // not booked at that exact time
            $taken = DB::table('tbl_appointments')
                ->where('counselor_id', $cid)
                ->where('scheduled_at', $scheduledAt)
                ->whereIn('status', self::BLOCKING_STATUSES)
                ->exists();

            if (!$taken) $free[] = $cid;
        }
        return $free;
    }

    /* --------------------------- Cancel (student) ---------------------- */
    public function cancel($id, Request $request)
    {
        $userId = Auth::id();

        $ap = DB::table('tbl_appointments')
            ->where('id', $id)
            ->where('student_id', $userId)
            ->first();

        if (!$ap) {
            return back()->withErrors(['error' => 'Appointment not found.']);
        }

        // Only pending + future can be canceled
        if ($ap->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending appointments can be canceled.']);
        }

        $now   = now();
        $start = Carbon::parse($ap->scheduled_at);
        if ($start->lte($now)) {
            return back()->withErrors(['error' => 'This appointment has already started/passed and cannot be canceled.']);
        }

        DB::table('tbl_appointments')
            ->where('id', $ap->id)
            ->update([
                'status'     => 'canceled',
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('appointment.history')
            ->with('swal', [
                'icon'              => 'success',
                'title'             => 'Appointment canceled',
                'text'              => 'Your appointment has been canceled successfully.',
                'confirmButtonText' => 'OK',
            ]);
    }
}
