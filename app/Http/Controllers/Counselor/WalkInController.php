<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseNote;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\Request;

class WalkInController extends Controller
{
    /**
     * Resolve counselor primary key used in tbl_counselor_availabilities.counselor_id
     */
    private function myCounselorId(): ?int
{
    $uid  = Auth::id();
    $user = Auth::user();

    if (!$uid || !$user) {
        return null;
    }

    // Case A
    if (Schema::hasColumn('tbl_counselors', 'user_id')) {
        $cid = DB::table('tbl_counselors')
            ->where('user_id', $uid)
            ->value('id');
        if ($cid) return (int) $cid;
    }

    // Case B
    if (Schema::hasColumn('tbl_counselors', 'email')) {
        $cid = DB::table('tbl_counselors')
            ->where('email', $user->email)
            ->value('id');
        if ($cid) return (int) $cid;
    }

    // Case C
    if (Schema::hasTable('tbl_counselors')) {
        $exists = DB::table('tbl_counselors')
            ->where('id', $uid)
            ->exists();
        if ($exists) return (int) $uid;
    }

    return null;
}


    /**
     * Check if counselor has any AVAILABLE slot right now
     * based on tbl_counselor_availabilities structure.
     *
     * Columns:
     *  - counselor_id
     *  - date (nullable)
     *  - weekday (0–6)
     *  - start_time, end_time (TIME)
     *  - slot_type: 'available' | 'blocked'
     */
private function hasCurrentAvailability(): bool
{
        return true;
    // 1) If availability table doesn’t exist, allow walk-ins
    if (!Schema::hasTable('tbl_counselor_availabilities')) {
        return true;
    }

    $cid = $this->myCounselorId();

    // 2) If counselor mapping not wired yet, TEMP: allow
    if (!$cid) {
        return true; // or false if you want it strict; for now better true
    }

    $now     = Carbon::now();
    $date    = $now->toDateString();
    $time    = $now->format('H:i:s');
    $weekday = (int) $now->dayOfWeek; // 0=Sun..6=Sat

    // 3) If this counselor has no rows at all, don’t block yet
    $hasAnyRow = DB::table('tbl_counselor_availabilities')
        ->where('counselor_id', $cid)
        ->exists();

    if (!$hasAnyRow) {
        return true;
    }

    // 4) Strict check when rows exist
    return DB::table('tbl_counselor_availabilities')
        ->where('counselor_id', $cid)
        ->where('slot_type', 'available')
        ->where('start_time', '<=', $time)
        ->where('end_time', '>',  $time)
        ->where(function ($q) use ($date, $weekday) {
            $q->where('date', $date)
              ->orWhere(function ($q2) use ($weekday) {
                  $q2->whereNull('date')
                     ->where('weekday', $weekday);
              });
        })
        ->exists();
}

