<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\Appointment;
use App\Models\CaseNote;
use Illuminate\Pagination\LengthAwarePaginator;

class AppointmentController extends Controller
{
    /** how long a slot is (minutes) */
    private const STEP_MINUTES      = 60;
    /** how long after a slot we allow marking no-show (grace minutes after end) */
    private const NO_SHOW_GRACE_MIN = 30;

    /** Resolve the counselor’s primary key used in tbl_appointments.counselor_id */
    private function myCounselorId(): ?int
    {
        $uid  = auth()->id();
        $user = auth()->user();

        // Case A: counselors table links to users via user_id
        if (Schema::hasColumn('tbl_counselors', 'user_id')) {
            $cid = DB::table('tbl_counselors')->where('user_id', $uid)->value('id');
            if ($cid) return (int) $cid;
        }

        // Case B: match by email (common when there is no user_id column)
        if ($user && Schema::hasColumn('tbl_counselors', 'email')) {
            $cid = DB::table('tbl_counselors')->where('email', $user->email)->value('id');
            if ($cid) return (int) $cid;
        }

        // Case C: the counselor guard logs in against tbl_counselors directly
        $exists = DB::table('tbl_counselors')->where('id', $uid)->exists();
        if ($exists) return (int) $uid;

        // No mapping found -> misconfiguration (will show a warning in index)
        return null;
    }

    /** List appointments assigned to this counselor (filters: status, period, q) */
    public function index(Request $r)
    {
        $cid = $this->myCounselorId();

        // If this user isn’t linked to a counselor record, show an empty list + warning.
        if (!$cid) {
            $empty = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10, 1, ['path' => url()->current()]);
            return view('Counselor_Interface.appointments.index', [
                'appointments'          => $empty,
                'reassignedAppointments'=> collect(),
                'status'                => $r->query('status', 'all'),
                'period'                => $r->query('period', 'all'),
                'q'                     => $r->query('q', ''),
            ])->with('swal', [
                'icon'  => 'warning',
                'title' => 'Counselor account not linked',
                'text'  => 'Your user is not linked to a counselor record. Ask admin to set tbl_counselors.user_id.',
            ]);
        }

        // Filters
        $status = $r->query('status', 'all');
        $period = $r->query('period', 'all');
        $q      = trim((string) $r->query('q', ''));
        $now    = now();

        // Safely include counselor-reassignment columns only if they exist
        $hasCrStatus  = \Illuminate\Support\Facades\Schema::hasColumn('tbl_appointments', 'cr_status');
        $hasCrCreated = \Illuminate\Support\Facades\Schema::hasColumn('tbl_appointments', 'cr_created_at');

        // ===== ACTIVE APPOINTMENTS (current counselor_id = $cid) =====
        $select = [
            'a.id',
            'a.scheduled_at',
            'a.created_at as booked_at',
            'a.status',
            \Illuminate\Support\Facades\DB::raw("COALESCE(s.name,'—')  as student_name"),
            \Illuminate\Support\Facades\DB::raw("COALESCE(s.email,'') as student_email"),
        ];
        $select[] = $hasCrStatus
            ? 'a.cr_status'
            : \Illuminate\Support\Facades\DB::raw('NULL as cr_status');
        $select[] = $hasCrCreated
            ? 'a.cr_created_at'
            : \Illuminate\Support\Facades\DB::raw('NULL as cr_created_at');

        $qrb = \Illuminate\Support\Facades\DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
            ->select($select)
            ->where('a.counselor_id', $cid);

        // Filter: status
        if ($status !== 'all') {
            $qrb->where('a.status', $status);
        }

        // Filter: period
        switch ($period) {
            case 'today':
                $qrb->whereDate('a.scheduled_at', $now->toDateString());
                break;
            case 'upcoming':
                $qrb->where('a.scheduled_at', '>=', $now);
                break;
            case 'this_week':
                $qrb->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                break;
            case 'this_month':
                $qrb->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                break;
            case 'past':
                $qrb->where('a.scheduled_at', '<', $now);
                break;
            default:
                // all
                break;
        }

