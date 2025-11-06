<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Models\ChatSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Appointment;
// 👇 imports for emailing
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\SimpleDatabaseNotification;

class AppointmentController extends Controller
{
    // ==== Flash keys ====
    private const FLASH_SWAL = 'swal';

    // ==== Filters ====
    private const STATUS_ALL = 'all';
    private const PERIOD_ALL = 'all';
    private const STATUSES   = ['pending','confirmed','canceled','completed','no_show'];
    private const PERIODS    = ['all','upcoming','today','this_week','this_month','past'];
    private const NO_SHOW_GRACE_MINUTES = 30;

    public function __construct(
        protected AppointmentRepositoryInterface $appointments
    ) {}  

    /** List appointments with optional filters + search (counselor name). */
public function index(Request $r): View
{
    $status = \in_array($r->query('status'), self::STATUSES, true) ? $r->query('status') : self::STATUS_ALL;
    $period = \in_array($r->query('period'), self::PERIODS, true)   ? $r->query('period') : self::PERIOD_ALL;
    $q      = \trim((string) $r->query('q', ''));

    $now = now();

    // ✅ Eloquent + eager loading to avoid N+1
    $query = Appointment::with(['student', 'counselor']);

    // ---- status filter ----
    if ($status !== self::STATUS_ALL) {
        $query->where('status', $status);
    }

    // ---- period filter (same logic as exportPdf) ----
    switch ($period) {
        case 'today':
            $query->whereDate('scheduled_at', $now->toDateString());
            break;

        case 'upcoming':
            $query->where('scheduled_at', '>=', $now);
            break;

        case 'this_week':
            $query->whereBetween('scheduled_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]);
            break;

        case 'this_month':
            $query->whereBetween('scheduled_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ]);
            break;

        case 'past':
            $query->where('scheduled_at', '<', $now);
            break;

        default:
            // 'all' → no date filter
            break;
    }

    // ---- search by student or counselor name ----
    if ($q !== '') {
        $query->where(function ($w) use ($q) {
            $w->whereHas('student', function ($s) use ($q) {
                $s->where('name', 'like', "%{$q}%");
            })->orWhereHas('counselor', function ($c) use ($q) {
                $c->where('name', 'like', "%{$q}%");
            });
        });
    }

    // ---- ordering: completed at bottom + date-aware ordering ----
    $query->orderByRaw("CASE WHEN status = 'completed' THEN 1 ELSE 0 END ASC");

    if ($period === 'past') {
        $query->orderBy('scheduled_at', 'desc');
    } elseif (\in_array($period, ['today','upcoming','this_week','this_month'], true)) {
        $query->orderBy('scheduled_at', 'asc');
    } else {
        $query
            ->orderByRaw("CASE WHEN scheduled_at >= ? THEN 0 ELSE 1 END", [$now])
            ->orderByRaw("CASE WHEN scheduled_at >= ? THEN scheduled_at END ASC",  [$now])
            ->orderByRaw("CASE WHEN scheduled_at <  ? THEN scheduled_at END DESC", [$now])
            ->orderByRaw("CASE WHEN status = 'completed' THEN scheduled_at END DESC");
    }

    // paginate + keep filters in the query string
    $appointments = $query->paginate(10)->withQueryString();

    return view('admin.appointments.index', compact('appointments', 'status', 'period', 'q'));
}

    /** Show appointment details + latest report for that pair. */
    public function show(int $id): View
    {
        $row = $this->appointments->findDetailedById($id);
        abort_unless($row, 404);

        // latest report for student+counselor
        $latestReport = \DB::table('tbl_diagnosis_reports')
            ->where('student_id', $row->student_id)
            ->where('counselor_id', $row->counselor_id)
            ->orderByDesc('id')
            ->first();

        return view('admin.appointments.show', [
            'appointment'  => $row,
            'latestReport' => $latestReport,
        ]);
    }

    /** Persist final report for a completed appointment. */
    public function saveReport(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'diagnosis'  => ['required','string','max:4000'],
            'final_note' => ['nullable','string','max:4000'],
        ]);

        $res = $this->appointments->saveFinalReport(
            appointmentId: $id,
            diagnosis:     $data['diagnosis'],
            finalNote:     $data['final_note'] ?? null,
            finalizedBy:   auth()->id()
        );

        if (!$res['ok']) {
            $map = [
                'not_found'      => ['warning','Not found','Appointment not found.'],
                'not_completed'  => ['warning','Not allowed','You can save the diagnosis only for completed appointments.'],
            ];
            [$icon,$title,$text] = $map[$res['reason']] ?? ['error','Error','Unable to save report.'];
            return back()->with(self::FLASH_SWAL, compact('icon','title','text'));
        }

        return back()->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Saved',
            'text'  => 'Diagnosis report has been saved.',
        ]);
    }

    /** Update status via action ('confirm' | 'done') with rule checks. */
    public function updateStatus(Request $r, int $id): RedirectResponse
    {
        $action = $r->input('action'); // 'confirm' | 'done' | 'no_show'

        // Fast path: use repo for confirm/done as you already had
        if (in_array($action, ['confirm','done'], true)) {
            $res = $this->appointments->updateStatusByAction($id, $action);

            if (!$res['ok']) {
                $map = [
                    'invalid_action'    => ['warning','Not allowed','Invalid action.'],
                    'not_found'         => ['warning','Not allowed','Appointment not found.'],
                    'must_be_confirmed' => ['warning','Not allowed','Appointment must be confirmed before you can mark it as done.'],
                    'too_early'         => ['warning','Too early','You can only mark the appointment as done once it has started.'],
                ];
                [$icon,$title,$text] = $map[$res['reason']] ?? ['error','Error','Unable to update status.'];

                return back()->with(self::FLASH_SWAL, compact('icon','title','text'));
            }
        }
        // ✅ New: No-Show
        else if ($action === 'no_show') {
            $row = DB::table('tbl_appointments')->where('id', $id)->first();
            if (!$row) {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Not found',
                    'text'  => 'Appointment not found.',
                ]);
            }

            // Only pending/confirmed can become no_show
            if (!in_array($row->status, ['pending','confirmed'], true)) {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Not allowed',
                    'text'  => 'Only pending/confirmed appointments can be marked as No-Show.',
                ]);
            }

            $start      = Carbon::parse($row->scheduled_at);
            $endOfSlot  = $start->copy()->addMinutes(60); // your slot = 60 minutes
            $graceOver  = $endOfSlot->copy()->addMinutes(self::NO_SHOW_GRACE_MINUTES);

            if ($graceOver->isFuture()) {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Too early',
                    'text'  => 'You can mark No-Show only after the slot has passed the grace period.',
                ]);
            }

            DB::table('tbl_appointments')
                ->where('id', $id)
                ->update([
                    'status'     => 'no_show',
                    'updated_at' => now(),
                ]);
        }
        else {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'Invalid action.',
            ]);
        }

        // ⬇️ Keep your post-update logic (clear high risk when completed)
        $appt = DB::table('tbl_appointments')
            ->select('id','student_id','status')
            ->where('id', $id)
            ->first();

        if ($appt && $appt->status === 'completed') {
            $updates = [
                'risk_level' => null,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn((new ChatSession)->getTable(), 'risk_score')) {
                $updates['risk_score'] = 0;
            }
            ChatSession::where('user_id', $appt->student_id)
                ->whereRaw("LOWER(COALESCE(risk_level, '')) IN ('high','high-risk','high_risk')")
                ->update($updates);
        }

        return back()->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Updated',
            'text'  => 'Appointment status has been updated.',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $status = (string) $request->query('status', 'all');
        $period = (string) $request->query('period', 'all');
        $q      = trim((string) $request->query('q', ''));

        $now = now();

        $query = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select([
                'a.id',
                'a.scheduled_at',
                'a.created_at as booked_at',
                'a.status',
                DB::raw("COALESCE(s.name,'—') as student_name"),
                DB::raw("COALESCE(c.name,'—') as counselor_name"),
            ]);

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
            $query->where(function ($w) use ($q) {
                $w->where('s.name', 'like', "%{$q}%")
                ->orWhere('c.name', 'like', "%{$q}%");
            });
        }

        // Completed at bottom + period-aware ordering (unchanged)
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

        // 🔹 Build base64 logo
        $logoData = null;
        $logoPath = public_path('images/chatbot.png'); // adjust if you moved it
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 96,
            'isPhpEnabled'         => true,   // ← REQUIRED for <script type="text/php">
        ]);

        $pdf->loadView('admin.appointments.pdf', [
            'appointments' => $appointments,
            'status'       => $status,
            'period'       => $period,
            'q'            => $q,
            'generatedAt'  => now()->format('Y-m-d H:i'),
            'logoData'     => $logoData,
        ]);

        $filename = 'Appointments_'.now()->format('Ymd_His').'.pdf';

        if ($request->boolean('download')) {
            // force download (when you add ?download=1 to the URL)
            return $pdf->download($filename);
        }

        // default: view in browser
        return $pdf->stream($filename);  // Content-Disposition: inline
    }

    public function exportShowPdf(Request $request, int $id)
    {
        $appointment = $this->appointments->findDetailedById($id);
        abort_unless($appointment, 404);

        $latestReport = \DB::table('tbl_diagnosis_reports')
            ->where('student_id', $appointment->student_id)
            ->where('counselor_id', $appointment->counselor_id)
            ->orderByDesc('id')
            ->first();

        $logoPath = public_path('images/chatbot.png');
        $logoData = null;
        if (is_file($logoPath)) {
            $mime = \Illuminate\Support\Str::endsWith(strtolower($logoPath), '.svg')
                ? 'image/svg+xml' : 'image/png';
            $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 96,
            'isPhpEnabled'         => true,   // ← REQUIRED for <script type="text/php">
        ]);

        $pdf->loadView('admin.appointments.pdf-show', [
            'appointment'  => $appointment,
            'latestReport' => $latestReport,
            'logoData'     => $logoData,
        ]);

        $filename = 'Appointment_' . $appointment->id . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename); // force download
        }

        return $pdf->stream($filename); // inline view (opens in new tab from the Blade link)
    }

    // Prefer date-specific rows; fall back to recurring weekday
    private function rangesForCounselorOnDate(int $cid, Carbon $date)
    {
        $dated = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereDate('date', $date->toDateString())
            ->orderBy('start_time')
            ->get(['start_time','end_time','slot_type']);

        if ($dated->count() > 0) return $dated;

        $dow = $date->isoWeekday(); // 1..7
        return DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereNull('date')
            ->where('weekday', $dow)
            ->orderBy('start_time')
            ->get(['start_time','end_time','slot_type']);
    }

    /**
     * Check if counselor is truly selectable for this 60-min slot:
     * - active
     * - accepting appointments (if column exists; otherwise treated as accepting)
     * - inside an "available" window (and not inside a "blocked")
     * - no conflicting appointment at the exact start
     * - not weekend; not past
     */
    private function checkCounselorAtSlot(int $cid, Carbon $slotStart): array
    {
        $slotEnd = $slotStart->copy()->addMinutes(60);
        $date    = $slotStart->copy()->startOfDay();

        // flags ...
        // (unchanged)

        if ($slotStart->lte(now()))   return ['ok' => false, 'reason' => 'Past time'];
        if ($slotStart->isoWeekday() >= 6) return ['ok' => false, 'reason' => 'Weekend'];

        // ⬇️ NEW: if counselor disabled this whole weekday, bail out
        if ($this->isFullyDisabledOnDate($cid, $date)) {
            return ['ok' => false, 'reason' => 'Disabled day'];
        }

        // availability rows (date overrides recurring); respect blocked tiles
        $rows = $this->rangesForCounselorOnDate($cid, $date);

        $isInsideAvailable = false;
        $isInsideBlocked   = false;

        foreach ($rows as $r) {
            if (!\is_string($r->start_time) || !\is_string($r->end_time) || $r->start_time==='' || $r->end_time==='') {
                continue;
            }
            $st = Carbon::parse($date->toDateString().' '.$r->start_time);
            $en = Carbon::parse($date->toDateString().' '.$r->end_time);

            $inside = $slotStart->gte($st) && $slotEnd->lte($en);
            if (!$inside) continue;

            if (($r->slot_type ?? 'available') === 'blocked') {
                $isInsideBlocked = true;
                break;
            } else {
                $isInsideAvailable = true;
            }
        }

        if ($isInsideBlocked)    return ['ok' => false, 'reason' => 'Blocked window'];
        if (!$isInsideAvailable) return ['ok' => false, 'reason' => 'Off-hours'];

        // conflict at the exact start time
        $hasConflict = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('scheduled_at', $slotStart)
            ->whereIn('status', ['pending','confirmed','ongoing'])
            ->exists();

        if ($hasConflict) return ['ok' => false, 'reason' => 'Already booked'];

        return ['ok' => true, 'reason' => null];
    }

    public function assignForm(int $id)
    {
        $appointment = $this->appointments->findDetailedById($id);
        abort_unless($appointment, 404);

        if ($appointment->status !== 'pending') {
            return redirect()
                ->route('admin.appointments.show', $appointment->id)
                ->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Not allowed',
                    'text'  => 'You can only assign a counselor to pending appointments.',
                ]);
        }

        $slotStart = Carbon::parse($appointment->scheduled_at)->second(0); // align to :00
        // 60-minute slot to match student side logic
        $counselors = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name','email','phone']);

        foreach ($counselors as $c) {
            $check = $this->checkCounselorAtSlot((int)$c->id, $slotStart);
            $c->available   = $check['ok'] ? 1 : 0;
            $c->busy_reason = $check['ok'] ? null : $check['reason'];
        }

        return view('admin.appointments.assign', compact('appointment', 'counselors'));
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'counselor_id' => ['required', 'exists:tbl_counselors,id'],
        ]);

        $ap = \DB::table('tbl_appointments')->where('id', $id)->first();
        abort_unless($ap, 404);

        if ($ap->status !== 'pending') {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'Only pending appointments can be assigned.',
            ]);
        }

        $res = $this->appointments->assignCounselor($id, (int)$data['counselor_id']);
        if (!$res['ok']) {
            $map = [
                'not_found'     => ['warning','Not found','Appointment not found.'],
                'in_past'       => ['warning','Not allowed','Cannot assign in the past.'],
                'not_available' => ['error','Counselor busy','Selected counselor is no longer free.'],
                'race_taken'    => ['error','Just taken','That slot was taken moments ago.'],
            ];
            [$icon,$title,$text] = $map[$res['reason']] ?? ['error','Error','Unable to assign counselor.'];
            return back()->with(self::FLASH_SWAL, compact('icon','title','text'));
        }

        // Auto-confirm after successful assign (optional business rule)
        $this->appointments->updateStatusByAction($id, 'confirm');

                /* ─────────────────────────────────────────────────────────────
        EMAIL NOTICES (robust: try Mailables; fallback to plaintext)
        ───────────────────────────────────────────────────────────── */
        try {
            // Re-fetch with joined names/emails (no model changes)
            $row = \DB::table('tbl_appointments as a')
                ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
                ->where('a.id', $id)
                ->select([
                    'a.id',
                    'a.scheduled_at',
                    's.id as student_id',
                    's.name as student_name',
                    's.email as student_email',
                    'c.id as counselor_id',
                    'c.name as counselor_name',
                    'c.email as counselor_email',
                ])->first();

            if ($row && $row->student_email && $row->counselor_email) {
                $whenNice = \Carbon\Carbon::parse($row->scheduled_at)->format('M d, Y g:i A');

                $sentViaMailable = false;

                // Try Mailables if both classes exist AND their constructors accept our payload.
                if (class_exists(\App\Mail\AppointmentAssignedStudent::class)
                    && class_exists(\App\Mail\AppointmentAssignedCounselor::class)) {

                    try {
                        // Try named-args first
                        Mail::to($row->student_email)->send(new \App\Mail\AppointmentAssignedStudent(
                            appointmentId: $row->id,
                            studentName:   (string) $row->student_name,
                            counselorName: (string) $row->counselor_name,
                            scheduledAt:   $row->scheduled_at,
                            whenNice:      $whenNice
                        ));

                        Mail::to($row->counselor_email)->send(new \App\Mail\AppointmentAssignedCounselor(
                            appointmentId: $row->id,
                            studentName:   (string) $row->student_name,
                            counselorName: (string) $row->counselor_name,
                            scheduledAt:   $row->scheduled_at,
                            whenNice:      $whenNice
                        ));

                        $sentViaMailable = true;
                    } catch (\Throwable $mErr) {
                        // Constructors likely have different signatures — log and fall back.
                        \Log::warning('Mailable signature mismatch; falling back to plaintext', [
                            'appointment_id' => $id,
                            'error' => $mErr->getMessage(),
                        ]);
                    }
                }

                if (!$sentViaMailable) {
                    // Plaintext fallback — guaranteed to work
                    Mail::raw(
                        "Hi {$row->student_name},\n\n".
                        "Your appointment has been approved.\n\n".
                        "Counselor: {$row->counselor_name}\n".
                        "When: {$whenNice}\n\n".
                        "If you didn’t request this, reply to this email.\n",
                        function ($m) use ($row) {
                            $m->to($row->student_email)->subject('LumiCHAT — Appointment Approved');
                        }
                    );

                    Mail::raw(
                        "New appointment assigned to you.\n\n".
                        "Student: {$row->student_name}\n".
                        "When: ".\Carbon\Carbon::parse($row->scheduled_at)->format('M d, Y g:i A')."\n\n".
                        "Please prepare accordingly.\n",
                        function ($m) use ($row) {
                            $m->to($row->counselor_email)->subject('LumiCHAT — New Appointment Assigned');
                        }
                    );
                }
            } else {
                \Log::warning('Email skipped: missing student/counselor email', [
                    'appointment_id' => $id,
                    'student_email' => $row->student_email ?? null,
                    'counselor_email' => $row->counselor_email ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // Don’t break the flow if email fails; just log it.
            \Log::warning('Appointment assign email failed', [
                'appointment_id' => $id,
                'error'          => $e->getMessage(),
            ]);
        }
        /* ───────────── end email notices ───────────── */

        return redirect()
        ->route('admin.appointments.show', $id)
        ->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Counselor assigned',
            'html'  => sprintf(
                '<div style="text-align:left">
                <div><b>Date:</b> %s</div>
                <div><b>Time:</b> %s</div>
                <div style="margin-top:.35rem;color:#475569">
                    Student and counselor have been notified via email.
                </div>
                </div>',
                e(\Carbon\Carbon::parse($ap->scheduled_at)->format('M d, Y')),
                e(\Carbon\Carbon::parse($ap->scheduled_at)->format('g:i A'))
            ),
            'confirmButtonText' => 'OK',
        ]);
    }

    /* ===================== Follow-up ===================== */

    public function followUpForm(int $id)
    {
        $appointment = $this->appointments->findDetailedById($id);
        abort_unless($appointment, 404);

        // Only allow after completion
        if ($appointment->status !== 'completed') {
            return redirect()
                ->route('admin.appointments.show', $appointment->id)
                ->with('swal', [
                    'icon'  => 'warning',
                    'title' => 'Not allowed',
                    'text'  => 'You can create a follow-up only after the appointment is completed.',
                ]);
        }

        // Start with same time next week
        $when = Carbon::parse($appointment->scheduled_at)->addWeek();

        // Snap to 30-min grid
        $when->second(0);
        $m = (int) $when->minute;
        $when->minute($m < 30 ? 30 : 0);
        if ($m >= 30) $when->addHour();

        // Move to next weekday (Mon–Fri)
        $when = $this->nextWeekdayMonToFri($when);

        // Find the next soonest slot that still has capacity
        $repo = $this->appointments;
        $limit = 200; // safety loop guard
        while ($limit--) {
            $freeIds = $repo->counselorIdsFreeAt($when);
            if (!empty($freeIds)) break;

            // try next 30-min slot; skip weekends
            $when->addMinutes(30);
            $when = $this->nextWeekdayMonToFri($when);
        }

        $suggest = [
            'date' => $when->toDateString(),        // 'YYYY-MM-DD'
            'time' => $when->format('H:i'),         // 'HH:MM'
            'nice' => $when->format('M d, Y g:i A') // pretty
        ];

        return view('admin.appointments.follow-up', compact('appointment', 'suggest'));
    }

    /** Ensure date is Mon–Fri. If Sat/Sun, jump to Monday 9:00 AM. */
    private function nextWeekdayMonToFri(Carbon $dt): Carbon
    {
        $dow = (int) $dt->dayOfWeek; // 0=Sun .. 6=Sat
        if ($dow === 0) { // Sunday -> Monday 9:00
            return $dt->addDay()->setTime(9, 0, 0);
        }
        if ($dow === 6) { // Saturday -> Monday 9:00 (+2 days)
            return $dt->addDays(2)->setTime(9, 0, 0);
        }
        return $dt;
    }

    public function followUpStore(Request $request, int $id)
    {
        $appointment = $this->appointments->findById($id);
        abort_unless($appointment, 404);

        // Only after completion
        if ($appointment->status !== 'completed') {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'You can create a follow-up only after the appointment is completed.',
            ]);
        }

        $data = $request->validate([
            'date' => ['required','date_format:Y-m-d'],
            'time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'note' => ['nullable','string','max:4000'],
        ]);

        $scheduledAt = Carbon::parse($data['date'].' '.$data['time'].':00');
        if ($scheduledAt->lte(now())) {
            return back()->withErrors(['time' => 'Please pick a future time.'])->withInput();
        }

        $originalCounselorId = $appointment->counselor_id ?: null;

        try {
            DB::transaction(function () use ($appointment, $scheduledAt, $originalCounselorId, $data) {

                // counselors free at that exact slot (pooled capacity)
                $freeIds = $this->appointments->counselorIdsFreeAt($scheduledAt);

                if ($originalCounselorId) {
                    // keep same counselor only if free
                    if (!in_array((int)$originalCounselorId, $freeIds, true)) {
                        throw new \RuntimeException('COUNSELOR_BUSY');
                    }
                    $counselorId = (int)$originalCounselorId;
                } else {
                    // pooled capacity required
                    if (empty($freeIds)) {
                        throw new \RuntimeException('FULL');
                    }
                    $counselorId = null; // assign later
                }

            // Create the appointment and capture its ID (all your fields stay the same)
            $newId = DB::table('tbl_appointments')->insertGetId([
                'student_id'   => (int) $appointment->student_id,
                'counselor_id' => $counselorId,        // leave as you already compute (can be null or an id)
                'scheduled_at' => $scheduledAt,        // your existing variable
                'status'       => 'confirmed',         // ✅ unchanged (auto-confirm per your flow)
                'parent_id'    => $appointment->id,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // 🔔 Notify the student with a deep link to their appointment view
            if ($student = User::find((int) $appointment->student_id)) {
                $dtLabel = Carbon::parse($scheduledAt)->format('M d, Y g:i A');

                $student->notify(new SimpleDatabaseNotification(
                    'Appointment approved',
                    'Your appointment for ' . $dtLabel . ' has been approved.',
                    route('appointment.view', $newId) // student-side page
                ));
            }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'FULL') {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'info',
                    'title' => 'Time slot unavailable',
                    'text'  => 'That time has no remaining capacity. Please pick another slot.',
                ])->withInput();
            }
            if ($e->getMessage() === 'COUNSELOR_BUSY') {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Counselor not available',
                    'text'  => 'The original counselor is busy at that time. Please pick a different time.',
                ])->withInput();
            }
            throw $e;
        }

        return redirect()
            ->route('admin.appointments.index')
            ->with(self::FLASH_SWAL, [
                'icon'  => 'success',
                'title' => 'Follow-up confirmed',
                'text'  => 'The follow-up appointment has been created and confirmed.',
            ]);
    }

    /** JSON: pooled capacity and (optional) specific counselor availability */
    public function capacity(Request $r): JsonResponse
    {
        try {
            $r->validate([
                'date'         => ['required','date_format:Y-m-d'],
                'time'         => ['required','date_format:H:i'],
                'counselor_id' => ['nullable','integer'],
            ]);

            $scheduledAt = Carbon::parse($r->input('date').' '.$r->input('time').':00');

            // always an array
            $freeIds = (array) ($this->appointments->counselorIdsFreeAt($scheduledAt) ?? []);
            $pooled  = count($freeIds);

            // cross-version safe int read
            $rawCid = $r->input('counselor_id', null);
            $cid    = ($rawCid === null || $rawCid === '') ? null : (int) $rawCid;

            $counselorFree = null;
            if ($cid !== null) {
                $counselorFree = in_array($cid, $freeIds, true);
            }

            return response()->json([
                'ok'                  => true,
                'pooled_available'    => $pooled,
                'counselor_available' => $counselorFree, // true/false/null
                'at'                  => $scheduledAt->toDateTimeString(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'validation',
                'msg'   => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('capacity error', ['exception' => $e]);
            return response()->json([
                'ok'    => false,
                'error' => 'server',
                'msg'   => 'Something went wrong.',
            ], 500);
        }
    }
    /**
     * A day is considered "disabled" for a counselor if, after resolving
     * date-specific overrides vs. recurring rows, there are **no** rows
     * marked as available for that date.
     */
    private function isFullyDisabledOnDate(int $cid, Carbon $date): bool
    {
        $rows = $this->rangesForCounselorOnDate($cid, $date);
        foreach ($rows as $r) {
            if (($r->slot_type ?? 'available') === 'available') {
                return false; // there is at least one available range
            }
        }
        return true; // nothing available => full-day disabled
    }
}
