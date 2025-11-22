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
use App\Support\Notify;
class AppointmentController extends Controller
{
    // ==== Flash keys ====
    private const FLASH_SWAL = 'swal';

    // ==== Filters ====
    private const STATUS_ALL = 'all';
    private const PERIOD_ALL = 'all';
    private const STATUSES   = ['pending','confirmed','canceled','completed','no_show','reassigned'];
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

    $now   = now();
    $table = (new \App\Models\Appointment)->getTable(); // normally "tbl_appointments"

    // 🚫 NO alias here, just the normal table
    $query = \App\Models\Appointment::query()
        ->with(['student','counselor'])
        ->select($table.'.*')
        ->addSelect([
            'cr_status' => \DB::table('counselor_change_requests')
                ->select('status')
                ->whereColumn('appointment_id', $table.'.id')
                ->orderByDesc('id')
                ->limit(1),
            'cr_created_at' => \DB::table('counselor_change_requests')
                ->select('created_at')
                ->whereColumn('appointment_id', $table.'.id')
                ->orderByDesc('id')
                ->limit(1),
        ]);

    // ---- status filter ----
    if ($status !== self::STATUS_ALL && $status !== 'reassigned') {
        // normal statuses: pending / confirmed / completed / canceled / no_show
        $query->where($table.'.status', $status);
    }

    // special: "Re-assigned only" → appointments that have history rows
    if ($status === 'reassigned' && \Schema::hasTable('tbl_appointment_counselor_history')) {
        $query->whereExists(function ($sub) use ($table) {
            $sub->from('tbl_appointment_counselor_history as h')
                ->whereColumn('h.appointment_id', $table.'.id')
                ->where('h.status', 'reassigned');
        });
    }

    // ---- period filter ----
    switch ($period) {
        case 'today':
            $query->whereDate($table.'.scheduled_at', $now->toDateString());
            break;

        case 'upcoming':
            $query->where($table.'.scheduled_at', '>=', $now);
            break;

        case 'this_week':
            $query->whereBetween($table.'.scheduled_at', [
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ]);
            break;

        case 'this_month':
            $query->whereBetween($table.'.scheduled_at', [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ]);
            break;

        case 'past':
            $query->where($table.'.scheduled_at', '<', $now);
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
    $query->orderByRaw("CASE WHEN {$table}.status = 'completed' THEN 1 ELSE 0 END ASC");

    if ($period === 'past') {
        $query->orderBy($table.'.scheduled_at', 'desc');
    } elseif (\in_array($period, ['today','upcoming','this_week','this_month'], true)) {
        $query->orderBy($table.'.scheduled_at', 'asc');
    } else {
        $query
            ->orderByRaw("CASE WHEN {$table}.scheduled_at >= ? THEN 0 ELSE 1 END", [$now])
            ->orderByRaw("CASE WHEN {$table}.scheduled_at >= ? THEN {$table}.scheduled_at END ASC",  [$now])
            ->orderByRaw("CASE WHEN {$table}.scheduled_at <  ? THEN {$table}.scheduled_at END DESC", [$now])
            ->orderByRaw("CASE WHEN {$table}.status = 'completed' THEN {$table}.scheduled_at END DESC");
    }

    // paginate + keep filters
    $appointments = $query->paginate(10)->withQueryString();

    // 🔎 counselor re-assignment history for all listed appointments
    $historyByAppt = [];
    if ($appointments->count() > 0 && \Schema::hasTable('tbl_appointment_counselor_history')) {
        $ids = $appointments->pluck('id')->all();

        $rowsHist = \DB::table('tbl_appointment_counselor_history as h')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'h.counselor_id')
            ->whereIn('h.appointment_id', $ids)
            ->orderBy('h.changed_at')
            ->get([
                'h.appointment_id',
                'h.counselor_id',
                'h.status',
                'h.changed_at',
                'c.name as counselor_name',
            ]);

        foreach ($rowsHist as $h) {
            $historyByAppt[$h->appointment_id][] = $h;
        }
    }

