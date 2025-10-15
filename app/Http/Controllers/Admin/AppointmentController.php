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

class AppointmentController extends Controller
{
    // ==== Flash keys ====
    private const FLASH_SWAL = 'swal';

    // ==== Filters ====
    private const STATUS_ALL = 'all';
    private const PERIOD_ALL = 'all';
    private const STATUSES   = ['pending','confirmed','canceled','completed'];
    private const PERIODS    = ['all','upcoming','today','this_week','this_month','past'];

    public function __construct(
        protected AppointmentRepositoryInterface $appointments
    ) {}  

    /** List appointments with optional filters + search (counselor name). */
    public function index(Request $r): View
    {
        $status = \in_array($r->query('status'), self::STATUSES, true) ? $r->query('status') : self::STATUS_ALL;
        $period = \in_array($r->query('period'), self::PERIODS, true)   ? $r->query('period') : self::PERIOD_ALL;
        $q      = \trim((string) $r->query('q', ''));

        $appointments = $this->appointments->paginateWithNames([
            'status' => $status,
            'period' => $period,
            'q'      => $q,
        ], 10);

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
        $action = $r->input('action'); // 'confirm' | 'done'
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

        // ✅ Success path: fetch the fresh row and clear highs if now completed
        $appt = DB::table('tbl_appointments')
            ->select('id','student_id','status')
            ->where('id', $id)
            ->first();

        // ✅ Success path: fetch the fresh row and clear highs if now completed
        $appt = DB::table('tbl_appointments')
            ->select('id','student_id','status')
            ->where('id', $id)
            ->first();

        if ($appt && $appt->status === 'completed') {
            // build updates only for columns that exist
            $updates = [
                'risk_level' => null,   // or 'low' if you prefer a defined state
                'updated_at' => now(),
            ];

            // only set risk_score if the column exists
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

        $slotStart = \Carbon\Carbon::parse($appointment->scheduled_at);
        $slotEnd   = $slotStart->copy()->addMinutes(30); // matches your slot length
        $dow       = $slotStart->isoWeekday();           // 1..7

        $counselors = \DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name','email']);

        foreach ($counselors as $c) {
            // fits counselor’s weekly schedule?
            $fits = \DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $c->id)
                ->where('weekday', $dow)
                ->where('start_time', '<=', $slotStart->format('H:i:s'))
                ->where('end_time',   '>=', $slotEnd->format('H:i:s'))
                ->exists();

            // already booked at that exact time?
            $booked = \DB::table('tbl_appointments')
                ->where('counselor_id', $c->id)
                ->where('scheduled_at', $appointment->scheduled_at)
                ->whereIn('status', ['pending','confirmed','completed'])
                ->exists();

            $c->available   = ($fits && !$booked) ? 1 : 0;
            $c->busy_reason = null;

            if (!$fits) {
                $c->busy_reason = 'Off-hours';
            } elseif ($booked) {
                $c->busy_reason = 'Has an appointment at this time';
            }
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

        return redirect()->route('admin.appointments.index')->with(self::FLASH_SWAL, [
            'icon'  => 'success',
            'title' => 'Counselor assigned',
            'text'  => 'Appointment has been confirmed.',
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

                DB::table('tbl_appointments')->insert([
                    'student_id'   => $appointment->student_id,
                    'counselor_id' => $counselorId,
                    'scheduled_at' => $scheduledAt,
                    'status'       => 'confirmed', // auto-confirm so student can’t cancel
                    'note'         => $data['note'] ?? null,
                    'parent_id'    => $appointment->id,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
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
}
