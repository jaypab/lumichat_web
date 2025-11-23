<?php

namespace App\Http\Controllers\Admin;

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

      /**
     * Show form to create a new student account.
     */
    public function create(): View
    {
        return view('admin.students.create');
    }

    /**
     * Store new student in tbl_users.
     */
    public function store(Request $request): RedirectResponse
    {
        $table = (new User)->getTable(); // should be 'tbl_users'

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
        $user->sis            = $validated['sis'];
        $user->name           = $validated['name'];
        $user->email          = $validated['email'];
        $user->course         = $validated['course'];
        $user->year_level     = $validated['year_level'];
        $user->contact_number = $validated['contact_number'];
        $user->role                 = 'student';
        $user->appointments_enabled = 0;
        $user->password             = Hash::make('12345678');
        $user->save();

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Student has been successfully added.');
    }

    /**
     * Show a student's appointment stats and chart for a selected year.
     * NOTE: We still type-hint App\Models\User for route-model binding.
     */
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

        // Monthly counts for the selected year
        $monthCounts = $this->appointments->monthlyCountsForStudent($studentId, $year);

        [$labels, $series] = $this->buildMonthlySeries($year, $monthCounts);

        $total     = array_sum($series);
        $max       = $total ? max($series) : 0;
        $peakLabel = $max ? $labels[array_search($max, $series, true)] : null;

        return view(self::VIEW_SHOW, compact(
            'student',
            'year',
            'yearsAvailable',
            'labels',
            'series',
            'total',
            'peakLabel'
        ));
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
    $year = (int) $request->query('year', now()->year);

    $studentModel = \App\Models\User::query()
        ->where('role', 'student')->findOrFail($student);

    [$labels, $series, $total] = $this->buildMonthlySeriesForStudent($studentModel->id, $year);

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
        'isPhpEnabled'         => true, // needed if your Blade uses <script type="text/php"> (page numbers)
    ]);

    $pdf->loadView('admin.students.show_pdf', [
        'student'     => $studentModel,
        'year'        => $year,
        'labels'      => $labels,
        'series'      => $series,
        'total'       => $total,
        'generatedAt' => now()->format('Y-m-d H:i'),
        'logoData'    => $logoData,
    ]);

    $filename = 'Student_' . $studentModel->id . '_' . $year . '_' . now()->format('Ymd_His') . '.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename); // force download
    }
    return $pdf->stream($filename); // inline view (opens in the new tab)
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
