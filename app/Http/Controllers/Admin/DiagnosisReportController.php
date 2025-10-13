<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DiagnosisReportRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiagnosisReportController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        protected DiagnosisReportRepositoryInterface $reportsRepo
    ) {}

    public function index(Request $request): View
    {
        $dateKey = (string) $request->input('date', 'all');
        $q       = trim((string) $request->input('q', ''));

        $reports = $this->reportsRepo->paginateWithFilters($dateKey, $q, self::PER_PAGE);

        return view('admin.diagnosis-reports.index', compact('reports', 'dateKey', 'q'));
    }

    public function show(int $id): View
    {
        $report = $this->reportsRepo->findWithRelations($id, ['student:id,name,email', 'counselor']);
        abort_unless($report, 404);

        return view('admin.diagnosis-reports.show', compact('report'));
    }

    /**
     * Export Diagnosis Reports to PDF (honors current filters, returns all rows).
     */
public function exportPdf(Request $request)
{
    $dateKey = (string) $request->input('date', 'all');
    $q       = trim((string) $request->input('q', ''));

    $reports = method_exists($this->reportsRepo, 'allWithFilters')
        ? $this->reportsRepo->allWithFilters($dateKey, $q)
        : $this->reportsRepo->paginateWithFilters($dateKey, $q, PHP_INT_MAX);

    $logoData = null;
    $logoPath = public_path('images/chatbot.png');
    if (is_file($logoPath)) $logoData = 'data:image/png;base64,'.base64_encode(@file_get_contents($logoPath));

    $pdf = app('dompdf.wrapper');
    $pdf->setPaper('a4', 'portrait');
    $pdf->setOptions([
        'defaultFont'          => 'dejavu sans',  // ✅ single, exact name
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => true,
        'chroot'               => base_path(),
    ]);

    $pdf->loadView('admin.diagnosis-reports.pdf', [
        'reports'     => $reports,
        'dateKey'     => $dateKey,
        'q'           => $q,
        'generatedAt' => now()->format('Y-m-d H:i'),
        'logoData'    => $logoData,
    ]);

    return $pdf->download('Diagnosis_Reports_'.now()->format('Ymd_His').'.pdf');
}

public function exportOne(int $reportId)
{
    $report = $this->reportsRepo->findWithRelations($reportId, ['student:id,name,email', 'counselor']);
    abort_unless($report, 404);

    $code        = 'DRP-'.now()->format('Y').'-'.str_pad($report->id, 4, '0', STR_PAD_LEFT);
    $generatedAt = now()->format('Y-m-d H:i');

    // inline logo (optional)
    $logoData = null;
    $logoPath = public_path('images/chatbot.png');
    if (is_file($logoPath)) {
        $logoData = 'data:image/png;base64,'.base64_encode(@file_get_contents($logoPath));
    }

    // ✅ embed DejaVu Sans (ship with dompdf)
    $dejavuTtf = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    $dejavuBtt = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    $dejavuData  = is_file($dejavuTtf) ? base64_encode(@file_get_contents($dejavuTtf)) : null;
    $dejavuBold  = is_file($dejavuBtt) ? base64_encode(@file_get_contents($dejavuBtt)) : null;

    $pdf = app('dompdf.wrapper');
    $pdf->setPaper('a4', 'portrait');
    $pdf->setOptions([
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => true,
        'chroot'               => public_path(), // safe root
    ]);

    $pdf->loadView('admin.diagnosis-reports.pdf-single', [
        'report'      => $report,
        'code'        => $code,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,
        // pass embedded font
        'dejavuData'  => $dejavuData,
        'dejavuBold'  => $dejavuBold,
    ]);

    return $pdf->download('Diagnosis-Report-'.$code.'.pdf');
}
}