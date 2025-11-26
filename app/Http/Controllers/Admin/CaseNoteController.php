<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CaseNoteController extends Controller
{
    /**
     * Simple helper: check if admin re-auth window is still valid.
     * Reuses the same session key used by ChatbotSessionController.
     */
    private function reauthOkay(): bool
    {
        $until = session('admin.reauth_until');
        if (!$until) {
            return false;
        }

        try {
            return now()->lt(\Carbon\Carbon::parse($until));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * GET /admin/case-notes
     */
    public function index(Request $request)
    {
        $dateKey = (string) $request->input('date', 'all');  // all|today|last7|last30|month|range
        $q       = trim((string) $request->input('q', ''));
        $from    = $request->input('from'); // Y-m-d (optional for range)
        $to      = $request->input('to');   // Y-m-d (optional for range)

        $query = $this->baseQuery();

        $this->applyFilters($query, $request);

        // Order: newest note_date (or created_at) first, then id desc
        $query->orderByRaw('COALESCE(n.note_date, DATE(n.created_at)) DESC')
              ->orderBy('n.id', 'desc');

        $notes = $query->paginate(10)->withQueryString();

        return view('admin.case-notes.index', [
            'notes'   => $notes,
            'dateKey' => $dateKey,
            'q'       => $q,
        ]);
        $source = $request->input('source'); // 'walk-in' or null

        $notes = CaseNote::query()
        // your existing date & search filters here…
        ->when($source === 'walk-in', fn ($q) => $q->where('note_source', 'Walk-in'))
        ->latest('note_date')
        ->paginate(10);

    }

    /**
     * GET /admin/case-notes/{id}
     */
    public function show(int $id)
    {
        // 🔒 Require second verification (same behavior as chatbot sessions)
        if (!$this->reauthOkay()) {
            return view('admin.case-notes.show_gate', [
                'noteId' => $id,
            ]);
        }

        $note = DB::table('tbl_case_notes as n')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'n.counselor_id')
            ->leftJoin('tbl_students   as s', 's.id', '=', 'n.student_id')
            ->leftJoin('tbl_appointments as a', 'a.id', '=', 'n.appointment_id')
            ->select([
                'n.*',
                DB::raw("COALESCE(c.name, CONCAT('Counselor #', n.counselor_id)) as counselor_name"),
                DB::raw("COALESCE(s.name, n.student_name) as student_display_name"),
                'a.scheduled_at',
                'a.status as appt_status',
            ])
            ->where('n.id', $id)
            ->whereNull('n.deleted_at')
            ->first();

        abort_unless($note, 404);

        return view('admin.case-notes.show', compact('note'));
    }

    /**
     * GET /admin/case-notes/export/pdf  (list PDF)
     */
    public function exportPdf(Request $request)
    {
        // Build same query as index but ->get()
        $rows = $this->baseQuery();
        $this->applyFilters($rows, $request);

        $rows = $rows
            ->orderByRaw('COALESCE(n.note_date, DATE(n.created_at)) DESC')
            ->orderBy('n.id', 'desc')
            ->get();

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait')->setOptions([
            'defaultFont'           => 'DejaVu Sans',
            'isHtml5ParserEnabled'  => true,
            'isRemoteEnabled'       => true,
        ]);

        $pdf->loadView('admin.case-notes.pdf-list', [
            'title'     => 'Case Form Summary',
            'rows'      => $rows,
            'filters'   => $request->only('date', 'q', 'from', 'to'),
            'generated' => now()->format('F d, Y · g:i A'),
            'logoData'  => $this->pdfLogo(),     // ensure logo shows
        ]);

        return $pdf->stream('Case_Form_Summary.pdf');
    }

    /**
     * GET /admin/case-notes/{id}/export/pdf  (single PDF)
     */
    public function exportOne(int $id)
    {
        // Optional: also enforce re-auth for direct PDF access
        if (!$this->reauthOkay()) {
            // Just block with 403 so they can't bypass the gate via direct link
            abort(403, 'Second verification required to export this case note.');
        }

        $note = DB::table('tbl_case_notes as n')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'n.counselor_id')
            ->leftJoin('tbl_students   as s', 's.id', '=', 'n.student_id')
            ->leftJoin('tbl_appointments as a', 'a.id', '=', 'n.appointment_id')
            ->select([
                'n.*',
                DB::raw("COALESCE(c.name, CONCAT('Counselor #', n.counselor_id)) as counselor_name"),
                DB::raw("COALESCE(s.name, n.student_name) as student_display_name"),
                'a.scheduled_at',
                'a.status as appt_status',
            ])
            ->where('n.id', $id)
            ->whereNull('n.deleted_at')
            ->first();

        abort_unless($note, 404);

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait')->setOptions([
            'defaultFont'           => 'DejaVu Sans',
            'isHtml5ParserEnabled'  => true,
            'isRemoteEnabled'       => true,
        ]);

        $pdf->loadView('admin.case-notes.pdf-one', [
            'note'      => $note,
            'generated' => now()->format('F d, Y · g:i A'),
            'logoData'  => $this->pdfLogo(),     // ensure logo shows
        ]);

        $code = 'CN-'.(now()->format('Y')).'-'.str_pad($note->id, 4, '0', STR_PAD_LEFT);
        return $pdf->stream($code.'.pdf');
    }

    /* =========================================================
     | Helpers
     * =======================================================*/

    /**
     * Base SELECT for case-notes lists.
     */
    private function baseQuery()
    {
        return DB::table('tbl_case_notes as n')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'n.counselor_id')
            ->leftJoin('tbl_students   as s', 's.id', '=', 'n.student_id')
            ->select([
                'n.id',
                'n.student_name',
                DB::raw("COALESCE(c.name, CONCAT('Counselor #', n.counselor_id)) as counselor_name"),
                DB::raw("COALESCE(s.name, n.student_name) as student_name_display"),
                'n.note_date',
                'n.presenting_problem',
                 'n.note_source', 
                'n.observations',
                'n.interventions',
                'n.response',
                'n.plan_followup',
                'n.created_at',
                'n.updated_at',
            ])
            ->whereNull('n.deleted_at');
    }

    /**
     * Apply date & search filters consistently across index and exports.
     */
    private function applyFilters($query, Request $request): void
    {
        $dateKey = (string) $request->input('date', 'all');
        $q       = trim((string) $request->input('q', ''));
        $from    = $request->input('from'); // Y-m-d
        $to      = $request->input('to');   // Y-m-d

        $today = now()->toDateString();

        switch ($dateKey) {
            case 'today':
                $query->whereDate('n.note_date', $today);
                break;
            case 'last7':
                $query->whereBetween('n.note_date', [now()->subDays(7)->toDateString(), $today]);
                break;
            case 'last30':
                $query->whereBetween('n.note_date', [now()->subDays(30)->toDateString(), $today]);
                break;
            case 'month':
                $query->whereBetween('n.note_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ]);
                break;
            case 'range':
                if ($from && $to) {
                    $query->whereBetween('n.note_date', [$from, $to]);
                }
                break;
            default:
                // 'all' -> no date filter
                break;
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('n.student_name', 'like', "%{$q}%")
                  ->orWhere('s.name', 'like', "%{$q}%")
                  ->orWhere('c.name', 'like', "%{$q}%")
                  ->orWhere('n.presenting_problem', 'like', "%{$q}%")
                  ->orWhere('n.observations', 'like', "%{$q}%")
                  ->orWhere('n.interventions', 'like', "%{$q}%")
                  ->orWhere('n.response', 'like', "%{$q}%")
                  ->orWhere('n.plan_followup', 'like', "%{$q}%");
            });
        }
    }

    /**
     * Inline base64 logo for Dompdf (keeps it 100% reliable).
     * Put your logo at public/images/lumichat-logo.png
     */
    private function pdfLogo(): ?string
    {
        // point to: public/images/chatbot.png
        $path = public_path('images/chatbot.png');   // ← update this

        if (!is_file($path)) {
            return null;
        }

        $mime = 'image/png'; // keep png
        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