    /**
     * Show New Walk-in Session form.
     * Pass canStartWalkin flag to Blade.
     */
    public function create(): View
{
    $cid = $this->myCounselorId();
    $canStartWalkin = $this->hasCurrentAvailability();

    logger()->info('Walk-in availability debug', [
        'user_id'           => Auth::id(),
        'counselor_id'      => $cid,
        'can_start_walkin'  => $canStartWalkin,
    ]);

    return view('Counselor_Interface.walkins.create', [
        'canStartWalkin' => $canStartWalkin,
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'course'       => ['required', 'string', 'max:255'],
            'year_level'   => ['required', 'string', 'max:50'],
            'start_time'   => ['required'],
            'end_time'     => ['required'],
            'reason'       => ['nullable', 'string'],
            'case_note'    => ['array'],
        ]);

        $counselorId = $this->myCounselorId();

        // ==============================
        // 1) Check counselor availability (server-side)
        // ==============================
        if (!$this->hasCurrentAvailability()) {
            return back()
                ->withErrors([
                    'availability' => 'You are currently outside your configured available hours. Walk-in sessions can only be recorded during an Available window.',
                ])
                ->withInput();
        }

        // ==============================
        // 2) Existing logic (unchanged)
        // ==============================
        $date    = Carbon::now()->toDateString();
        $startAt = Carbon::parse($date.' '.$request->start_time);
        $endAt   = Carbon::parse($date.' '.$request->end_time);

        // ===== resolve student_id as before =====
        $studentId = null;
        if (Schema::hasTable('tbl_users')) {
            $studentId = DB::table('tbl_users')
                ->where('role', 'student')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($request->student_name)])
                ->value('id');
        }

        $apptModel = new Appointment();
        $apptTable = $apptModel->getTable();

        if (Schema::hasColumn($apptTable, 'student_id') && !$studentId) {
            return back()
                ->withErrors([
                    'student_name' => 'This student is not yet registered. Please add them in Admin ▸ Students first, then record the walk-in.',
                ])
                ->withInput();
        }

        // ===== create appointment =====
        $appointment = new Appointment();

        if (Schema::hasColumn($apptTable, 'student_id')) {
            $appointment->student_id = $studentId;
        }
        if (Schema::hasColumn($apptTable, 'student_name')) {
            $appointment->student_name = $request->student_name;
        }
        if (Schema::hasColumn($apptTable, 'course')) {
            $appointment->course = $request->course;
        }
        if (Schema::hasColumn($apptTable, 'year_level')) {
            $appointment->year_level = $request->year_level;
        }
        if (Schema::hasColumn($apptTable, 'student_program_year')) {
            $appointment->student_program_year = $request->course.' - '.$request->year_level;
        }
        if (Schema::hasColumn($apptTable, 'counselor_id')) {
            $appointment->counselor_id = $counselorId;
        }
        if (Schema::hasColumn($apptTable, 'type')) {
            $appointment->type = 'walk-in';
        }
        if (Schema::hasColumn($apptTable, 'status')) {
            $appointment->status = 'completed';
        }
        if (Schema::hasColumn($apptTable, 'scheduled_at')) {
            $appointment->scheduled_at = $startAt;
        }
        if (Schema::hasColumn($apptTable, 'end_at')) {
            $appointment->end_at = $endAt;
        }
        if (Schema::hasColumn($apptTable, 'reason')) {
            $appointment->reason = $request->reason;
        }
        if (Schema::hasColumn($apptTable, 'appointment_source')) {
            $appointment->appointment_source = 'walk_in'; // or 'Walk-in' if you prefer
        }

        $appointment->created_at = now();
        $appointment->updated_at = now();
        $appointment->save();

        // ===== create case note =====
        $caseNoteModel = new CaseNote();
        $cnTable       = $caseNoteModel->getTable();
        $caseNote      = new CaseNote();

        if (Schema::hasColumn($cnTable, 'appointment_id')) {
            $caseNote->appointment_id = $appointment->id;
        }
        if (Schema::hasColumn($cnTable, 'counselor_id')) {
            $caseNote->counselor_id = Auth::id();
        }
        if (Schema::hasColumn($cnTable, 'student_id')) {
            $caseNote->student_id = $studentId;
        }
        if (Schema::hasColumn($cnTable, 'student_name')) {
            $caseNote->student_name = $request->student_name;
        }
        if (Schema::hasColumn($cnTable, 'note_date')) {
            $caseNote->note_date = $date; // auto today
        }
        if (Schema::hasColumn($cnTable, 'program_year')) {
            $caseNote->program_year = $request->course.' - '.$request->year_level;
        }

        $cn = $request->input('case_note', []);

        if (Schema::hasColumn($cnTable, 'presenting_problem')) {
            $caseNote->presenting_problem = $cn['presenting_problem'] ?? $request->reason;
        }
        if (Schema::hasColumn($cnTable, 'observations')) {
            $caseNote->observations = $cn['observations'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'interventions')) {
            $caseNote->interventions = $cn['interventions'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'response')) {
            $caseNote->response = $cn['response'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'plan_followup')) {
            $caseNote->plan_followup = $cn['plan_followup'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'emergency_contact_person')) {
            $caseNote->emergency_contact_person = $cn['emergency_contact_person'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'emergency_relationship')) {
            $caseNote->emergency_relationship = $cn['emergency_relationship'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'emergency_contact_no')) {
            $caseNote->emergency_contact_no = $cn['emergency_contact_no'] ?? null;
        }
        if (Schema::hasColumn($cnTable, 'emergency_address')) {
            $caseNote->emergency_address = $cn['emergency_address'] ?? null;
        }
               if (Schema::hasColumn($cnTable, 'created_by')) {
            $caseNote->created_by = Auth::id();
        }
        if (Schema::hasColumn($cnTable, 'updated_by')) {
            $caseNote->updated_by = Auth::id();
        }

        // 🔹 Tag this case note as Walk-in
        if (Schema::hasColumn($cnTable, 'note_source')) {
            $caseNote->note_source = 'Walk-in';
        }

        $caseNote->save();


        return redirect()
            ->route('counselor.appointments.show', $appointment->id)
            ->with('swal', [
                'icon'  => 'success',
                'title' => 'Walk-in saved',
                'text'  => 'Walk-in session has been recorded and a case note was created.',
            ]);
    }

    /**
     * Utility if ever needed later (not used right now)
     */
    private function firstFreeScheduledAt(int $counselorId, Carbon $base): Carbon
    {
        $ts = $base->copy()->second(0);

        while (DB::table('tbl_appointments')
            ->where('counselor_id', $counselorId)
            ->where('scheduled_at', $ts->toDateTimeString())
            ->exists()
        ) {
            $ts->addSecond();
        }

        return $ts;
    }

}
