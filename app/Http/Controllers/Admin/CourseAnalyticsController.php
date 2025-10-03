<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;
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
        $yearKey = (string) $request->query('year', 'all');
        $q       = trim((string) $request->query('q', ''));

        $courses = $this->analytics->listCourses($yearKey, $q);

        return view('admin.course-analytics.index', [
            'courses' => $courses,
            'yearKey' => $yearKey,
            'q'       => $q,
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
        $yearKey = (string) $request->query('year', 'all');
        $q       = trim((string) $request->query('q', ''));
        $courses = $this->analytics->listCourses($yearKey, $q);

        // Optional logo embed (base64) so dompdf sees it without external HTTP
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
        ]);

        $pdf->loadView('admin.course-analytics.index-pdf', [
            'courses'     => $courses,
            'yearKey'     => $yearKey,
            'q'           => $q,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        return $pdf->download('Course_Analytics_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Export the SHOW view to PDF
     * Route name suggestion: admin.course-analytics.show.export.pdf
     */
    public function exportShowPdf(int $course): Response
    {
        $courseObj = $this->analytics->findCourseWithBreakdown($course);
        abort_unless($courseObj, 404);

        $title       = "{$courseObj->course} • {$courseObj->year_level}";
        $generatedAt = now()->format('Y-m-d H:i');

        // Optional logo embed (base64)
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
        ]);

        // View file: resources/views/admin/course-analytics/show_pdf.blade.php
        $pdf->loadView('admin.course-analytics.show_pdf', [
            'course'      => $courseObj,
            'title'       => $title,
            'generatedAt' => $generatedAt,
            'logoData'    => $logoData,
        ]);

        return $pdf->download('Course_Analytics_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Back-compat alias if you still call exportPdf for the index.
     */
    public function exportPdf(Request $request): Response
    {
        return $this->exportIndexPdf($request);
    }
}
