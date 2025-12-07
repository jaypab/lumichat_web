<?php

namespace App\Http\Controllers\Admin;

use App\Models\Appointment;
use App\Models\CaseNote;
use App\Models\ChatSession;
use App\Http\Controllers\Controller;
use App\Models\User; 
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf; 
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class StudentController extends Controller
{
    // ==== Constants ====
    private const PER_PAGE    = 10;
    private const VIEW_INDEX  = 'admin.students.index';
    private const VIEW_SHOW   = 'admin.students.show';

    public function __construct(
        protected StudentRepositoryInterface $students,
        protected AppointmentRepositoryInterface $appointments
    ) {}

    /**
     * List students (from tbl_users) with optional text and year filters.
     */


    public function index(Request $request): View
    {
            $q    = trim((string) $request->input('q', ''));
            $year = $request->input('year');

            $query = User::query()
                ->select([
                    'id',
                    'sis',              // ✅ include SIS so Blade can use $s->sis
                    'name',
                    'email',
                    'course',
                    'year_level',
                    'contact_number',
                    'email_verified_at',
                    'created_at',
                ])
                ->where('role', 'student');

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('course', 'like', "%{$q}%")
                    ->orWhere('sis', 'like', "%{$q}%");   // 🔎 optional: allow searching by SIS
                });
            }

            if ($year !== null && $year !== '') {
                $query->where('year_level', $year);
            }

            $students = $query
                ->orderBy('created_at', 'desc')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $yearLevels = $this->students->distinctYearLevels();

            return view(self::VIEW_INDEX, [
                'students'   => $students,
                'q'          => $q,
                'year'       => $year,
                'yearLevels' => $yearLevels,
            ]);
    }

            public function create(): View
            {
                $nextSis = $this->generateNextSis();
                return view('admin.students.create', compact('nextSis'));
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
private function generateNextSis(): string
{
    $table      = (new User)->getTable();   // usually 'tbl_users'
    $yearPrefix = (string) now()->year;     // e.g. "2025"

    // Get the HIGHEST SIS for the current year, sorted NUMERICALLY
    $lastSis = \DB::table($table)
        ->whereNotNull('sis')
        ->where('sis', '!=', '')
        ->where('sis', 'like', $yearPrefix.'%')
        ->orderByRaw('CAST(sis AS UNSIGNED) DESC')   // 🔹 numeric sort, not string
        ->value('sis');

    // No SIS for this year yet → start at YYYY0000
    if (!$lastSis) {
        return $yearPrefix . '0000';
    }

    // Take everything AFTER the year prefix (the suffix)
    $suffix = substr($lastSis, strlen($yearPrefix)); // e.g. "0007", "0011", "9"

    // Failsafe: if somehow not numeric, treat as 0
    if ($suffix === '' || !ctype_digit($suffix)) {
        $suffix = '0';
    }

    // Compute next sequence
    $next      = (int) $suffix + 1;                 // e.g. 11 -> 12
    $nextPart  = str_pad((string) $next, 4, '0', STR_PAD_LEFT); // "0012"

    return $yearPrefix . $nextPart;                 // "2025" . "0012" = 20250012
}




    public function store(Request $request): RedirectResponse
{
    $table = (new User)->getTable(); // should be 'tbl_users'

    // Auto-generate SIS if the field was left blank (just in case)
    if (!$request->filled('sis')) {
        if ($autoSis = $this->generateNextSis()) {
            $request->merge(['sis' => $autoSis]);
        }
    }

    $validated = $request->validate([
        'sis' => [
            'required',
            'digits_between:4,20',
            'regex:/^[0-9]+$/',
            Rule::unique($table, 'sis'),
        ],
        'name' => [
            'required',
            'string',
            'max:120',
            'regex:/^[A-Za-z\s\.\-]+$/',
        ],
        'email' => [
            'required',
            'string',
            'email:filter',
            'max:255',
            Rule::unique($table, 'email'),
        ],
        'course' => [
            'required',
            Rule::in(['BSIT','EDUC','CAS','CRIM','BLIS','MIDWIFERY','BSHM','BSBA']),
        ],
        'year_level' => [
            'required',
            Rule::in(['1st Year','2nd Year','3rd Year','4th Year']),
        ],
        'contact_number' => [
            'required',
            'regex:/^[0-9]{10,13}$/',
        ],
    ], [
        'sis.regex'               => 'The SIS ID may only contain numbers.',
        'contact_number.regex'    => 'The contact number may only contain digits.',
        'name.regex'              => 'The full name may only contain letters and basic punctuation.',
        'course.required'         => 'The course field is required.',
        'year_level.required'     => 'The year level field is required.',
        'contact_number.required' => 'The contact number field is required.',
    ]);

    $user = new User();
    $user->sis                 = $validated['sis'];
    $user->name                = $validated['name'];
    $user->email               = $validated['email'];
    $user->course              = $validated['course'];
    $user->year_level          = $validated['year_level'];
    $user->contact_number      = $validated['contact_number'];
    $user->role                = 'student';
    $user->appointments_enabled = 0;
    $user->password            = Hash::make('12345678');
    $user->save();

    return redirect()
        ->route('admin.students.index')
        ->with('success', 'Student has been successfully added.');
}

public function show(Request $request, User $student): View
{
    $requestedYear = (int) ($request->query('year') ?: now()->year);
    $studentId     = (int) $student->id;

    // Earliest year from appointments for this user
    $firstYearFromData = $this->appointments->firstAppointmentYearForStudent($studentId);

    $minYear = (int) ($firstYearFromData ?: ($student->created_at?->year ?? now()->year));
    $maxYear = (int) now()->year;
    $floor   = min($minYear, $maxYear - 4);
    $yearsAvailable = range($maxYear, $floor, -1); // DESC
    $year = max(min($requestedYear, $maxYear), $floor);

    // Monthly counts for the selected year (chart)
    $monthCounts = $this->appointments->monthlyCountsForStudent($studentId, $year);
    [$labels, $series] = $this->buildMonthlySeries($year, $monthCounts);

    $total     = array_sum($series);
    $max       = $total ? max($series) : 0;
    $peakLabel = $max ? $labels[array_search($max, $series, true)] : null;

    // 🔹 List-style related records for this student

    // 1) All appointments for this student
    $appointments = Appointment::query()
        ->with([
            'counselor:id,name',   // adjust columns if your Counselor model uses other fields
        ])
        ->where('student_id', $studentId)
        ->orderByDesc('scheduled_at')
        ->get();

    // 2) All case notes for this student
    $caseNotes = CaseNote::query()
        ->with([
            'appointment:id,scheduled_at',   // so we can show Appt # + date
            'counselor:id,name',
        ])
        ->where('student_id', $studentId)
        ->orderByDesc('created_at')
        ->get();

    // 3) All chatbot sessions for this student
    $chatSessions = ChatSession::query()
        ->where('user_id', $studentId)
        ->orderByDesc('created_at')
        ->get();

    return view(self::VIEW_SHOW, [
        'student'        => $student,
        'year'           => $year,
        'yearsAvailable' => $yearsAvailable,
        'labels'         => $labels,
        'series'         => $series,
        'total'          => $total,
        'peakLabel'      => $peakLabel,

        // 🔹 lists for the UI
        'appointments'   => $appointments,
        'caseNotes'      => $caseNotes,
        'chatSessions'   => $chatSessions,
    ]);
}

    /**
     * Export the filtered Student list to PDF (all matching rows, no pagination).
     */
public function exportPdf(Request $request)
{
    $q    = trim((string) $request->input('q', ''));
    $year = $request->input('year');

    $students = method_exists($this->students, 'allWithFilters')
        ? $this->students->allWithFilters(['q' => $q, 'year' => $year])
        : $this->students->paginateWithFilters(['q' => $q, 'year' => $year], PHP_INT_MAX);

    $generatedAt = now()->format('Y-m-d H:i');

    $logoData = null;
    $logoPath = public_path('images/chatbot.png');
    if (is_file($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
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

    $pdf->loadView('admin.students.pdf', [
        'students'    => $students,
        'q'           => $q,
        'year'        => $year,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,
    ]);

    $filename = 'Student_Records_' . now()->format('Ymd_His') . '.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename); // force download
    }

    return $pdf->stream($filename); // inline view (opens in new tab from the Blade link)
}


    // ==== Private helpers ====

    /**
     * Build month labels (Jan–Dec) and a 12-length series using the plucked counts.
     *
     * @param  int $year
     * @param  \Illuminate\Support\Collection|array $monthCounts  [monthNumber => count]
     * @return array{0: array<int,string>, 1: array<int,int>}
     */
    private function buildMonthlySeries(int $year, $monthCounts): array
    {
        if ($monthCounts instanceof \Illuminate\Support\Collection) {
            $monthCounts = $monthCounts->all();
        }

        $labels = [];
        $series = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = Carbon::create($year, $m, 1)->format('M'); // Jan, Feb, ...
            $series[] = (int) ($monthCounts[$m] ?? 0);
        }

        return [$labels, $series];
    }
    
   public function exportShowPdf(int $student, Request $request): Response
{
    // Always resolve the student first
    $studentModel = User::query()
        ->where('role', 'student')
        ->findOrFail($student);

    // =========================================================
    //  A) APPOINTMENT PDF MODE  (when ?appointment=ID is present)
    // =========================================================
    if ($request->filled('appointment')) {

        $appointmentId = (int) $request->query('appointment');

        // --- Fetch the appointment row for this student ---
        $appointment = DB::table('tbl_appointments')
            ->where('id', $appointmentId)
            ->where('student_id', $studentModel->id)   // make sure it belongs to this student
            ->first();

        if (!$appointment) {
            abort(404, 'Appointment not found for this student.');
        }

        // Ensure optional counselor_* properties exist so Blade won’t throw notices
        foreach (['counselor_name', 'counselor_email', 'counselor_phone', 'counselor_dept'] as $col) {
            if (!property_exists($appointment, $col)) {
                $appointment->{$col} = null;
            }
        }

        // --- Get case note (latest for this appointment, if any) ---
        $caseNote = CaseNote::where('appointment_id', $appointment->id)
            ->latest('created_at')
            ->first();

        // --- Logo for header ---
        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        // --- DomPDF setup ---
        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
            'dpi'                  => 96,
            'isPhpEnabled'         => true, // for <script type="text/php">
        ]);

        // --- Load the appointment PDF view ---
        $pdf->loadView('admin.appointments.pdf-show', [
            'student'     => $studentModel,
            'appointment' => $appointment,
            'caseNote'    => $caseNote,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        $filename = 'Appointment_' . $appointment->id . '_' . now()->format('Ymd_His') . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename); // force download
        }

        return $pdf->stream($filename); // inline view
    }

   // =========================================================
