<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User; // kept for route-model binding type-hint
use App\Repositories\Contracts\StudentRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf; // <-- add this
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
        $year = $request->input('year'); // string|int

        $paginated = $this->students->paginateWithFilters([
            'q'    => $q,
            'year' => $year,
        ], self::PER_PAGE);

        $yearLevels = $this->students->distinctYearLevels();

        return view(self::VIEW_INDEX, [
            'students'   => $paginated,
            'q'          => $q,
            'year'       => $year,
            'yearLevels' => $yearLevels,
        ]);
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

    // Read logo from public/images/chatbot.png and encode as data URI
    $logoData = null;
    $logoPath = public_path('images/chatbot.png');   // C:\xampp\htdocs\...\public\images\chatbot.png
    if (is_file($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }

    $pdf = app('dompdf.wrapper');
    $pdf->setPaper('a4', 'portrait');
    $pdf->setOptions([
        'defaultFont'          => 'DejaVu Sans',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => true,
        // Optional: let DomPDF access /public just in case you later use file paths
        'chroot'               => public_path(),
        'dpi'                  => 96,
    ]);

    $pdf->loadView('admin.students.pdf', [
        'students'    => $students,
        'q'           => $q,
        'year'        => $year,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,   // <-- pass Base64 to Blade
    ]);

    return $pdf->download('Student_Records_' . now()->format('Ymd_His') . '.pdf');
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
    
    public function exportShowPdf(int $student, \Illuminate\Http\Request $request): Response
    {
        $year = (int) $request->query('year', now()->year);

        // Load the same data you use on the HTML show()
        $studentModel = \App\Models\User::query()
            ->where('role', 'student')->findOrFail($student);

        // If you compute $labels/$series/$total in show(), replicate here (no charts in PDF).
        [$labels, $series, $total] = $this->buildMonthlySeriesForStudent($studentModel->id, $year);

        // Optional logo (base64) so Dompdf doesn’t need HTTP
        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode(@file_get_contents($logoPath));
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'defaultFont'          => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'chroot'               => public_path(),
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

        return $pdf->download('Student_'.$studentModel->id.'_'.$year.'_'.now()->format('Ymd_His').'.pdf');
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
