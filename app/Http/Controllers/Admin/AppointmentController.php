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
use App\Mail\AppointmentAssignedCounselor;
use App\Mail\AppointmentAssignedStudent;    

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

    /* ===================== Email helpers (safe, no crashes) ===================== */

    /** Safe plaintext email sender (logs failures, never throws). */
    private function sendPlainEmail(?string $to, string $subject, string $body): void
    {
        if (!$to) return;
        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
            Log::info('Mail sent OK', ['to' => $to, 'subject' => $subject]);
        } catch (\Throwable $e) {
            Log::warning('Mail send failed', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
        }
    }

    /** M d, Y g:i A */
    private function niceWhen($dt): string
    {
        return Carbon::parse($dt)->format('M d, Y g:i A');
    }

    /** Joined row for emailing/notifying */
    private function joinedApptRow(int $appointmentId): ?object
    {
        return DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->where('a.id', $appointmentId)
            ->select([
                'a.id','a.scheduled_at','a.status',
                's.id as student_id','s.name as student_name','s.email as student_email',
                'c.id as counselor_id','c.name as counselor_name','c.email as counselor_email',
            ])->first();
    }

    /** List appointments with optional filters + search (counselor name). */
    public function index(Request $r): View
    {
        $status = \in_array($r->query('status'), self::STATUSES, true) ? $r->query('status') : self::STATUS_ALL;
        $period = \in_array($r->query('period'), self::PERIODS, true)   ? $r->query('period') : self::PERIOD_ALL;
        $q      = \trim((string) $r->query('q', ''));

        $now   = now();
        $table = (new \App\Models\Appointment)->getTable(); // normally "tbl_appointments"

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
            $query->where($table.'.status', $status);
        }

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
                // 'all'
                break;
        }

        // ---- search by student / counselor ----
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->whereHas('student', function ($s) use ($q) {
                    $s->where('name', 'like', "%{$q}%");
                })->orWhereHas('counselor', function ($c) use ($q) {
                    $c->where('name', 'like', "%{$q}%");
                });
            });
        }

        // ---- ordering ----
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

        // 🔹 latest updated_at for polling
        $lastUpdatedAt = \DB::table($table)->max('updated_at');

        return view('admin.appointments.index', [
            'appointments'   => $appointments,
            'status'         => $status,
            'period'         => $period,
            'q'              => $q,
            'historyByAppt'  => $historyByAppt,
            'lastUpdatedAt'  => $lastUpdatedAt,
        ]);
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
/** Update status via action ('confirm' | 'done' | 'no_show') with rule checks. */
public function updateStatus(Request $r, int $id): RedirectResponse
{
    $action = $r->input('action'); // 'confirm' | 'done' | 'no_show'

    $from = null;
    $to   = null;
    $row  = null; // minimal row for notifyOnStatusChange()

    // ---------- confirm / done via repository ----------
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

        // capture for centralized notifications
        $from = (string) ($res['from'] ?? '');
        $to   = (string) ($res['to']   ?? '');
        $row  = (object) ($res['row']  ?? null);
    }

    // ---------- no_show with grace period ----------
    elseif ($action === 'no_show') {
        $before = DB::table('tbl_appointments')->where('id', $id)->first();
        if (!$before) {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not found',
                'text'  => 'Appointment not found.',
            ]);
        }

        // Only pending/confirmed can become no_show
        if (!in_array($before->status, ['pending','confirmed'], true)) {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'Only pending/confirmed appointments can be marked as No-Show.',
            ]);
        }

        $start     = Carbon::parse($before->scheduled_at)->second(0);
        $endOfSlot = $start->copy()->addMinutes(60);
        $graceOver = $endOfSlot->copy()->addMinutes(self::NO_SHOW_GRACE_MINUTES);

        if ($graceOver->isFuture()) {
            return back()->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Too early',
                'text'  => 'You can mark No-Show only after the slot has passed the grace period.',
            ]);
        }

        DB::table('tbl_appointments')->where('id', $id)->update([
            'status'     => 'no_show',
            'updated_at' => now(),
        ]);

        // capture for centralized notifications
        $from = (string) $before->status;
        $to   = 'no_show';
        $row  = DB::table('tbl_appointments')->where('id', $id)->first(['id','student_id','counselor_id','scheduled_at','status']);
    }

    // ---------- invalid ----------
    else {
        return back()->with(self::FLASH_SWAL, [
            'icon'  => 'warning',
            'title' => 'Not allowed',
            'text'  => 'Invalid action.',
        ]);
    }

    // ---------- Centralized in-app notifications (student / counselor / admins) ----------
    try {
        if ($row && $from !== null && $to !== null) {
            $this->notifyOnStatusChange($row, $from, $to);
        }
    } catch (\Throwable $e) {
        \Log::notice('notifyOnStatusChange failed', ['id'=>$id, 'e'=>$e->getMessage()]);
    }

    // ---------- Emails (same content as before) ----------
    try {
        $j = $this->joinedApptRow($id);
        if ($j) {
            $whenNice = $this->niceWhen($j->scheduled_at ?? now());
            if ($to === 'confirmed') {
                // student
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Confirmed',
                    "Hi {$j->student_name},\n\nYour appointment has been confirmed.\nWhen: {$whenNice}\nCounselor: {$j->counselor_name}\n\nSee you!"
                );
                // counselor
                $this->sendPlainEmail(
                    $j->counselor_email ?? null,
                    'LumiCHAT — Appointment Confirmed',
                    "A confirmed appointment is on your schedule.\nStudent: {$j->student_name}\nWhen: {$whenNice}\n"
                );
            } elseif ($to === 'completed') {
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Completed',
                    "Hi {$j->student_name},\n\nYour counseling appointment on {$whenNice} has been marked as completed.\n"
                );
            } elseif ($to === 'no_show') {
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — No-Show Notice',
                    "Hi {$j->student_name},\n\nThe appointment scheduled on {$whenNice} was marked as no-show.\nIf this was a mistake, please contact us.\n"
                );
            } elseif ($to === 'canceled' || $to === 'cancelled') {
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Canceled',
                    "Hi {$j->student_name},\n\nYour appointment scheduled on {$whenNice} has been canceled.\n"
                );
            }
        }
    } catch (\Throwable $e) {
        \Log::warning('Status email failed', ['id' => $id, 'err' => $e->getMessage()]);
    }

    // ---------- Post-effects (risk clearing on completed) ----------
    if ($to === 'completed') {
        $appt = DB::table('tbl_appointments')->select('id','student_id')->where('id', $id)->first();
        if ($appt) {
            $updates = ['risk_level' => null, 'updated_at' => now()];
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
        ini_set('memory_limit', '512M'); // 🔹 Fix: Allow Dompdf to allocate enough memory to parse complex Tailwind grids
        
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
    ini_set('memory_limit', '512M'); // 🔹 Fix: Allow Dompdf to allocate enough memory

    $appointment = $this->appointments->findDetailedById($id);
    abort_unless($appointment, 404);

    // 🔹 Latest CASE NOTE for this appointment
    $caseNote = \DB::table('tbl_case_notes')
        ->where('appointment_id', $appointment->id)
        ->orderByDesc('id')
        ->first();

    $logoPath = public_path('images/chatbot.png');
    $logoData = null;
    if (is_file($logoPath)) {
        $mime = \Illuminate\Support\Str::endsWith(strtolower($logoPath), '.svg')
            ? 'image/svg+xml'
            : 'image/png';
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
        'isPhpEnabled'         => true,   // for page numbers script
    ]);

    $pdf->loadView('admin.appointments.pdf-show', [
        'appointment'  => $appointment,
        'caseNote'     => $caseNote,
        'generatedAt'  => now()->format('Y-m-d H:i'),
        'logoData'     => $logoData,
    ]);

    $filename = 'Appointment_' . $appointment->id . '.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename);
    }

    return $pdf->stream($filename);
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

 /** UPDATED: assignment with status update + notifications + emails */
