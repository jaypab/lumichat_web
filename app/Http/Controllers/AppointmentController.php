<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Notifications\SimpleDatabaseNotification;
use App\Models\User; 
use App\Support\Notify;

// ⬇️ ADD THESE
use App\Models\Appointment;
use Illuminate\Http\RedirectResponse;

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

    /** Auto mark “no show” if the slot ended + this grace */
    private const NO_SHOW_GRACE_MINUTES = 30;

    /** Mon–Fri only (1=Mon ... 5=Fri with isoWeekday) */
    private const WEEKDAY_MIN = 1; // Monday
    private const WEEKDAY_MAX = 5; // Friday

    /* --------------------------- Utilities --------------------------- */
    private function floorToSlot(Carbon $dt): Carbon
    {
        $m = (int) floor($dt->minute / self::SLOT_MINUTES) * self::SLOT_MINUTES;
        return $dt->copy()->setTime($dt->hour, $m, 0);
    }

    /** Auto-transition past PENDING/CONFIRMED appointments to NO_SHOW (per student). */
    private function autoSweepNoShowsForStudent(int $studentId): void
    {
        // If the end of the slot (scheduled_at + STEP) + grace is already past -> no_show
        $cutoff = now()->subMinutes(self::STEP_MINUTES + self::NO_SHOW_GRACE_MINUTES);

        DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::STUDENT_ACTIVE_STATUSES) // pending/confirmed
            ->where('scheduled_at', '<=', $cutoff)             // slot long finished
            ->update([
                'status'     => 'no_show',
                'updated_at' => now(),
            ]);
    }

    private function apptRepo(): \App\Repositories\Contracts\AppointmentRepositoryInterface
    {
        return app(\App\Repositories\Contracts\AppointmentRepositoryInterface::class);
    }

    /* --------------------------- Booking page ------------------------ */
    public function index()
    {
        if (Auth::check()) {
            $this->autoSweepNoShowsForStudent(Auth::id());

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

    /**
     * Day is disabled for this counselor when resolved availability rows
     * for that date contain **no** 'available' ranges at all.
     */
    private function dayDisabledForCounselor(int $cid, Carbon $date): bool
    {
        $rows = $this->rangesForCounselorOnDate($cid, $date);
        foreach ($rows as $r) {
            if (($r->slot_type ?? 'available') === 'available') {
                return false;
            }
        }
        return true;
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

        $cids = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->where('is_accepting_appointments', 1)
            ->pluck('id')->all();
        if (empty($cids)) return 0;

        $count = 0;
        foreach ($cids as $cid) {
            $cid = (int)$cid;

            // If the counselor disabled this weekday entirely, skip
            if ($this->dayDisabledForCounselor($cid, $date)) {
                continue;
            }

            // Count only if the specific slot is allowed (inside any available AND not inside any blocked)
            if ($this->slotAllowedForCounselor($cid, $slotStart, $slotEnd, $date)) {
                $count++;
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
        $this->autoSweepNoShowsForStudent($userId);

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
    public function slots(Request $request)
    {
        $dateStr = (string) $request->query('date', '');
        if (!$dateStr || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['slots'=>[], 'reason'=>'bad_request', 'message'=>'Provide date=YYYY-MM-DD.'], 400);
        }

        $date  = Carbon::parse($dateStr)->startOfDay();
        $today = now();

        $dowIso = $date->isoWeekday();
        if ($dowIso < 1 || $dowIso > 5) {
            return response()->json(['slots'=>[], 'reason'=>'weekend', 'message'=>'Appointments are available Monday to Friday only.']);
        }

        $cids = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->where('is_accepting_appointments', 1)   // ← ADD THIS
            ->pluck('id')->all();
        if (empty($cids)) {
            return response()->json(['slots'=>[], 'reason'=>'no_counselor', 'message'=>'No counselors are currently available.']);
        }

        // ⬇️ NEW: if all active counselors disabled this weekday, return none (explicit reason)
        $allDisabled = true;
        foreach ($cids as $cid) {
            if (!$this->dayDisabledForCounselor((int)$cid, $date)) { $allDisabled = false; break; }
        }
        if ($allDisabled) {
            return response()->json(['slots'=>[], 'reason'=>'disabled_weekday', 'message'=>'This weekday is disabled by all counselors.']);
        }

        $candidate = [];
        foreach ($cids as $cid) {
            // get both available and blocked rows, but we’ll still step only through the
            // ranges marked available to keep things cheap
            $ranges = $this->rangesForCounselorOnDate($cid, $date)
                ->filter(fn($r) => !isset($r->slot_type) || $r->slot_type === 'available');

            foreach ($ranges as $r) {
                if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') {
                    continue;
                }

                $cursor = Carbon::parse($date->toDateString().' '.$r->start_time)->second(0);
                $end    = Carbon::parse($date->toDateString().' '.$r->end_time)->second(0);

                while ($cursor->lt($end)) {
                    $slot = $this->floorToSlot($cursor);
                    $next = $slot->copy()->addMinutes(self::SLOT_MINUTES);
                    if ($next->gt($end)) break;

                    // skip past times for today
                    if ($date->isSameDay($today) && $slot->lte($today)) {
                        $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
                        continue;
                    }

                    // ✅ NEW: respect blocks/disabled day for this counselor
                    if (!$this->slotAllowedForCounselor((int)$cid, $slot, $next, $date)) {
                        $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
                        continue;
                    }

                    // merge by HH:MM (pooled view)
                    $candidate[$slot->format('H:i')] = $slot->copy();
                    $cursor = $cursor->addMinutes(self::SLOT_MINUTES);
                }
            }
        }

        // If nothing survived (e.g., everyone disabled the weekday), return an explicit reason
        if (empty($candidate)) {
            // you already return 'disabled_weekday' earlier when it’s a full-day disable for all;
            // this covers partial/custom blocks that eliminate all slots for this date.
            return response()->json(['slots' => [], 'reason' => 'no_slots', 'message' => 'No working-hour slots on this day.']);
        }

        $slots = [];
        foreach ($candidate as $hhmm => $slotStart) {
            $remaining = $this->remainingCapacityAt($slotStart);
            $slots[] = [
                'value'     => $hhmm,
                'label'     => $slotStart->format('g:i A'),
                'available' => max(0, (int)$remaining),
            ];
        }
        usort($slots, fn($a,$b)=>strcmp($a['value'],$b['value']));
        return response()->json(['slots'=>$slots]);
    }

    /* --------------------------- Store booking -------------------------- */
    public function store(Request $request)
    {
        $request->validate([
            'date'    => ['required','date_format:Y-m-d'],
            'time'    => ['required','regex:/^\d{2}:\d{2}$/'],
            'consent' => ['accepted'],
        ], [], ['date'=>'date', 'time'=>'time']);

        $studentId = Auth::id();

        // First, auto-sweep past actives to no_show so they won't block booking
        $this->autoSweepNoShowsForStudent($studentId);

        $raw  = Carbon::parse($request->date.' '.$request->time.':00')->second(0);
        $slot = $this->floorToSlot($raw);

        if ($raw->ne($slot)) {
            return back()->withErrors(['time'=>'Please choose a 60-minute step (e.g., 09:00, 10:00).'])->withInput();
        }

        $hasActiveAny = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::STUDENT_ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveAny) {
            return back()->withErrors([
                'error' => 'You already have a pending/confirmed appointment. Complete or cancel it before booking another.',
            ])->withInput();
        }

        $dowIso = $slot->isoWeekday();
        if ($dowIso < 1 || $dowIso > 5) {
            return back()->withErrors(['date'=>'Appointments are available Monday to Friday only.'])->withInput();
        }
        if ($slot->lte(now())) {
            return back()->withErrors(['time'=>'Please choose a future time.'])->withInput();
        }

        $hasSameDay = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereDate('scheduled_at', $slot->toDateString())
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->exists();
        if ($hasSameDay) {
            return back()->withErrors(['date'=>'You already have an appointment on this date.'])->withInput();
        }

        try {
            $newId = null;

            DB::transaction(function () use ($studentId, $slot, &$newId) {
                $remaining = $this->remainingCapacityAt($slot);
                if ($remaining <= 0) {
                    throw new \RuntimeException('FULL');
                }

                // get ID for deep link
                $newId = DB::table('tbl_appointments')->insertGetId([
                    'student_id'   => $studentId,
                    'counselor_id' => null,
                    'scheduled_at' => $slot,
                    'status'       => 'pending',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }, 3);

            // 🔔 notify student (requested)
            $this->notifyUser(
                $studentId,
                'Appointment requested',
                'We received your booking for ' . $slot->format('M d, Y g:i A') . '. You’ll be notified when it’s approved.',
                route('appointment.view', $newId)
            );

            // 🔔 Step A: notify all Admins that assignment is needed
            try {
                $whenNice = $slot->format('M d, Y g:i A');
                // Keep it two-arg to avoid signature mismatches; include the id in the body
                Notify::admins(
                    'New appointment pending',
                    'A new student appointment (ID: '.$newId.') needs counselor assignment for '.$whenNice.'.'
                );
            } catch (\Throwable $e) {
                \Log::warning('Admin notify failed (new pending appt)', [
                    'appointment_id' => $newId,
                    'error'          => $e->getMessage(),
                ]);
            }

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
        // not changed; shows 'no_show' rows too
        $status = (string) $request->query('status', 'all');
        $period = (string) ($request->query('period', $request->query('preoid', 'all')));
        $q      = trim((string) $request->query('q', ''));

        $now = now();

        $query = DB::table('tbl_appointments as a')
        ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
        // ⬇️ pick latest counselor_change_request per appointment
        ->leftJoin(DB::raw('
            (SELECT appointment_id, MAX(id) AS last_id
            FROM counselor_change_requests
            GROUP BY appointment_id) last_cr
        '), 'last_cr.appointment_id', '=', 'a.id')
        ->leftJoin('counselor_change_requests as cr', 'cr.id', '=', 'last_cr.last_id')
        ->select([
            'a.id','a.student_id','a.counselor_id','a.scheduled_at','a.status',
            'c.name as counselor_name','c.email as counselor_email','c.phone as counselor_phone',
            'a.final_note','a.finalized_at',
            // ⬇️ expose to the blade
            DB::raw('cr.status as cr_status'),
            DB::raw('cr.created_at as cr_created_at'),
        ])
        ->where('a.student_id', Auth::id());

        if ($status !== 'all') $query->where('a.status', $status);

        switch ($period) {
            case 'today':      $query->whereDate('a.scheduled_at', $now->toDateString()); break;
            case 'upcoming':   $query->where('a.scheduled_at', '>=', $now); break;
            case 'this_week':  $query->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]); break;
            case 'this_month': $query->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]); break;
            case 'past':       $query->where('a.scheduled_at', '<', $now); break;
            case 'all': default: break;
        }

        if ($q !== '') {
            $query->where(function($w) use ($q) {
                $w->where('c.name', 'like', "%{$q}%")
                  ->orWhereNull('c.id');
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

        $appointments = $query->paginate(10)->withQueryString();

        $view = view('appointment.history', [
            'appointments' => $appointments,
            'status'       => $status,
            'period'       => $period,
            'q'            => $q,
        ]);

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
        // unchanged
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

    public function show(int $id)
    {
        $userId = Auth::id();

        // 1) Load appointment (owned by this student) + assigned counselor info
        $appointment = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select([
                'a.*',
                'c.name  as counselor_name',
                'c.email as counselor_email',
                'c.phone as counselor_phone',
            ])
            ->where('a.id', $id)
            ->where('a.student_id', $userId)
            ->first();

        // 404 kung hindi niya appointment
        abort_unless($appointment, 404);

        // 2) Latest counselor change request (if any) + preferred counselor name
        $changeRequest = DB::table('counselor_change_requests as cr')
            ->leftJoin('tbl_counselors as pc', 'pc.id', '=', 'cr.preference_counselor_id')
            ->select([
                'cr.*',
                // same property names na ginagamit sa blade:
                'cr.preference_counselor_id',
                'pc.name as preferred_counselor_name',
            ])
            ->where('cr.appointment_id', $appointment->id)
            ->orderByDesc('cr.id')
            ->first();

        // 3) Build counselor list for the "Request different counselor" modal
        //    (same slot as this appointment)
        $slotStart = Carbon::parse($appointment->scheduled_at)->second(0);

        $counselors = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        // IDs ng counselors na free sa exact slot na ito
        $freeIds = $this->counselorsFreeAt($slotStart);   // returns array<int>

        // 4) Render student-side show view
        return view('appointment.show', [
            'appointment'   => $appointment,
            'changeRequest' => $changeRequest,   // may ->status, ->preference_counselor_id, ->preferred_counselor_name
            'counselors'    => $counselors,
            'freeIds'       => $freeIds,
        ]);
    }

    /* ------------------------------ Helpers ---------------------------- */

    /**
     * True if the slot is within ANY 'available' range AND NOT inside ANY 'blocked' range
     * after resolving date-specific vs recurring rows.
     * Blocked always overrides available on overlap.
     */
    private function slotAllowedForCounselor(int $cid, Carbon $slotStart, Carbon $slotEnd, Carbon $date): bool
    {
        $rows = $this->rangesForCounselorOnDate($cid, $date);

        $insideAvailable = false;
        foreach ($rows as $r) {
            if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') {
                continue;
            }
            $st = Carbon::parse($date->toDateString().' '.$r->start_time);
            $en = Carbon::parse($date->toDateString().' '.$r->end_time);

            $inside = $slotStart->gte($st) && $slotEnd->lte($en);
            if (!$inside) continue;

            // If any matching row is BLOCKED -> immediately disallow
            if (($r->slot_type ?? 'available') === 'blocked') {
                return false;
            }

            // Mark that at least one matching row is available
            if (($r->slot_type ?? 'available') === 'available') {
                $insideAvailable = true;
            }
        }

        return $insideAvailable;
    }

    /** Return counselor IDs who are free at the exact $scheduledAt slot (date-aware, hourly). */
    private function counselorsFreeAt(Carbon $scheduledAt): array
    {
        $date      = $scheduledAt->copy()->startOfDay();
        $endOfSlot = $scheduledAt->copy()->addMinutes(self::STEP_MINUTES);

        $active = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->where('is_accepting_appointments', 1)
            ->pluck('id')->all();
        if (empty($active)) return [];

        $free = [];
        foreach ($active as $cid) {
            $cid = (int)$cid;

            // Skip fully disabled day
            if ($this->dayDisabledForCounselor($cid, $date)) {
                continue;
            }

            // Blocked overrides available: slot must be allowed
            if (!$this->slotAllowedForCounselor($cid, $scheduledAt, $endOfSlot, $date)) {
                continue;
            }

            // No conflicting appt at exact start
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

        // 🔔 notify student (canceled)
        $this->notifyUser(
            $userId,
            'Appointment canceled',
            'Your appointment for ' . $start->format('M d, Y g:i A') . ' was canceled.',
            route('appointment.history')
        );

        return redirect()
            ->route('appointment.history')
            ->with('swal', [
                'icon'              => 'success',
                'title'             => 'Appointment canceled',
                'text'              => 'Your appointment has been canceled successfully.',
                'confirmButtonText' => 'OK',
            ]);
    }


        private function notifyUser(int $userId, string $title, string $body = '', ?string $url = null): void
    {
        $u = User::find($userId);
        if (!$u) return;

        $u->notify(new SimpleDatabaseNotification($title, $body, $url));
    }

   public function requestCounselorChange(Request $request, int $id)
    {
        $userId = Auth::id();

        // Load appointment the student owns
        $ap = DB::table('tbl_appointments')
            ->where('id', $id)
            ->where('student_id', $userId)
            ->first();

        abort_unless($ap, 404);

        // Must be future and have a counselor (and >24h away)
        $start = Carbon::parse($ap->scheduled_at);
        if (empty($ap->counselor_id) || $start->lte(now()->addHours(24))) {
            return back()->withErrors([
                'error' => 'You can request a change only after a counselor is assigned and at least 24 hours before the session.',
            ]);
        }

        // Reason codes aligned with the Blade <select>
        $allowedReasons = [
            'comfort_mismatch',
            'communication_style',
            'language',
            'conflict',
            'gender_preference',
            'cultural_preference',
            'other',
        ];

        // Base validation
        $data = $request->validate([
            'reason_code'             => ['required', 'in:' . implode(',', $allowedReasons)],
            // optional by default – only required when "other"
            'reason_text'             => ['nullable', 'string', 'max:300'],
            // preferred counselor is required in the UI
            'preference_counselor_id' => ['required', 'integer', 'exists:tbl_counselors,id'],
        ], [], [
            'reason_code'             => 'reason',
            'reason_text'             => 'additional explanation',
            'preference_counselor_id' => 'preferred counselor',
        ]);

        // Conditional requirement for "other"
        $reasonText = (string)($data['reason_text'] ?? '');
        if ($data['reason_code'] === 'other') {
            if (mb_strlen(trim($reasonText)) < 10) {
                return back()
                    ->withErrors([
                        'reason_text' => 'Please describe your reason in at least 10 characters.',
                    ])
                    ->withInput();
            }
        }

        // Sanitize text
        $clean = function (?string $s): string {
            $s = (string) $s;
            $s = strip_tags($s);
            $s = preg_replace('/https?:\/\/\S+/i', '[link removed]', $s);
            $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s);
            $s = preg_replace('/\s{2,}/', ' ', $s);
            return trim($s);
        };
        $data['reason_text'] = $clean($reasonText);

        // Preferred counselor (double-check)
        $prefId = (int) $data['preference_counselor_id'];
        $exists = DB::table('tbl_counselors')->where('id', $prefId)->exists();
        if (!$exists) {
            return back()
                ->withErrors(['preference_counselor_id' => 'Selected counselor is not valid.'])
                ->withInput();
        }

        // Existing open request?
        $existing = DB::table('counselor_change_requests')
            ->where('appointment_id', $ap->id)
            ->where('status', 'requested')
            ->first();

        $payload = [
            'appointment_id'          => $ap->id,
            'requested_by_student_id' => $userId,
            'current_counselor_id'    => $ap->counselor_id,
            'reason_code'             => $data['reason_code'],
            'reason_text'             => $data['reason_text'],
            'preference_counselor_id' => $prefId,
            'status'                  => 'requested',
            'updated_at'              => now(),
        ];

        if ($existing) {
            DB::table('counselor_change_requests')
                ->where('id', $existing->id)
                ->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('counselor_change_requests')->insert($payload);
        }

        // Notify admins (optional)
        try {
            Notify::admins(
                'Counselor change request',
                'Student #' . $userId . ' requested a counselor change for appointment #' . $ap->id . '.'
            );
        } catch (\Throwable $e) {
            \Log::warning('Admin notify failed (counselor change request)', ['e' => $e->getMessage()]);
        }

        return redirect()
            ->route('appointment.view', $ap->id)
            ->with('success', 'Your request was submitted and is now under review. We’ll notify you once the admin decides.');
    }

    public function studentConfirm(Appointment $appointment, Request $request): RedirectResponse
    {
        $user = $request->user();

        // must belong to this student
        if ((int)$appointment->student_id !== (int)$user->id) {
            abort(403);
        }

        // only pending + future can be confirmed
        if ($appointment->status !== 'pending') {
            return back()->with('status', 'Only pending appointments can be confirmed.');
        }

        if (Carbon::parse($appointment->scheduled_at)->isPast()) {
            return back()->with('status', 'This appointment has already started or passed.');
        }

        $appointment->status = 'confirmed';
        // optional columns:
        // $appointment->confirmed_at = now();
        // $appointment->confirmed_by = 'student';
        $appointment->save();

        // ✅ flash success for SweetAlert
        return redirect()
            ->route('appointment.view', $appointment->id)
            ->with('success', 'Your guidance appointment for ' .
                Carbon::parse($appointment->scheduled_at)->format('M d, Y · g:i A') .
                ' has been confirmed.');
    }
}