//  B) STUDENT SUMMARY PDF MODE (old behavior)
// =========================================================

$year = (int) $request->query('year', now()->year);

// chart data
[$labels, $series, $total] = $this->buildMonthlySeriesForStudent($studentModel->id, $year);

// 🔹 fetch same related records as in show()
$studentId = (int) $studentModel->id;

// 1) appointments
$appointments = Appointment::query()
    ->with(['counselor:id,name'])
    ->where('student_id', $studentId)
    ->orderByDesc('scheduled_at')
    ->get();

// 2) case notes
$caseNotes = CaseNote::query()
    ->with([
        'appointment:id,scheduled_at',
        'counselor:id,name',
    ])
    ->where('student_id', $studentId)
    ->orderByDesc('created_at')
    ->get();

// 3) chatbot sessions
$chatSessions = ChatSession::query()
    ->where('user_id', $studentId)
    ->orderByDesc('created_at')
    ->get();

// Logo for header
$logoData = null;
$logoPath = public_path('images/chatbot.png');
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
    'isPhpEnabled'         => true, // for <script type="text/php">
]);

$pdf->loadView('admin.students.show_pdf', [
    'student'      => $studentModel,
    'year'         => $year,
    'labels'       => $labels,
    'series'       => $series,
    'total'        => $total,
    'generatedAt'  => now()->format('Y-m-d H:i'),
    'logoData'     => $logoData,
    'appointments' => $appointments,
    'caseNotes'    => $caseNotes,
    'chatSessions' => $chatSessions,
]);

$filename = 'Student_' . $studentModel->id . '_' . $year . '_' . now()->format('Ymd_His') . '.pdf';

if ($request->boolean('download')) {
    return $pdf->download($filename);
}

return $pdf->stream($filename);
}


    /**
     * Example helper so the PDF has the same numbers as the HTML page.
     * Return: [$labels, $series, $total]
     */
    protected function buildMonthlySeriesForStudent(int $studentId, int $year): array
    {
        // Replace with your real query that populates the chart in show()
        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $series = array_fill(0, 12, 0);

        // Example: count appointments per month
        $rows = \DB::table('tbl_appointments')
            ->selectRaw('MONTH(scheduled_at) as m, COUNT(*) as c')
            ->where('student_id', $studentId)
            ->whereYear('scheduled_at', $year)
            ->groupBy('m')
            ->get();

        foreach ($rows as $r) {
            $idx = max(0, min(11, ((int)$r->m) - 1));
            $series[$idx] = (int)$r->c;
        }

        return [$labels, $series, array_sum($series)];
    }
}