public function assign(Request $request, int $id): RedirectResponse
{
    $data = $request->validate([
        'counselor_id' => ['required','exists:tbl_counselors,id'],
    ]);
    $cid = (int) $data['counselor_id'];

    return \DB::transaction(function () use ($id, $cid) {
        $ap = \DB::table('tbl_appointments')->where('id', $id)->lockForUpdate()->first();
        abort_unless($ap, 404);

        if (!empty($ap->counselor_id)) {
            return back()->with(self::FLASH_SWAL, [
                'icon'=>'info','title'=>'Already assigned',
                'text'=>'This appointment already has a counselor. Use the change-request flow to reassign.',
            ]);
        }
        if (!in_array($ap->status, ['pending','confirmed'], true)) {
            return back()->with(self::FLASH_SWAL, [
                'icon'=>'warning','title'=>'Not allowed',
                'text'=>'Only pending/confirmed appointments can be assigned.',
            ]);
        }
        if (\Carbon\Carbon::parse($ap->scheduled_at)->lte(now())) {
            return back()->with(self::FLASH_SWAL, [
                'icon'=>'warning','title'=>'Not allowed',
                'text'=>'Cannot assign a counselor to a past time.',
            ]);
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

        // Auto-confirm and notify via centralized helper
        $res2 = $this->appointments->updateStatusByAction($id, 'confirm');
        if ($res2['ok'] ?? false) {
            if (isset($res2['row'], $res2['from'], $res2['to'])) {
                $this->notifyOnStatusChange($res2['row'], (string)$res2['from'], (string)$res2['to']);
            }
        }

        // 🔔 Admin broadcast (deep link)
        try {
            $row = \DB::table('tbl_appointments as a')
                ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
                ->where('a.id', $id)
                ->select(['a.id','a.scheduled_at','s.name as student_name','c.name as counselor_name'])
                ->first();

            if ($row) {
                \App\Support\Notify::admins(
                    'Appointment assigned',
                    sprintf(
                        'Admin assigned %s to %s • %s (ID #%d).',
                        (string)($row->counselor_name ?? '—'),
                        (string)($row->student_name ?? '—'),
                        \Carbon\Carbon::parse($row->scheduled_at)->format('M d, Y g:i A'),
                        (int)$row->id
                    ),
                    route('admin.appointments.show', $id)
                );
            }
        } catch (\Throwable $e) {
            \Log::notice('Notify::admins skipped/failed (assign)', ['appt'=>$id,'e'=>$e->getMessage()]);
        }

        /* 🔹 EMAILS ON ASSIGN + AUTO-CONFIRM (plain text, using working helper) */
        try {
            $j = $this->joinedApptRow($id);
            if ($j) {
                $whenNice = $this->niceWhen($j->scheduled_at ?? now());

                // Student email
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Assigned & Confirmed',
                    "Hi {$j->student_name},\n\n"
                    ."Your counseling appointment has been assigned and confirmed.\n\n"
                    ."Counselor: {$j->counselor_name}\n"
                    ."When: {$whenNice}\n\n"
                    ."See you soon.\n"
                );

                // Counselor email
                $this->sendPlainEmail(
                    $j->counselor_email ?? null,
                    'LumiCHAT — New Appointment Assigned',
                    "Hi {$j->counselor_name},\n\n"
                    ."A counseling appointment has been assigned to you.\n\n"
                    ."Student: {$j->student_name}\n"
                    ."When: {$whenNice}\n\n"
                    ."Please check your LumiCHAT dashboard for full details.\n"
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('AppointmentAssigned mail failed', [
                'appointment_id' => $id,
                'error'          => $e->getMessage(),
            ]);
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
                        Student and counselor have been notified (email + in-app).
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
                'student_id'               => (int) $appointment->student_id,
                'counselor_id'             => $counselorId,     // can be null or an id
                'scheduled_at'             => $scheduledAt,
                'status'                   => 'pending',        // ← awaiting student confirmation
                'student_confirm_required' => 1,                // ← require student confirm
                'student_confirmed_at'     => null,
                'parent_id'                => $appointment->id,
                'note'                     => $data['note'] ?? null,
                'created_at'               => now(),
                'updated_at'               => now(),
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

        // Build signed confirm/decline links for the student (48h validity)
        $confirmUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'appointments.student.confirm',
            ['id' => (int) $created->id],
            now()->addDays(2)
        );
        $declineUrl = \Illuminate\Support\Facades\URL::signedRoute(
            'appointments.student.decline',
            ['id' => (int) $created->id],
            now()->addDays(2)
        );

        // Student — needs confirmation
        \App\Support\Notify::student(
            (int) $appointment->student_id,
            'Confirm your appointment',
            'We scheduled a counseling slot for '.$dtLabel.'. Please confirm or decline.',
            $confirmUrl // primary deep link
        );

        // Optional: email both links
        $student = DB::table('tbl_users')->where('id', $appointment->student_id)->first();
        if (!empty($student?->email)) {
            $body = "Hi {$student->name},\n\n"
                  . "A counseling appointment was scheduled for {$dtLabel}.\n"
                  . "Please confirm or decline:\n\n"
                  . "Confirm: {$confirmUrl}\n"
                  . "Decline: {$declineUrl}\n";
            $this->sendPlainEmail($student->email, 'LumiCHAT — Please Confirm Appointment', $body);
        }

        // Counselor — tentatively assigned (if kept/assigned)
        if (!empty($created->counselor_id) && \Illuminate\Support\Facades\Route::has('counselor.appointments.show')) {
            $studentName = $student?->name ?? ('#'.$appointment->student_id);
            \App\Support\Notify::counselor(
                (int) $created->counselor_id,
                'Tentative appointment (awaiting student)',
                'Student: '.$studentName.' · '.$dtLabel.'. Awaiting student confirmation.',
                route('counselor.appointments.show', (int) $created->id)
            );
        }

        // Admins — FYI
        if (\Illuminate\Support\Facades\Route::has('admin.appointments.show')) {
            \App\Support\Notify::admins(
                'Tentative appointment created',
                sprintf('Appointment #%d scheduled for %s — awaiting student confirmation.', (int) $created->id, $dtLabel),
                route('admin.appointments.show', (int) $created->id)
            );
        }
    } catch (\Throwable $e) {
        \Log::warning('followUpStore notify/email failed', ['id' => $created->id, 'e' => $e->getMessage()]);
    }

    return redirect()
        ->route('admin.appointments.index')
        ->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Follow-up created',
            'text'  => 'Follow-up created. Waiting for the student to confirm.',
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

                    // pull row for notifications + emails
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

                            // in-app
                            \App\Support\Notify::student(
                                (int) $row->student_id,
                                'Reassignment approved',
                                'Your counselor change request was approved. You have been reassigned to '.$row->counselor_name.' on '.$whenNice.'.',
                                $studentUrl
                            );
                            if ($row->counselor_id && method_exists(\App\Support\Notify::class, 'counselor')) {
                                \App\Support\Notify::counselor(
                                    (int) $row->counselor_id,
                                    'New reassigned appointment',
                                    'Student: '.$row->student_name.' · '.$whenNice.'.',
                                    $counselorUrl
                                );
                            }

                            // emails
                            $this->sendPlainEmail(
                                $row->student_email ?? null,
                                'LumiCHAT — Reassignment Approved',
                                "Hi {$row->student_name},\n\nYour counselor change request was approved.\nYou are now assigned to {$row->counselor_name} on {$whenNice}.\n"
                            );
                            $this->sendPlainEmail(
                                $row->counselor_email ?? null,
                                'LumiCHAT — Reassigned Appointment',
                                "A student has been reassigned to you.\nStudent: {$row->student_name}\nWhen: {$whenNice}\n"
                            );
                        }
                    } catch (\Throwable $e) {
                        \Log::warning('Notify/email failed on auto-assign change-request', [
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

    /** Centralized notifier used by assign() after status change */
    private function notifyOnStatusChange(object $apRow, string $from, string $to): void
    {
        $whenNice = $apRow->scheduled_at
            ? \Carbon\Carbon::parse($apRow->scheduled_at)->format('M d, Y g:i A')
            : '—';

        $studentUrl   = route('appointment.view', $apRow->id);
        $counselorUrl = \Illuminate\Support\Facades\Route::has('counselor.appointments.show')
            ? route('counselor.appointments.show', $apRow->id)
            : null;
        $adminUrl     = \Illuminate\Support\Facades\Route::has('admin.appointments.show')
            ? route('admin.appointments.show', $apRow->id)
            : null;

        $studentMsgs = [
            'confirmed' => ['Appointment confirmed',    'Your counseling appointment has been confirmed for '.$whenNice.'.'],
            'cancelled' => ['Appointment cancelled',    'Your counseling appointment was cancelled.'],
            'completed' => ['Appointment completed',    'Your counseling session has been marked completed.'],
            'no_show'   => ['Missed appointment',       'You were marked as a no-show for your appointment.'],
            'pending'   => ['Appointment reopened',     'Your appointment was reopened and is now pending.'],
        ];

        $counselorMsgs = [
            'confirmed' => ['New/updated appointment',  'An appointment was confirmed for '.$whenNice.'.'],
            'cancelled' => ['Appointment cancelled',    'An appointment assigned to you was cancelled.'],
            'completed' => ['Appointment completed',    'The appointment has been marked completed.'],
            'no_show'   => ['Missed appointment',       'The student was marked as a no-show.'],
            'pending'   => ['Appointment reopened',     'The appointment was reopened and is now pending.'],
        ];

        // Student
        if (!empty($apRow->student_id) && isset($studentMsgs[$to])) {
            [$title, $body] = $studentMsgs[$to];
            \App\Support\Notify::student((int) $apRow->student_id, $title, $body, $studentUrl);
        }

        // Counselor
        if (!empty($apRow->counselor_id) && isset($counselorMsgs[$to])) {
            [$title, $body] = $counselorMsgs[$to];
            \App\Support\Notify::counselor((int) $apRow->counselor_id, $title, $body, $counselorUrl);
        }

        // Optional: notify all admins for notable changes
        if (in_array($to, ['cancelled','no_show'], true) && $adminUrl) {
            \App\Support\Notify::admins(
                'Appointment status changed',
                sprintf('#%d: %s → %s (%s)', $apRow->id, $from, $to, $whenNice),
                $adminUrl
            );
        }
    }

    public function poll(Request $request): \Illuminate\Http\JsonResponse
    {
        // last value the frontend knows
        $last = $request->query('last');

        // global latest update in tbl_appointments
        $latest = \DB::table('tbl_appointments')->max('updated_at');

        if (!$latest) {
            return response()->json([
                'ok'           => true,
                'has_changes'  => false,
                'last_updated' => null,
            ]);
        }

        $latestStr = (string) $latest;

        return response()->json([
            'ok'           => true,
            'has_changes'  => $last && $latestStr !== $last,  // only true if something changed
            'last_updated' => $latestStr,
        ]);
    }

    /**
     * Public, signed link → student confirms a tentative/urgent appointment.
     * Route: GET /appointments/{id}/confirm  (name: appointments.student.confirm)
     */
    public function studentConfirm(Request $request, int $id)
    {
        // Find current row
        $before = DB::table('tbl_appointments')->where('id', $id)->first();
        abort_unless($before, 404);

        // If it’s already confirmed, just take the happy path
        if ($before->status === 'confirmed') {
            return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
                'icon'  => 'success',
                'title' => 'Already confirmed',
                'text'  => 'Your appointment is already confirmed.',
            ]);
        }

        // Only pending/confirmed may be confirmed via this link
        if (!in_array($before->status, ['pending','confirmed'], true)) {
            return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'This appointment can’t be confirmed anymore.',
            ]);
        }

        // Apply confirmation
        DB::table('tbl_appointments')->where('id', $id)->update([
            'status'                   => 'confirmed',
            'student_confirm_required' => 0,
            'student_confirmed_at'     => now(),
            'updated_at'               => now(),
        ]);

        // Load a minimal row for centralized notifier
        $row = DB::table('tbl_appointments')->where('id', $id)->first(['id','student_id','counselor_id','scheduled_at','status']);

        // In-app notifications
        try {
            $this->notifyOnStatusChange($row, (string)($before->status ?? ''), 'confirmed');
        } catch (\Throwable $e) {
            \Log::notice('notifyOnStatusChange failed (studentConfirm)', ['id'=>$id,'e'=>$e->getMessage()]);
        }

        // Emails (same wording style as elsewhere)
        try {
            $j = $this->joinedApptRow($id);
            if ($j) {
                $whenNice = $this->niceWhen($j->scheduled_at ?? now());
                // student
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Confirmed',
                    "Hi {$j->student_name},\n\nYour appointment has been confirmed.\nWhen: {$whenNice}\nCounselor: {$j->counselor_name}\n\nSee you!"
                );
                // counselor
                $this->sendPlainEmail(
                    $j->counselor_email ?? null,
                    'LumiCHAT — Appointment Confirmed',
                    "A confirmed appointment is on your schedule.\nStudent: {$j->student_name}\nWhen: {$whenNice}\n"
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Status email failed (studentConfirm)', ['id' => $id, 'err' => $e->getMessage()]);
        }

        return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Confirmed',
            'text'  => 'Thanks! Your appointment has been confirmed.',
        ]);
    }

    /**
     * Public, signed link → student declines a tentative/urgent appointment.
     * Route: GET /appointments/{id}/decline  (name: appointments.student.decline)
     */
    public function studentDecline(Request $request, int $id)
    {
        // Find current row
        $before = DB::table('tbl_appointments')->where('id', $id)->first();
        abort_unless($before, 404);

        // If already cancelled, nothing to do
        if (in_array($before->status, ['canceled','cancelled'], true)) {
            return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
                'icon'  => 'info',
                'title' => 'Already cancelled',
                'text'  => 'This appointment is already cancelled.',
            ]);
        }

        // Only pending/confirmed can be declined by student
        if (!in_array($before->status, ['pending','confirmed'], true)) {
            return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
                'icon'  => 'warning',
                'title' => 'Not allowed',
                'text'  => 'This appointment can’t be declined anymore.',
            ]);
        }

        // Apply cancellation
        DB::table('tbl_appointments')->where('id', $id)->update([
            'status'                   => 'canceled',
            'student_confirm_required' => 0,
            'student_confirmed_at'     => null,
            'updated_at'               => now(),
        ]);

        // Load minimal row for notifications
        $row = DB::table('tbl_appointments')->where('id', $id)->first(['id','student_id','counselor_id','scheduled_at','status']);

        // In-app notifications (student, counselor, and admins for visibility)
        try {
            $this->notifyOnStatusChange($row, (string)($before->status ?? ''), 'cancelled');
        } catch (\Throwable $e) {
            \Log::notice('notifyOnStatusChange failed (studentDecline)', ['id'=>$id,'e'=>$e->getMessage()]);
        }

        // Emails
        try {
            $j = $this->joinedApptRow($id);
            if ($j) {
                $whenNice = $this->niceWhen($j->scheduled_at ?? now());
                $this->sendPlainEmail(
                    $j->student_email ?? null,
                    'LumiCHAT — Appointment Cancelled',
                    "Hi {$j->student_name},\n\nYour appointment scheduled on {$whenNice} has been cancelled per your request.\n"
                );
                if (!empty($j->counselor_email)) {
                    $this->sendPlainEmail(
                        $j->counselor_email,
                        'LumiCHAT — Appointment Cancelled',
                        "A student cancelled an appointment.\nStudent: {$j->student_name}\nWhen: {$whenNice}\n"
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Status email failed (studentDecline)', ['id' => $id, 'err' => $e->getMessage()]);
        }

        return redirect()->route('appointment.view', $id)->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Cancelled',
            'text'  => 'Got it. Your appointment has been cancelled.',
        ]);
    }


}