    return view('admin.appointments.index', compact(
        'appointments',
        'status',
        'period',
        'q',
        'historyByAppt'
    ));
}


    public function show(int $id): \Illuminate\View\View
    {
        // same finder mo
        $appointment = $this->appointments->findDetailedById($id);
        abort_unless($appointment, 404);

        // --- counselor history (iniwan ko as is) ---
        $history = [];
        if (\Schema::hasTable('tbl_appointment_counselor_history')) {
            $history = \DB::table('tbl_appointment_counselor_history as h')
                ->leftJoin('tbl_counselors as c', 'c.id', '=', 'h.counselor_id')
                ->where('h.appointment_id', $id)
                ->orderBy('h.changed_at')
                ->get([
                    'h.appointment_id',
                    'h.counselor_id',
                    'h.status',
                    'h.changed_at',
                    'c.name as counselor_name',
                ]);
        }

        // --- LATEST change request + preferred counselor name ---
        $changeReq = \DB::table('counselor_change_requests as cr')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'cr.preference_counselor_id')
            ->where('cr.appointment_id', $id)
            ->orderByDesc('cr.id')
            ->select(
                'cr.*',
                'c.name as preferred_counselor_name'
            )
            ->first();

        $preferredCounselorName = $changeReq?->preferred_counselor_name;

        return view('admin.appointments.show', [
            'appointment'           => $appointment,
            'history'               => $history,
            'changeReq'             => $changeReq,            // ✅ ito na ang gagamitin ng Blade
            'preferredCounselorName'=> $preferredCounselorName,
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

            // Use repo for confirm/done (keeps your business rules)
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
            // No-show handling with grace period
            elseif ($action === 'no_show') {
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

                $start     = Carbon::parse($row->scheduled_at)->second(0);
                $endOfSlot = $start->copy()->addMinutes(60); // slot length = 60m
                $graceOver = $endOfSlot->copy()->addMinutes(self::NO_SHOW_GRACE_MINUTES);

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

            // Post-update effects
            $appt = DB::table('tbl_appointments')
                ->select('id','student_id','status')
                ->where('id', $id)
                ->first();

            if ($appt) {
                // Safe deep link (only if the route exists)
                $viewUrl = \Illuminate\Support\Facades\Route::has('appointment.view')
                    ? route('appointment.view', (int)$appt->id)
                    : null;

                // Per-status student notifications (in-app)
                switch ($appt->status) {
                    case 'confirmed':
                        Notify::student((int)$appt->student_id, 'Appointment confirmed', 'Your appointment has been confirmed.', $viewUrl);
                        break;
                    case 'completed':
                        Notify::student((int)$appt->student_id, 'Appointment completed', 'Your appointment has been marked as completed.', $viewUrl);
                        break;
                    case 'no_show':
                        Notify::student((int)$appt->student_id, 'Marked as no-show', 'The appointment was marked as no-show.', $viewUrl);
                        break;
                    case 'canceled':
                        Notify::student((int)$appt->student_id, 'Appointment canceled', 'The appointment was canceled.', $viewUrl);
                        break;
                }

                // Clear HIGH risk on completion (preserves your existing behavior)
                if ($appt->status === 'completed') {
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

        if ($status !== 'all') {
            if ($status === 'reassigned') {
                $query->whereIn('a.id', function ($sub) {
                    $sub->from('tbl_appointment_counselor_history as h')
                        ->select('h.appointment_id')
                        ->distinct();
                });
            } else {
                $query->where('a.status', $status);
            }
        }

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

        if (!empty($appointment->counselor_id)) {
            return redirect()
                ->route('admin.appointments.show', $appointment->id)
                ->with(self::FLASH_SWAL, [
                    'icon'=>'info','title'=>'Counselor already assigned',
                    'text'=>'This appointment already has a counselor. Use the change-request flow to reassign.',
                ]);
        }
        if (!in_array($appointment->status, ['pending','confirmed'], true)) {
            return redirect()->route('admin.appointments.show', $appointment->id)
                ->with(self::FLASH_SWAL, ['icon'=>'warning','title'=>'Not allowed','text'=>'Only pending/confirmed appointments can be assigned.']);
        }
        if (\Carbon\Carbon::parse($appointment->scheduled_at)->lte(now())) {
            return redirect()->route('admin.appointments.show', $appointment->id)
                ->with(self::FLASH_SWAL, ['icon'=>'warning','title'=>'Not allowed','text'=>'Cannot assign a counselor to a past time.']);
        }

        // 🔎 latest approved CR (if any) to know who must be blocked
        $cr = \DB::table('counselor_change_requests')
            ->where('appointment_id', $appointment->id)
            ->orderByDesc('id')
            ->first();

        $blockedCounselorId = null;

        if ($cr && in_array($cr->status, ['approved','requested'], true)) {
            // be generous in what we accept (depende sa actual column name mo)
            $rawPrev =
                $cr->previous_counselor_id
                ?? $cr->prev_counselor_id   // just in case ganito pangalan
                ?? $cr->previous_counselor  // or this
                ?? null;

            if ($rawPrev !== null && $rawPrev !== '') {
                $blockedCounselorId = (int) $rawPrev;
            }
        }

        $slotStart = \Carbon\Carbon::parse($appointment->scheduled_at)->second(0);
        $counselors = \DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name','email','phone']);

        foreach ($counselors as $c) {
            $c->available   = $this->appointments->counselorIsFreeAt((int)$c->id, $slotStart) ? 1 : 0;
            $c->busy_reason = $c->available ? null : 'Not available';

            if ($blockedCounselorId && (int)$c->id === (int)$blockedCounselorId) {
                $c->available      = 0;
                $c->busy_reason    = 'Last assigned counselor';
                $c->__blocked_same = true;
            }
        }

        return view('admin.appointments.assign', compact('appointment', 'counselors', 'blockedCounselorId'));
    }

        public function assign(Request $request, int $id): RedirectResponse
        {
            $data = $request->validate([
                'counselor_id' => ['required','exists:tbl_counselors,id'],
            ]);
            $cid = (int) $data['counselor_id'];

            return \DB::transaction(function () use ($id, $cid) {
                $ap = \DB::table('tbl_appointments')->where('id', $id)->first();
                abort_unless($ap, 404);

                if (!empty($ap->counselor_id)) {
                    return back()->with(self::FLASH_SWAL, [
                        'icon'=>'info','title'=>'Already assigned',
                        'text'=>'This appointment already has a counselor. Use the change-request flow to reassign.',
                    ]);
                }
                if (!in_array($ap->status, ['pending','confirmed'], true)) {
                    return back()->with(self::FLASH_SWAL, ['icon'=>'warning','title'=>'Not allowed','text'=>'Only pending/confirmed appointments can be assigned.']);
                }
                if (\Carbon\Carbon::parse($ap->scheduled_at)->lte(now())) {
                    return back()->with(self::FLASH_SWAL, ['icon'=>'warning','title'=>'Not allowed','text'=>'Cannot assign a counselor to a past time.']);
                }

                // 🚫 server guard: if there is an approved CR, do not allow the same previous counselor
                $cr = \DB::table('counselor_change_requests')
                    ->where('appointment_id', $id)
                    ->orderByDesc('id')
                    ->first();

                if ($cr && $cr->status === 'approved' && !empty($cr->previous_counselor_id)) {
                    if ((int)$cr->previous_counselor_id === $cid) {
                        return back()->with(self::FLASH_SWAL, [
                            'icon'=>'warning',
                            'title'=>'Not allowed',
                            'text'=>'You can’t reassign the same counselor for an approved change request.',
                        ])->withInput();
                    }
                }

                // repo: lock + CAS + availability
                $res = $this->appointments->assignCounselor($id, $cid);
                if (!$res['ok']) {
                    $map = [
                        'not_found'        => ['warning','Not found','Appointment not found.'],
                        'invalid_status'   => ['warning','Not allowed','This appointment cannot be assigned.'],
                        'already_assigned' => ['info','Already assigned','A counselor is already set.'],
                        'in_past'          => ['warning','Not allowed','Cannot assign in the past.'],
                        'not_available'    => ['error','Counselor busy','Selected counselor is no longer free.'],
                        'race_taken'       => ['error','Just taken','That slot was taken moments ago.'],
                        'same_as_previous' => ['warning','Not allowed','You can’t assign the same counselor requested to be changed.'],
                    ];
                    [$icon,$title,$text] = $map[$res['reason']] ?? ['error','Error','Unable to assign counselor.'];
                    return back()->with(self::FLASH_SWAL, compact('icon','title','text'));
                }

                // Auto-confirm once (your existing rule)
                $this->appointments->updateStatusByAction($id, 'confirm');

                // ——— notifications (student + counselor) ———
                try {
                    $row = \DB::table('tbl_appointments as a')
                        ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                        ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
                        ->where('a.id', $id)
                        ->select([
                            'a.id','a.scheduled_at',
                            's.id as student_id','s.name as student_name','s.email as student_email',
                            'c.id as counselor_id','c.name as counselor_name','c.email as counselor_email',
                        ])->first();

                    if ($row) {
                        $whenNice     = \Carbon\Carbon::parse($row->scheduled_at)->format('M d, Y g:i A');
                        $studentUrl   = route('appointment.view', $row->id); // keep your student route
                        $counselorUrl = \Illuminate\Support\Facades\Route::has('counselor.appointments.show')
                            ? route('counselor.appointments.show', $row->id)
                            : null;

                        // Student: Appointment confirmed
                        Notify::student(
                            (int) $row->student_id,
                            'Appointment confirmed',
                            'Your counseling appointment has been confirmed. Counselor: '.$row->counselor_name.' · '.$whenNice.'.',
                            $studentUrl
                        );

                        // Counselor: New appointment assigned
                        if ($row->counselor_id) {
                            Notify::counselor(
                                (int) $row->counselor_id,
                                'New appointment assigned',
                                'Student: '.$row->student_name.' · '.$whenNice.'.',
                                $counselorUrl
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Admin assign notify failed', ['id'=>$id,'e'=>$e->getMessage()]);
                }

                // Success swal
                $whenDate = \Carbon\Carbon::parse($ap->scheduled_at)->format('M d, Y');
                $whenTime = \Carbon\Carbon::parse($ap->scheduled_at)->format('g:i A');

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
                                Student and counselor have been notified.
                            </div>
                            </div>',
                            e($whenDate), e($whenTime)
                        ),
                        'confirmButtonText' => 'OK',
                    ]);
            });
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

/**
 * Notify the student that a counselor has been assigned (or changed).
 */
    private function notifyStudentCounselorAssigned(int $appointmentId): void
    {
        $ap = DB::table('tbl_appointments')->where('id', $appointmentId)->first();
        if (!$ap) return;

        $counselor = $ap->counselor_id
            ? DB::table('tbl_counselors')->where('id', $ap->counselor_id)->first()
            : null;

        $slot = $ap->scheduled_at ? Carbon::parse($ap->scheduled_at) : null;

        $title = $counselor ? 'Counselor assigned' : 'Counselor changed';
        $body  = trim(
            ($counselor ? ('You have been assigned to ' . $counselor->name) : 'A counselor was assigned')
            . ($slot ? (' on ' . $slot->format('M d, Y g:i A') . '.') : '.')
        );

        Notify::student((int)$ap->student_id, $title, $body);
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

    // capture created appt details for post-commit notifications
    $created = (object)['id' => null, 'counselor_id' => null, 'scheduled_at' => null];

    try {
        DB::transaction(function () use ($appointment, $scheduledAt, $originalCounselorId, $data, &$created) {

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
                $counselorId = null; // assign later (pooled)
            }

            // Create the appointment and capture its ID
            $newId = DB::table('tbl_appointments')->insertGetId([
                'student_id'   => (int) $appointment->student_id,
                'counselor_id' => $counselorId,        // can be null or an id
                'scheduled_at' => $scheduledAt,
                'status'       => 'confirmed',         // auto-confirm per your flow
                'parent_id'    => $appointment->id,
                'note'         => $data['note'] ?? null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // expose for post-commit notifications
            $created->id           = $newId;
            $created->counselor_id = $counselorId;
            $created->scheduled_at = $scheduledAt;
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

    // 🔔 Post-commit notifications (soft-fail)
    try {
        $dtLabel = Carbon::parse($created->scheduled_at)->format('M d, Y g:i A');

        // Student — Follow-up scheduled
        \App\Support\Notify::student(
            (int) $appointment->student_id,
            'Follow-up scheduled',
            'Your follow-up appointment has been scheduled for '.$dtLabel.'.',
            route('appointment.view', (int) $created->id)
        );

        // Counselor — New follow-up assigned (only if counselor kept/assigned)
        if (!empty($created->counselor_id) && \Illuminate\Support\Facades\Route::has('counselor.appointments.show')) {
            $studentName = \DB::table('tbl_users')->where('id', $appointment->student_id)->value('name') ?? ('#'.$appointment->student_id);

            \App\Support\Notify::counselor(
                (int) $created->counselor_id,
                'New follow-up assigned',
                'Student: '.$studentName.' · '.$dtLabel.'.',
                route('counselor.appointments.show', (int) $created->id)
            );
        }
    } catch (\Throwable $e) {
        \Log::warning('followUpStore notify failed', ['id' => $created->id, 'e' => $e->getMessage()]);
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

   public function handleChangeRequest(Request $r, int $id, string $action): \Illuminate\Http\RedirectResponse
    {
        try {
            // 1) Only APPROVE is allowed now
            if ($action !== 'approve') {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Invalid action',
                    'text'  => 'Only approval of counselor change requests is allowed.',
                ]);
            }

            // 2) Load appointment
            $ap = \DB::table('tbl_appointments')->where('id', $id)->first();
            if (!$ap) {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Not found',
                    'text'  => 'Appointment not found.',
                ]);
            }

            // Only pending/confirmed can be processed
            if (!in_array($ap->status, ['pending','confirmed'], true)) {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'warning',
                    'title' => 'Not allowed',
                    'text'  => 'You can process change requests only for pending/confirmed appointments.',
                ]);
            }

            // 3) Most recent counselor-change request
            $cr = \DB::table('counselor_change_requests')
                ->where('appointment_id', $id)
                ->orderByDesc('id')
                ->first();

            if (!$cr || $cr->status !== 'requested') {
                return back()->with(self::FLASH_SWAL, [
                    'icon'  => 'info',
                    'title' => 'No pending request',
                    'text'  => 'Nothing to process.',
                ]);
            }

            // Student’s preferred counselor (if any)
            $preferredId = null;
            if (!empty($cr->preference_counselor_id)) {
                $preferredId = (int) $cr->preference_counselor_id;
            }

            // 4) First: mark request as approved, log previous counselor, clear current counselor
            DB::transaction(function () use ($id, $cr) {
                // lock appointment so we can capture the last counselor before clearing
                $apLocked = \DB::table('tbl_appointments')
                    ->lockForUpdate()
                    ->where('id', $id)
                    ->first();

                $prevId = $cr->previous_counselor_id ?? ($apLocked?->counselor_id ?? null);

                $updateData = [
                    'status'              => 'approved',
                    'updated_at'          => now(),
                    'handled_by_admin_id' => auth()->id(),
                    'handled_at'          => now(),
                ];

                if (\Schema::hasColumn('counselor_change_requests', 'previous_counselor_id')) {
                    $updateData['previous_counselor_id'] = $prevId;
                }

                \DB::table('counselor_change_requests')
                    ->where('id', $cr->id)
                    ->update($updateData);

                // history row for the past counselor
                if ($prevId && \Schema::hasTable('tbl_appointment_counselor_history')) {
                    \DB::table('tbl_appointment_counselor_history')->insert([
                        'appointment_id'       => $apLocked->id,
                        'counselor_id'         => (int) $prevId,
                        'status'               => 'reassigned',
                        'changed_at'           => now(),
                        'changed_by_admin_id'  => auth()->id(),
                        'created_at'           => now(),
                        'updated_at'           => now(),
                    ]);
                }

                // clear counselor and move back to pending
                \DB::table('tbl_appointments')
                    ->where('id', $id)
                    ->update([
                        'counselor_id' => null,
                        'status'       => 'pending',
                        'updated_at'   => now(),
                    ]);
            });

            // 5) If there is a preferred counselor → try AUTO ASSIGN
            if ($preferredId) {
                $res = $this->appointments->assignCounselor($id, $preferredId);

                if ($res['ok'] ?? false) {
                    // auto-confirm (same rule as manual assign)
                    $this->appointments->updateStatusByAction($id, 'confirm');

                    // pull row for notifications
                    try {
                        $row = \DB::table('tbl_appointments as a')
                            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
                            ->where('a.id', $id)
                            ->select([
                                'a.id','a.scheduled_at',
                                's.id as student_id','s.name as student_name','s.email as student_email',
                                'c.id as counselor_id','c.name as counselor_name','c.email as counselor_email',
                            ])->first();

                        if ($row) {
                            $whenNice     = \Carbon\Carbon::parse($row->scheduled_at)->format('M d, Y g:i A');
                            $studentUrl   = route('appointment.view', $row->id);
                            $counselorUrl = \Illuminate\Support\Facades\Route::has('counselor.appointments.show')
                                ? route('counselor.appointments.show', $row->id)
                                : null;

                            // student notification
                            \App\Support\Notify::student(
                                (int) $row->student_id,
                                'Reassignment approved',
                                'Your counselor change request was approved. You have been reassigned to '.$row->counselor_name.' on '.$whenNice.'.',
                                $studentUrl
                            );

                            // counselor notification
                            if ($row->counselor_id && method_exists(\App\Support\Notify::class, 'counselor')) {
                                \App\Support\Notify::counselor(
                                    (int) $row->counselor_id,
                                    'New reassigned appointment',
                                    'Student: '.$row->student_name.' · '.$whenNice.'.',
                                    $counselorUrl
                                );
                            }
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Notify failed on auto-assign change-request', [
                            'id'  => $id,
                            'e'   => $e->getMessage(),
                        ]);
                    }

                    // ✅ SUCCESS – auto-assigned to preferred counselor
                    return redirect()
                        ->route('admin.appointments.show', $id)
                        ->with(self::FLASH_SWAL, [
                            'icon'  => 'success',
                            'title' => 'Reassignment completed',
                            'text'  => 'Request approved and the student has been moved to their preferred counselor.',
                        ]);
                }

                // ❌ Auto-assign failed → fall back to manual Assign screen
                return redirect()
                    ->route('admin.appointments.assign.form', $id)
                    ->with(self::FLASH_SWAL, [
                        'icon'  => 'warning',
                        'title' => 'Preferred counselor unavailable',
                        'text'  => 'The preferred counselor is not available for this slot. Please select another counselor.',
                    ]);
            }

            // 6) No preferred counselor → old flow: approved + admin must assign
            if (class_exists(\App\Support\Notify::class)) {
                \App\Support\Notify::student(
                    (int) $ap->student_id,
                    'Reassignment approved',
                    'Your counselor change request was approved. We will assign a new counselor shortly.',
                    route('appointment.view', $id)
                );
            }

            return redirect()
                ->route('admin.appointments.assign.form', $id)
                ->with(self::FLASH_SWAL, [
                    'icon'  => 'success',
                    'title' => 'Approved',
                    'text'  => 'Request approved. Please assign a new counselor.',
                ]);

        } catch (\Throwable $e) {
            \Log::error('change_request.handle failed', [
                'id'     => $id,
                'action' => $action,
                'e'      => $e,
            ]);

            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'error',
                'title' => 'Server error',
                'text'  => 'Something went wrong while processing the request. Check logs.',
            ]);
        }
    }

}