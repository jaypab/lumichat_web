<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CounselorLogRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;


class CounselorLogController extends Controller
{
    public function __construct(
        protected CounselorLogRepositoryInterface $logs
    ) {}

    /** List view with filters (by counselor / month / year) */
    public function index(Request $request)
    {
        $month = (int) $request->integer('month') ?: (int) now()->format('n');
        $year  = (int) $request->integer('year')  ?: (int) now()->year;
        $cid   = (int) $request->integer('counselor_id') ?: null;
        $mode  = $request->query('mode', 'monthly'); // 'monthly' or 'ytd'

        $counselors = $this->logs->listCounselors();

        $rows = $this->logs->paginateLogs([
            'month'        => $month,
            'year'         => $year,
            'counselor_id' => $cid,
            'per_page'     => 12,
            'mode'         => $mode,
        ]);

        $years = $this->logs->availableYears();

        return view('admin.counselor-logs.index', compact('rows','counselors','years','month','year','cid','mode'));
    }

    /** Drilldown page: one counselor + selected month/year */
    public function show(Request $request, int $counselor)
    {
        $month = (int) $request->integer('month') ?: (int) now()->format('n');
        $year  = (int) $request->integer('year')  ?: (int) now()->year;

        $data = $this->logs->counselorMonthDetail($counselor, $month, $year);
        abort_unless($data['counselor'] ?? null, 404);

        return view('admin.counselor-logs.show', [
            'counselor' => $data['counselor'],
            'month'     => $month,
            'year'      => $year,
            'students'  => $data['students'],
            'dxCounts'  => $data['dxCounts'],
        ]);
    }

public function exportPdf(Request $request)
{
    $month = (int) $request->integer('month') ?: null;
    $year  = (int) $request->integer('year')  ?: null;
    $cid   = (int) $request->integer('counselor_id') ?: null;

    if (method_exists($this->logs, 'allLogs')) {
        $rows = $this->logs->allLogs([
            'month'        => $month,
            'year'         => $year,
            'counselor_id' => $cid,
        ]);
    } else {
        $p    = $this->logs->paginateLogs([
            'month'        => $month,
            'year'         => $year,
            'counselor_id' => $cid,
            'per_page'     => PHP_INT_MAX,
        ]);
        $rows = method_exists($p, 'items') ? collect($p->items()) : collect($p);
    }

    $counselors = $this->logs->listCounselors();
    $cName = $cid ? optional($counselors->firstWhere('id',$cid))->full_name : 'All';
    $mName = $month ? \Carbon\Carbon::create(null,$month,1)->format('F') : 'All';
    $yName = $year ?: 'All';
    $generatedAt = now()->format('Y-m-d H:i');

    // Base64 logo
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
        'isPhpEnabled'         => true, // if your Blade adds page numbers via <script type="text/php">
    ]);

    $pdf->loadView('admin.counselor-logs.pdf', [
        'rows'        => $rows,
        'cName'       => $cName,
        'mName'       => $mName,
        'yName'       => $yName,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,
    ]);

    $filename = 'Counselor_Logs_'.now()->format('Ymd_His').'.pdf';

    if ($request->boolean('download')) {
        return $pdf->download($filename);   // force a download
    }
    return $pdf->stream($filename);         // inline view (opens in the new tab)
}
// app/Http/Controllers/Admin/CounselorLogController.php

public function exportShowPdf(Request $request, int $counselor)
{
    $month = (int) $request->integer('month') ?: (int) now()->format('n');
    $year  = (int) $request->integer('year')  ?: (int) now()->year;

    $data = $this->logs->counselorMonthDetail($counselor, $month, $year);
    abort_unless($data['counselor'] ?? null, 404);

    $label       = \Carbon\Carbon::create($year, $month, 1)->format('F Y');
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
        'isPhpEnabled'         => true, // if your Blade uses <script type="text/php"> for page numbers
    ]);

    $pdf->loadView('admin.counselor-logs.pdf-show', [
        'counselor'   => $data['counselor'],
        'students'    => $data['students'],
        'dxCounts'    => $data['dxCounts'],
        'month'       => $month,
        'year'        => $year,
        'label'       => $label,
        'generatedAt' => $generatedAt,
        'logoData'    => $logoData,
    ]);

    $safeName = Str::slug($data['counselor']->full_name, '_');
    $filename = "Counselor_Log_{$safeName}_{$year}-{$month}_" . now()->format('Ymd_His') . ".pdf";

    if ($request->boolean('download')) {
        return $pdf->download($filename);   // force download
    }
    return $pdf->stream($filename);         // inline view (opens in the new tab)
}
}
