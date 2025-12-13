<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CaseNote;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
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
     * Map short year (1st,2nd,3rd,4th) to the format used in tbl_users (1st Year, etc).
     */
    private function mapYearLevelForUser(?string $short): ?string
    {
        if (!$short) return null;

        return match ($short) {
            '1st' => '1st Year',
            '2nd' => '2nd Year',
            '3rd' => '3rd Year',
            '4th' => '4th Year',
            default => $short,
        };
    }

    /**
 * Check if counselor has any AVAILABLE slot right now.
 * ✅ Weekends (Sat/Sun) are auto-allowed for testing.
 */
private function hasCurrentAvailability(): bool
{
    // 0 = Sunday, 6 = Saturday
    $now = Carbon::now();

    // 🔓 Bypass availability completely on weekends for testing
    if (in_array($now->dayOfWeek, [0, 6], true)) {
        return true;
    }

    // If availability table doesn’t exist, allow walk-ins
    if (!Schema::hasTable('tbl_counselor_availabilities')) {
        return true;
    }

    $cid = $this->myCounselorId();

    // If counselor mapping not wired yet, allow for now
    if (!$cid) {
        return true;
    }

    $date    = $now->toDateString();
    $time    = $now->format('H:i:s');
    $weekday = (int) $now->dayOfWeek; // 0=Sun..6=Sat

    // If this counselor has no rows at all, don’t block yet
    $hasAnyRow = DB::table('tbl_counselor_availabilities')
        ->where('counselor_id', $cid)
        ->exists();

    if (!$hasAnyRow) {
        return true;
    }

    // Strict check during weekdays
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

    /**
     * AJAX: check student, auto-create account if needed.
     * Route: POST /counselor/walk-ins/check-student
     */
public function checkStudent(Request $request)
{
    $data = $request->validate([
        'student_name'    => ['nullable', 'string', 'max:255'],
        'email'           => ['nullable', 'email', 'max:255'],
        'course'          => ['nullable', 'string', 'max:255'],
        'year_level'      => ['nullable', 'string', 'max:50'],
        'contact_number'  => ['nullable', 'string', 'max:50'],
    ]);

    if (!Schema::hasTable('tbl_users')) {
        return response()->json(['ok' => true, 'student' => null]);
    }

    $email = trim((string)($data['email'] ?? ''));
    $name  = trim((string)($data['student_name'] ?? ''));

    // Require at least email OR name to search
    if ($email === '' && $name === '') {
        return response()->json([
            'ok' => false,
            'message' => 'Provide at least an email or student name to search.',
        ], 422);
    }

    $query = User::query()->whereRaw('LOWER(role) = ?', ['student']);


    if ($email !== '') {
        $query->where('email', $email);
    } else {
        $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
    }

    $student = $query->first();
    $created = false;

    if ($student && empty($student->sis)) {
        $student->sis = $this->generateNextSis();
        $student->save();
    }

    // If not found, only CREATE if we have enough info
    if (!$student && $email !== '') {
        $hasEnoughToCreate = ($name !== '' && !empty($data['course']) && !empty($data['year_level']));

        if (!$hasEnoughToCreate) {
            return response()->json([
                'ok' => false,
                'message' => 'No student account found for this email. Fill Student Name, Course, and Year Level to auto-create.',
            ], 422);
        }

        $student = new User();
        $student->sis            = $this->generateNextSis();
        $student->name           = $name;
        $student->email          = $email;
        $student->course         = $data['course'];
        $student->year_level     = $this->mapYearLevelForUser($data['year_level']);
        $student->contact_number = $data['contact_number'] ?? null;
        $student->role                 = 'student';
        $student->appointments_enabled = 0;
        $student->password             = Hash::make('12345678');
        $student->save();

        $created = true;
    }

    if (!$student) {
        return response()->json([
            'ok' => false,
            'message' => 'Student not found. Provide an email to auto-create an account.',
        ], 422);
    }

    return response()->json([
        'ok'      => true,
        'created' => $created,
        'student' => [
            'id'             => $student->id,
            'name'           => $student->name,
            'email'          => $student->email,
            'sis'            => $student->sis,
            'course'         => $this->normalizeCourse($student->course),
            'year_level'     => $this->mapYearLevelShort($student->year_level),
            'contact_number' => $student->contact_number,
        ],
    ]);
}


    /**
     * Store Walk-in + appointment + case note
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'string', 'email:filter', 'max:255'],
            'course'       => ['required', 'string', 'max:255'],
            'year_level'   => ['required', 'string', 'max:50'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'start_time'   => ['required'],
            'end_time'     => ['required'],
            'reason'       => ['nullable', 'string'],
            'case_note'    => ['array'],
        ]);

        $counselorId = $this->myCounselorId();

        // If you want strict availability, put the check here
        if (!$this->hasCurrentAvailability()) {
            return back()
                ->withErrors([
                    'availability' => 'You are currently outside your configured available hours. Walk-in sessions can only be recorded during an Available window.',
                ])
                ->withInput();
        }

        $date    = Carbon::now()->toDateString();
        $startAt = Carbon::parse($date.' '.$request->start_time);
        $endAt   = Carbon::parse($date.' '.$request->end_time);

        // =============================
        // 1) Find or create STUDENT
        // =============================
        $student = null;
        if (Schema::hasTable('tbl_users')) {
            $student = User::where('role', 'student')
                ->where('email', $request->email)
                ->first();

            // If student exists but has no SIS yet → assign one
            if ($student && (empty($student->sis))) {
                $student->sis = $this->generateNextSis();
                $student->save();
            }

            if (!$student) {
                $student = new User();

                // 🔹 auto-generate SIS
                $student->sis            = $this->generateNextSis();

                $student->name           = $request->student_name;
                $student->email          = $request->email;
                $student->course         = $request->course;
                $student->year_level     = $this->mapYearLevelForUser($request->year_level);
                $student->contact_number = $request->contact_number;
                $student->role                 = 'student';
                $student->appointments_enabled = 0;
                $student->password             = Hash::make('12345678'); // default 1–8
                $student->save();
            }
        }

        $studentId = $student?->id ?? null;

        // =============================
        // 2) Create APPOINTMENT
        // =============================
        $apptModel = new Appointment();
        $apptTable = $apptModel->getTable();

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
            $appointment->appointment_source = 'walk_in';
        }

        $appointment->created_at = now();
        $appointment->updated_at = now();
        $appointment->save();

        // =============================
        // 3) Create CASE NOTE
        // =============================
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
            $caseNote->note_date = $date;
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
        if (Schema::hasColumn($cnTable, 'note_source')) {
            $caseNote->note_source = 'Walk-in';
        }

        $caseNote->save();

        return redirect()
            ->route('counselor.appointments.show', $appointment->id)
            ->with('swal', [
                'icon'  => 'success',
                'title' => 'Walk-in saved',
                'text'  => 'Walk-in session has been recorded and a case note was created. If the student had no account, one was created with the default password 12345678.',
            ]);
    }

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
    /**
 * Normalize course to the short code used by the dropdown (BSIT, EDUC, etc.).
 */
private function normalizeCourse(?string $course): ?string
{
    if (!$course) return null;

    $c = trim($course);

    return match ($c) {
        'College of Information Technology', 'BSIT'      => 'BSIT',
        'College of Education',              'EDUC'      => 'EDUC',
        'College of Arts and Sciences',      'CAS'       => 'CAS',
        'College of Criminal Justice and Public Safety', 'CRIM' => 'CRIM',
        'College of Library Information Science',        'BLIS' => 'BLIS',
        'College of Midwifery',              'MIDWIFERY' => 'MIDWIFERY',
        'College of Hospitality Management', 'BSHM'      => 'BSHM',
        'College of Business',               'BSBA'      => 'BSBA',
        default => $c,
    };
}

/**
 * Map stored year level (1st Year, First Year, etc.) → short code used in dropdown (1st, 2nd, 3rd, 4th).
 */
private function mapYearLevelShort(?string $full): ?string
{
    if (!$full) return null;

    $f = mb_strtolower(trim($full));

    return match ($f) {
        '1st year', 'first year', '1st' => '1st',
        '2nd year', 'second year', '2nd' => '2nd',
        '3rd year', 'third year', '3rd' => '3rd',
        '4th year', 'fourth year', '4th' => '4th',
        default => $full,
    };
}
   /**
 * Generate the next SIS based on the current year.
 *
 * Format:
 *   YYYYNNNN
 * Example (year 2025):
 *   First student this year: 20250000
 *   Next:                    20250001, 20250002, ...
 */
private function generateNextSis(): ?string
{
    if (!Schema::hasTable('tbl_users') || !Schema::hasColumn('tbl_users', 'sis')) {
        return null;
    }

    $yearPrefix = (string) now()->year; // e.g. "2025"

    // 🔹 Get the highest SIS for this year, sorted NUMERICALLY
    $lastSis = DB::table('tbl_users')
        ->whereNotNull('sis')
        ->where('sis', '!=', '')
        ->where('sis', 'like', $yearPrefix.'%')
        ->orderByRaw('CAST(sis AS UNSIGNED) DESC')   // ✅ numeric, not string
        ->value('sis');

    // No SIS for this year yet → start at YYYY0000
    if (!$lastSis) {
        return $yearPrefix.'0000';
    }

    // Take everything after the year prefix
    $suffix = substr($lastSis, strlen($yearPrefix));   // e.g. "0007", "0011", "9"

    // If malformed, treat as 0
    if ($suffix === '' || !ctype_digit($suffix)) {
        $suffix = '0';
    }

    $next = (int) $suffix + 1;                         // e.g. 11 -> 12
    $nextStr = str_pad((string) $next, 4, '0', STR_PAD_LEFT); // "0012"

    return $yearPrefix.$nextStr;                       // "2025"."0012" = 20250012
}

}