        // Filter: search (student name/email)
        if ($q !== '') {
            $qrb->where(function ($w) use ($q) {
                $w->where('s.name', 'like', "%{$q}%")
                ->orWhere('s.email', 'like', "%{$q}%");
            });
        }

        // Sort: future first (asc), then past (desc); completed at the bottom
        $qrb->orderByRaw("CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN 0 ELSE 1 END", [$now])
            ->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN a.scheduled_at END ASC",  [$now])
            ->orderByRaw("CASE WHEN a.scheduled_at <  ? THEN a.scheduled_at END DESC", [$now]);

        $appointments = $qrb->paginate(10)->withQueryString();

        // ===== REASSIGNED HISTORY (read-only; still visible to past counselor) =====
        $reassignedAppointments = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('tbl_appointment_counselor_history')) {
            $hr = \Illuminate\Support\Facades\DB::table('tbl_appointment_counselor_history as h')
                ->join('tbl_appointments as a', 'a.id', '=', 'h.appointment_id')
                ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
                ->select([
                    'a.id',
                    'a.scheduled_at',
                    'a.created_at as booked_at',
                    \Illuminate\Support\Facades\DB::raw("COALESCE(s.name,'—')  as student_name"),
                    \Illuminate\Support\Facades\DB::raw("COALESCE(s.email,'') as student_email"),
                    'h.status as history_status',
                    'h.changed_at',
                ])
                ->where('h.counselor_id', $cid)
                ->where('h.status', 'reassigned');

            // optional: reuse period filter for these as well (based on scheduled_at)
            switch ($period) {
                case 'today':
                    $hr->whereDate('a.scheduled_at', $now->toDateString());
                    break;
                case 'upcoming':
                    $hr->where('a.scheduled_at', '>=', $now);
                    break;
                case 'this_week':
                    $hr->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
                    break;
                case 'this_month':
                    $hr->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                    break;
                case 'past':
                    $hr->where('a.scheduled_at', '<', $now);
                    break;
                default:
                    // all
                    break;
            }

            // optional: reuse search filter
            if ($q !== '') {
                $hr->where(function ($w) use ($q) {
                    $w->where('s.name', 'like', "%{$q}%")
                    ->orWhere('s.email', 'like', "%{$q}%");
                });
            }

            $reassignedAppointments = $hr
                ->orderByDesc('h.changed_at')
                ->orderByDesc('a.scheduled_at')
                ->get();
        }

        return view('Counselor_Interface.appointments.index', [
            'appointments'           => $appointments,
            'reassignedAppointments' => $reassignedAppointments,
            'status'                 => $status,
            'period'                 => $period,
            'q'                      => $q,
        ]);
    }

    public function show(int $id)
{
    $cid = $this->myCounselorId();
    abort_unless($cid, 404);

    // 1) Try as ACTIVE appointment (current counselor)
    $row = DB::table('tbl_appointments as a')
        ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
        ->select(
            'a.*',
            DB::raw("COALESCE(s.name,'—') as student_name"),
            DB::raw("COALESCE(s.email,'') as student_email"),
            DB::raw("
                TRIM(
                    CONCAT(
                        COALESCE(s.course, ''),
                        CASE 
                            WHEN s.course IS NOT NULL AND s.year_level IS NOT NULL 
                                THEN ' · ' 
                            ELSE '' 
                        END,
                        COALESCE(s.year_level, '')
                    )
                ) as student_program_year
            ")
        )
        ->where('a.id', $id)
        ->where('a.counselor_id', $cid)
        ->first();

    $isHistory        = false;
    $historyChangedAt = null;

    // 2) If not active, try as REASSIGNED history for THIS counselor + THIS appointment
    if (!$row && Schema::hasTable('tbl_appointment_counselor_history')) {
        $hist = DB::table('tbl_appointment_counselor_history as h')
            ->join('tbl_appointments as a', 'a.id', '=', 'h.appointment_id')
            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
            ->select(
                'a.*',
                DB::raw("COALESCE(s.name,'—') as student_name"),
                DB::raw("COALESCE(s.email,'') as student_email"),
                DB::raw("
                    TRIM(
                        CONCAT(
                            COALESCE(s.course, ''),
                            CASE 
                                WHEN s.course IS NOT NULL AND s.year_level IS NOT NULL 
                                    THEN ' · ' 
                                ELSE '' 
                            END,
                            COALESCE(s.year_level, '')
                        )
                    ) as student_program_year
                "),
                'h.status as history_status',
                'h.changed_at'
            )
            ->where('h.counselor_id', $cid)   // ✅ only this counselor
            ->where('h.appointment_id', $id)  // ✅ only this appointment
            ->where('h.status', 'reassigned')
            ->orderByDesc('h.changed_at')
            ->first();

        if ($hist) {
            $row              = $hist;
            $isHistory        = true;
            $historyChangedAt = $hist->changed_at;
        }
    }

    abort_unless($row, 404);

    $caseNote = CaseNote::where('appointment_id', $row->id)->first();

    return view('Counselor_Interface.appointments.show', [
        'appointment'      => $row,
        'caseNote'         => $caseNote,
        'isHistory'        => $isHistory,
        'historyChangedAt' => $historyChangedAt,
    ]);
}


    public function caseNotePdf(int $id)
    {
        $cid = $this->myCounselorId(); abort_unless($cid, 404);

 $appointment = DB::table('tbl_appointments as a')
    ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
    ->select(
        'a.*',
        DB::raw("COALESCE(s.name,'—') as student_name"),
        DB::raw("COALESCE(s.email,'') as student_email"),
        DB::raw("
            TRIM(
                CONCAT(
                    COALESCE(s.course, ''),
                    CASE 
                        WHEN s.course IS NOT NULL AND s.year_level IS NOT NULL 
                            THEN ' · ' 
                        ELSE '' 
                    END,
                    COALESCE(s.year_level, '')
                )
            ) as student_program_year
        ")
    )
    ->where('a.id', $id)
    ->where('a.counselor_id', $cid)
    ->first();


        $caseNote = \App\Models\CaseNote::where('appointment_id', $appointment->id)->first();
        abort_unless($caseNote, 404);

        // Optional logo embed (set to your actual path or null)
        $logoPath = public_path('images/chatbot.png');
        $logoData = file_exists($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait')->setOptions([
            // Either omit defaultFont or use a built-in one like Helvetica
            'defaultFont' => 'Helvetica',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ]);

        $pdf->loadView('Counselor_Interface.appointments.pdf-case-note', [
            'appointment' => $appointment,
            'note'        => $caseNote,
            'generatedAt' => now()->format('F d, Y · g:i A'),
            'logoData'    => $logoData,
        ]);

        $filename = 'Case_Note_Appointment_'.$appointment->id.'.pdf';
        return request()->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }

    /** Collapse whitespace and trim (registration-style) */
private function trimCollapse(?string $v): ?string
{
    if ($v === null) return null;

    if (class_exists('\Normalizer')) {
        $v = \Normalizer::normalize($v, \Normalizer::FORM_KC) ?: $v; // NFKC
    }

    return trim(preg_replace('/\s+/u', ' ', $v));
}

/** Digits only with optional single leading + */
private function tidyPhone(?string $v): ?string
{
    if ($v === null) return null;

    if (class_exists('\Normalizer')) {
        $v = \Normalizer::normalize($v, \Normalizer::FORM_KC) ?: $v;
    }

    // keep digits, keep + only if it is the first char
    $v = preg_replace('/(?!^)\+/', '', $v); // remove extra +'s
    $v = preg_replace('/[^\d+]/', '', $v);  // strip non-digits
    return $v;
}


    /** Sanitize a whole case_note payload */
    private function sanitizeCaseNote(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            if ($k === 'emergency_contact_no') {
                $out[$k] = $this->tidyPhone($v);
            } elseif ($k === 'date') {
                $vv = $this->trimCollapse($v);
                $out[$k] = $vv ?: null;
            } else {
                $out[$k] = $this->trimCollapse($v);
            }
        }
        return $out;
    }
    public function storeCaseNote(int $id, Request $request)
    {
        $cid = $this->myCounselorId(); abort_unless($cid, 404);

        $appointment = DB::table('tbl_appointments')
            ->where('id', $id)
            ->where('counselor_id', $cid)
            ->first();
        abort_unless($appointment, 404);

        if (!in_array(strtolower((string)$appointment->status), ['completed'], true)) {
            return back()->with('swal', [
                'icon'=>'error','title'=>'Not allowed yet',
                'text'=>'You can only save the Case Note after the appointment is Completed.',
            ]);
        }

        $rules = [
            // Header
            'case_note.student_name'             => ['required','string','max:255'],
            'case_note.date'                     => ['required','date','before_or_equal:today'], // ✅ not future
            'case_note.program_year'             => ['nullable','string','max:255'],
            'case_note.address'                  => ['nullable','string','max:255'],

            // I–V
            'case_note.presenting_problem'       => ['required','string','max:4000'],
            'case_note.observations'             => ['required','string','max:4000'],
            'case_note.interventions'            => ['required','string','max:4000'],
            'case_note.response'                 => ['required','string','max:4000'],
            'case_note.plan_followup'            => ['required','string','max:4000'],

            // VI
            'case_note.emergency_contact_person' => ['required','string','max:255'],
            'case_note.emergency_relationship'   => ['required','string','max:255'],
            'case_note.emergency_contact_no'     => ['required','regex:/^\+?\d{10,15}$/'], // ✅ phone format
            'case_note.emergency_address'        => ['required','string','max:255'],
        ];

       $messages = [
            'case_note.presenting_problem.required'       => 'Presenting Problem is required.',
            'case_note.observations.required'             => 'Observations is required.',
            'case_note.interventions.required'            => 'Interventions is required.',
            'case_note.response.required'                 => 'Student’s Response / Insight is required.',
            'case_note.plan_followup.required'            => 'Plan / Follow-Up is required.',
            'case_note.emergency_contact_person.required' => 'Emergency contact person is required.',
            'case_note.emergency_relationship.required'   => 'Emergency relationship is required.',
            'case_note.emergency_contact_no.required'     => 'Emergency contact number is required.',
            'case_note.emergency_address.required'        => 'Emergency address is required.',
            'case_note.student_name.required'             => 'Student name is required.',
            'case_note.date.required'                     => 'Date is required.',

            // ✅ missing but needed
            'case_note.emergency_contact_no.regex'        => 'Enter a valid contact number (10–15 digits, optional + at the start).',
            'case_note.date.date'                         => 'Date must be a valid calendar date.',
            'case_note.date.before_or_equal'              => 'Date cannot be in the future.',

            // (optional but nice to have if you keep max rules)
            'case_note.student_name.max'                  => 'Student name is too long.',
            'case_note.program_year.max'                  => 'Program & Year is too long.',
            'case_note.address.max'                       => 'Address is too long.',
            'case_note.emergency_contact_person.max'      => 'Contact person is too long.',
            'case_note.emergency_relationship.max'        => 'Relationship is too long.',
            'case_note.emergency_address.max'             => 'Emergency address is too long.',
            'case_note.*.max'                             => 'This field exceeds the allowed length.', // catch-all fallback
        ];

        // 👉 Single validator, explicitly disable stop-on-first-failure
        $validator = \Validator::make($request->all(), $rules, $messages);
        $validator->stopOnFirstFailure(false);
        $validated = $validator->validate();

        // Sanitize like registration page
        $cn = $this->sanitizeCaseNote($validated['case_note'] ?? []);

        $existing    = CaseNote::where('appointment_id', $appointment->id)->first();
        $counselorId = $this->myCounselorId();
        $noteDate    = !empty($cn['date']) ? Carbon::parse($cn['date'])->toDateString() : now()->toDateString();

        CaseNote::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'counselor_id' => $counselorId,
                'student_id'   => $appointment->student_id ?? null,
                'student_name' => $cn['student_name'] ?? $appointment->student_name ?? null,
                'note_date'    => $noteDate,
                'program_year' => $cn['program_year'] ?? null,
                'address'      => $cn['address'] ?? null,

                'presenting_problem' => $cn['presenting_problem'] ?? null,
                'observations'       => $cn['observations'] ?? null,
                'interventions'      => $cn['interventions'] ?? null,
                'response'           => $cn['response'] ?? null,
                'plan_followup'      => $cn['plan_followup'] ?? null,

                'emergency_contact_person' => $cn['emergency_contact_person'] ?? null,
                'emergency_relationship'   => $cn['emergency_relationship'] ?? null,
                'emergency_contact_no'     => $cn['emergency_contact_no'] ?? null,
                'emergency_address'        => $cn['emergency_address'] ?? null,

                'created_by' => $existing?->created_by ?? Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        return back()->with('swal', [
            'icon'  => 'success',
            'title' => 'Case Note saved',
            'text'  => 'Your counselor case note has been saved successfully.',
        ]);
    }

    /** POST /counselor/appointments/{id}/no-show — after grace */
    public function markNoShow(int $id)
    {
        $cid = $this->myCounselorId(); abort_unless($cid, 404);

        $row = DB::table('tbl_appointments')->where('id', $id)->where('counselor_id', $cid)->first();
        abort_unless($row, 404);

        if (!in_array($row->status, ['pending','confirmed'], true)) {
            return back()->with('swal', ['icon'=>'warning','title'=>'Not allowed','text'=>'Only pending/confirmed can be set to No-Show.']);
        }

        $start   = Carbon::parse($row->scheduled_at);
        $end     = $start->copy()->addMinutes(self::STEP_MINUTES);
        $allowed = $end->copy()->addMinutes(self::NO_SHOW_GRACE_MIN);

        if ($allowed->isFuture()) {
            return back()->with('swal', ['icon'=>'warning','title'=>'Too early','text'=>'You can mark No-Show after the slot passes the grace period.']);
        }

        DB::table('tbl_appointments')->where('id', $id)->update([
            'status'     => 'no_show',
            'updated_at' => now(),
        ]);

        return back()->with('swal', ['icon'=>'success','title'=>'Marked as No-Show']);
    }

    /** Counselor saves final report (only after completed, and must own the appt) */
    public function saveReport(Request $r, int $id)
    {
        $cid = $this->myCounselorId(); abort_unless($cid, 404);

        $data = $r->validate([
            'diagnosis'  => ['required','string','max:4000'],
            'final_note' => ['nullable','string','max:4000'],
        ]);

        $a = DB::table('tbl_appointments')->where('id', $id)->where('counselor_id', $cid)->first();
        abort_unless($a, 404);

        if ($a->status !== 'completed') {
            return back()->with('swal', ['icon'=>'warning','title'=>'Not allowed','text'=>'You can save the diagnosis only for completed appointments.']);
        }

        DB::table('tbl_diagnosis_reports')->insert([
            'appointment_id' => $a->id,
            'student_id'     => $a->student_id,
            'counselor_id'   => $cid,
            'diagnosis_result' => $data['diagnosis'],  
            'notes'            => $data['final_note'] ?? null, 
            'updated_at'     => now(),
        ]);

        return back()->with('swal', ['icon'=>'success','title'=>'Saved','text'=>'Diagnosis report saved.']);
    }

    public function followUpForm(int $id)
    {
        $cid = $this->myCounselorId(); // your existing helper

        $appointment = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->select([
                'a.*',
                'c.name  as counselor_name',
                'c.email as counselor_email',
            ])
            ->where('a.id', $id)
            ->where('a.counselor_id', $cid) // must belong to this counselor
            ->first();

        abort_unless($appointment, 404);

        $suggest = [
            'date' => Carbon::now()->addWeek()->toDateString(),
            'time' => '09:00',
        ];

        return view('Counselor_Interface.appointments.follow-up', [
            'appointment' => $appointment,
            'suggest'     => $suggest,
        ]);
    }

    /** Create the follow-up booked to this counselor if free; else ask to pick another time. */
    public function followUpStore(Request $r, int $id)
    {
        $cid = $this->myCounselorId(); abort_unless($cid, 404);

        $ap = DB::table('tbl_appointments')->where('id', $id)->where('counselor_id', $cid)->first();
        abort_unless($ap, 404);

        if ($ap->status !== 'completed') {
            return back()->with('swal', ['icon'=>'warning','title'=>'Not allowed','text'=>'Create a follow-up only after completion.']);
        }

        $data = $r->validate([
            'date' => ['required','date_format:Y-m-d'],
            'time' => ['required','regex:/^\d{2}:\d{2}$/'],
            'note' => ['nullable','string','max:4000'],
        ]);

        $scheduledAt = Carbon::parse("{$data['date']} {$data['time']}:00");
        if ($scheduledAt->lte(now())) {
            return back()->withErrors([
                'date' => 'Pick a future date/time for the follow-up.',
            ])->withInput();
        }
        // Ensure counselor is free at that slot
        $busy = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['pending','confirmed','completed'])
            ->exists();
        if ($busy) {
            return back()->with('swal', ['icon'=>'warning','title'=>'Not available','text'=>'You are busy at that time. Pick another slot.'])->withInput();
        }

        DB::table('tbl_appointments')->insert([
            'student_id'   => $ap->student_id,
            'counselor_id' => $cid,
            'scheduled_at' => $scheduledAt,
            'status'       => 'confirmed', // counselor-created follow-up is confirmed
            'note'         => $data['note'] ?? null,
            'parent_id'    => $ap->id,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->route('counselor.appointments.index')
            ->with('swal', ['icon'=>'success','title'=>'Follow-up created','text'=>'The follow-up appointment has been confirmed.']);
    }

    /** Ensure a date falls Mon–Fri (Sun->Mon 9:00, Sat->Mon 9:00). */
    private function nextWeekdayMonToFri(Carbon $dt): Carbon
    {
        $dow = (int) $dt->dayOfWeek; // 0 Sun .. 6 Sat
        if ($dow === 0) return $dt->addDay()->setTime(9,0,0);
        if ($dow === 6) return $dt->addDays(2)->setTime(9,0,0);
        return $dt;
    }

    /** Optional: counselor’s single appointment PDF */
   public function exportShowPdf(Request $request, int $id)
{
    $cid = $this->myCounselorId();
    abort_unless($cid, 404);

    // Same appointment lookup as caseNotePdf
    $appointment = DB::table('tbl_appointments as a')
    ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id')
    ->select(
        'a.*',
        DB::raw("COALESCE(s.name,'—') as student_name"),
        DB::raw("COALESCE(s.email,'') as student_email"),
        DB::raw("
            TRIM(
                CONCAT(
                    COALESCE(s.course, ''),
                    CASE 
                        WHEN s.course IS NOT NULL AND s.year_level IS NOT NULL 
                            THEN ' · ' 
                        ELSE '' 
                    END,
                    COALESCE(s.year_level, '')
                )
            ) as student_program_year
        ")
    )
    ->where('a.id', $id)
    ->where('a.counselor_id', $cid)
    ->first();


    abort_unless($appointment, 404);

    // Pull the case note for this appointment
    $caseNote = \App\Models\CaseNote::where('appointment_id', $appointment->id)->first();
    abort_unless($caseNote, 404);

    // Optional logo embed
    $logoPath = public_path('images/chatbot.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;

    $pdf = app('dompdf.wrapper');
    $pdf->setPaper('a4', 'portrait')->setOptions([
        'defaultFont'          => 'Helvetica',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => true,
    ]);

    // 👉 Use the existing case-note template
    $pdf->loadView('Counselor_Interface.appointments.pdf-case-note', [
        'appointment' => $appointment,
        'note'        => $caseNote,
        'generatedAt' => now()->format('F d, Y · g:i A'),
        'logoData'    => $logoData,
    ]);

    $filename = 'Case_Note_Appointment_'.$appointment->id.'.pdf';

    return $request->boolean('download')
        ? $pdf->download($filename)
        : $pdf->stream($filename);
}

    public function status(Request $request, int $id)
    {
        $cid = $this->myCounselorId();
        abort_unless($cid, 404);

        $appt = DB::table('tbl_appointments')
            ->where('id', $id)
            ->where('counselor_id', $cid)
            ->first();
        abort_unless($appt, 404);

        $now      = now();
        $startAt  = Carbon::parse($appt->scheduled_at);
        $graceMin = 10;

        // ✅ FIX: read the action from the request
        $action  = (string) $request->input('action', '');
        $allowed = ['confirm', 'start', 'done', 'no_show'];
        if (!in_array($action, $allowed, true)) {
            return back()->withErrors('Unknown action.')->withInput();
        }

        // Normalize current status
        $status = strtolower((string) $appt->status);

        // Guard rails per action
        if ($action === 'confirm') {
            if ($status !== 'pending') {
                return back()->withErrors('Only pending appointments can be confirmed.');
            }
            DB::table('tbl_appointments')->where('id', $id)->update([
                'status'     => 'confirmed',
                'updated_at' => $now,
            ]);
            return back()->with('swal', ['title' => 'Confirmed', 'text' => 'Appointment confirmed.']);
        }

        if ($action === 'start') {
            if ($status !== 'confirmed') {
                return back()->withErrors('Only confirmed appointments can be started.');
            }
            if ($now->lt($startAt->copy()->subMinutes($graceMin))) {
                return back()->withErrors("You can start within {$graceMin} minutes before the scheduled time.");
            }
            DB::table('tbl_appointments')->where('id', $id)->update([
                'status'     => 'ongoing',
                // 'started_at' => $now,   // ❌ remove (column doesn't exist)
                'updated_at' => $now,
            ]);
            return back()->with('swal', ['title' => 'Session started', 'text' => 'Status set to Ongoing.']);
        }

        if ($action === 'done') {
            if (!in_array($status, ['confirmed', 'ongoing'], true)) {
                return back()->withErrors('Only confirmed/ongoing appointments can be ended.');
            }
            if ($now->lt($startAt)) {
                return back()->withErrors('You can only end after the scheduled start time.');
            }
            DB::table('tbl_appointments')->where('id', $id)->update([
                'status'     => 'completed',
                // 'ended_at'   => $now,   // ❌ remove
                'updated_at' => $now,
            ]);
            return back()->with('swal', ['title' => 'Completed', 'text' => 'Appointment marked as Completed.']);
        }

        if ($action === 'no_show') {
            if (!in_array($status, ['pending', 'confirmed'], true)) {
                return back()->withErrors('Only pending/confirmed can be marked No-Show.');
            }
            DB::table('tbl_appointments')->where('id', $id)->update([
                'status'     => 'no_show',
                'updated_at' => $now,
            ]);
            return back()->with('swal', ['title' => 'Marked No-Show', 'text' => 'Student marked as No-Show.']);
        }

        // Fallback (shouldn’t hit)
        return back();
    }
    
   public function followUpSlots(Request $request, \App\Models\Appointment $appointment)
    {
        $cid = $this->myCounselorId();

        // Keep UI happy: never 404—return reasons.
        if (!$cid) {
            return response()->json(['reason' => 'not_counselor']);
        }
        if ((int)$appointment->counselor_id !== (int)$cid) {
            return response()->json(['reason' => 'not_owner']);
        }

        $date = (string) $request->query('date', '');
        if ($date === '') {
            return response()->json(['reason' => 'no_date']);
        }

        try {
            $day = \Carbon\Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['reason' => 'bad_date']);
        }

        if ($day->isPast())    return response()->json(['reason' => 'past']);
        // Use ISO weekday: 1=Mon ... 7=Sun (match student side + your data)
        $isoDow = (int) $day->isoWeekday();
        if ($isoDow < 1 || $isoDow > 5) return response()->json(['reason' => 'weekend']);

        // Accepting flag: align with student controller
        $accepting = \Illuminate\Support\Facades\DB::table('tbl_counselors')
            ->where('id', $cid)
            ->value('is_accepting_appointments');
        if ((string)$accepting === '0') {
            return response()->json(['reason' => 'disabled', 'blocked' => true]);
        }

        // Get availability rows (date-specific overrides recurring)
        $ranges = $this->rangesForCounselorOnDateIso($cid, $day);
        if ($ranges->isEmpty()) {
            return response()->json(['reason' => 'no_availability']);
        }

        // Build slots inside AVAILABLE ranges; BLOCKED overrides.
        $slotMinutes = (int) self::STEP_MINUTES; // 60
        $slots = [];
        foreach ($ranges as $r) {
            if (($r->slot_type ?? 'available') !== 'available') continue;
            if (!is_string($r->start_time) || !is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') continue;

            $start = \Carbon\Carbon::parse($date.' '.$r->start_time)->second(0);
            $end   = \Carbon\Carbon::parse($date.' '.$r->end_time)->second(0);

            for ($t = $start->copy(); $t->lt($end); $t->addMinutes($slotMinutes)) {
                $tEnd = $t->copy()->addMinutes($slotMinutes);

                // skip past times if same day
                if ($day->isToday() && $t->lte(now())) continue;

                // respect blocks
                if (!$this->slotAllowedForCounselorIso($cid, $t, $tEnd, $day)) continue;

                $hhmm = $t->format('H:i');

                // Busy check: counselor has appt at exact start with active statuses
                $taken = DB::table('tbl_appointments')
                    ->where('counselor_id', $cid)
                    ->whereDate('scheduled_at', $day->toDateString())
                    ->whereTime('scheduled_at', $hhmm.':00')
                    ->whereIn('status', ['pending','confirmed','ongoing'])
                    ->exists();

                $slots[] = [
                    'label'     => $t->format('g:i A'),
                    'value'     => $hhmm,
                    'available' => $taken ? 0 : 1,
                ];
            }
        }

        if (!$slots) return response()->json(['reason' => 'no_slots']);

        // If everything is taken
        $open = collect($slots)->sum(fn($s) => $s['available'] ? 1 : 0);
        if ($open === 0) return response()->json(['reason' => 'fully_booked']);

        // Unique + sorted
        $slots = collect($slots)->unique('value')->sortBy('value')->values()->all();

        return response()->json(['slots' => $slots]);
    }

    /**
     * Counselor availability rows for a date:
     * - Prefer date-specific rows; else fallback to recurring by ISO weekday (1..7).
     * - Reads start_time, end_time, slot_type ('available'|'blocked').
     */
    private function rangesForCounselorOnDateIso(int $cid, \Carbon\Carbon $date): \Illuminate\Support\Collection
    {
        $isoDow = $date->isoWeekday(); // 1..7

        $dated = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereDate('date', $date->toDateString())
            ->orderBy('start_time')
            ->get(['start_time','end_time','slot_type']);

        if ($dated->count() > 0) return $dated;

        return DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $cid)
            ->whereNull('date')
            ->where('weekday', $isoDow) // 🔑 ISO alignment
            ->orderBy('start_time')
            ->get(['start_time','end_time','slot_type']);
    }

    /**
     * True if the slot is within ANY 'available' range AND NOT in ANY 'blocked' range
     * after date-specific override. (Matches student side behavior.)
     */
    private function slotAllowedForCounselorIso(int $cid, \Carbon\Carbon $slotStart, \Carbon\Carbon $slotEnd, \Carbon\Carbon $date): bool
    {
        $rows = $this->rangesForCounselorOnDateIso($cid, $date);

        $insideAvailable = false;
        foreach ($rows as $r) {
            if (!is_string($r->start_time) || !is_string($r->end_time) || $r->start_time === '' || $r->end_time === '') continue;

            $st = \Carbon\Carbon::parse($date->toDateString().' '.$r->start_time);
            $en = \Carbon\Carbon::parse($date->toDateString().' '.$r->end_time);

            $inside = $slotStart->gte($st) && $slotEnd->lte($en);
            if (!$inside) continue;

            if (($r->slot_type ?? 'available') === 'blocked') {
                return false; // any block wins
            }
            if (($r->slot_type ?? 'available') === 'available') {
                $insideAvailable = true;
            }
        }
        return $insideAvailable;
    }


}
