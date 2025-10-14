<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB; 
use Symfony\Component\HttpFoundation\Response; // use Symfony response (matches Dompdf::download)

class CourseAnalyticsController extends Controller
{
    public function __construct(
        protected CourseAnalyticsRepositoryInterface $analytics
    ) {}

    /**
     * INDEX: list rows with year + search filters
     */
    public function index(Request $request): View
    {
        $yearKey    = (string) $request->query('year', 'all');
        $courseKey  = (string) $request->query('course', 'all');
        $freeTextQ  = trim((string) $request->query('q', ''));

        // If a specific course is chosen, reuse it as the search text (keeps repo unchanged)
        $effectiveQ = $courseKey !== 'all' ? $courseKey : $freeTextQ;

        $courses = $this->analytics->listCourses($yearKey, $effectiveQ);

        // 1) Map codes -> friendly names (adjust labels as you prefer)
        $COURSE_LABELS = [
            'BSIT'      => 'College of Information Technology',
            'EDUC'      => 'College of Education',
            'CAS'       => 'College of Arts and Sciences',
            'CRIM'      => 'College of Criminal Justice and Public Safety',
            'BLIS'      => 'College of Library Information Science',
            'MIDWIFERY' => 'College of Midwifery',
            'BSHM'      => 'College of Hospitality Management',
            'BSBA'      => 'College of Business',
        ];

        // 2) Build dropdown options from distinct student courses
        $rawCodes = DB::table('tbl_users')
            ->selectRaw('DISTINCT course')
            ->whereNotNull('course')
            ->where('course', '<>', '')
            ->when(DB::getSchemaBuilder()->hasColumn('tbl_users','role'), fn($q)=>$q->where('role','student'))
            ->orderBy('course')
            ->pluck('course');

        $courseOptions = $rawCodes->map(function ($code) use ($COURSE_LABELS) {
            $code = (string) $code;
            return [
                'code' => $code,
                'name' => $COURSE_LABELS[$code] ?? $code, // fallback: show code itself
            ];
        })->values();

        // also pass a quick lookup map for table rendering
        $courseNameMap = $courseOptions->pluck('name', 'code');

        return view('admin.course-analytics.index', [
            'courses'        => $courses,
            'yearKey'        => $yearKey,
            'q'              => $freeTextQ,
            'courseKey'      => $courseKey,
            'courseOptions'  => $courseOptions,
            'courseNameMap'  => $courseNameMap,
        ]);
    }

    /**
     * SHOW: one course/year with diagnosis breakdown
     */
    public function show(int $id): View
    {
        $course = $this->analytics->findCourseWithBreakdown($id);
        abort_unless($course, 404);

        $title = "{$course->course} • {$course->year_level}";

        // Pass id explicitly so Blade can build the export link
        return view('admin.course-analytics.show', [
            'course'   => $course,
            'title'    => $title,
            'courseId' => $id,
        ]);
    }

    /**
     * Export the INDEX list to PDF
     * Route name suggestion: admin.course-analytics.export.pdf
     */
public function exportIndexPdf(Request $request): Response
{
    $yearKey    = (string) $request->query('year', 'all');
    $courseKey  = (string) $request->query('course', 'all');
    $freeTextQ  = trim((string) $request->query('q', ''));

    $effectiveQ = $courseKey !== 'all' ? $courseKey : $freeTextQ;
    $courses    = $this->analytics->listCourses($yearKey, $effectiveQ);

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
        'isPhpEnabled'         => true, // if your Blade adds page numbers
    ]);

    $pdf->loadView('admin.course-analytics.index-pdf', [
        'courses'     => $courses,
        'yearKey'     => $yearKey,
        'q'           => $effectiveQ,
        'generatedAt' => now()->format('Y-m-d H:i'),
        'logoData'    => $logoData,
    ]);

    $filename = 'Course_Analytics_' . now()->format('Ymd_His') . '.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename);   // force download
    }
    return $pdf->stream($filename);         // inline view (opens in the new tab)
}

/**
 * Export the SHOW view to PDF
 * Route name: admin.course-analytics.show.export.pdf
 */
public function exportShowPdf(Request $request, int $course): Response
{
    $courseObj = $this->analytics->findCourseWithBreakdown($course);
    abort_unless($courseObj, 404);

    $title       = "{$courseObj->course} • {$courseObj->year_level}";
    $generatedAt = now()->format('Y-m-d H:i');

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
        'isPhpEnabled'         => true,
    ]);

    $pdf->loadView('admin.course-analytics.show_pdf', [
        'course'      => $courseObj,
        'title'       => $title,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,
    ]);

    $filename = 'Course_Analytics_' . now()->format('Ymd_His') . '.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename);
    }
    return $pdf->stream($filename); // inline in a new tab
}

/** Back-compat alias if you still call exportPdf for the index. */
public function exportPdf(Request $request): Response
{
    return $this->exportIndexPdf($request);
}
}
