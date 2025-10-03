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
        'defaultFont'          => 'DejaVu Sans',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled'      => true,
        'chroot'               => public_path(),
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
}