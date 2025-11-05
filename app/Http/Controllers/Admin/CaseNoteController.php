<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CaseNoteController extends Controller
{
    /**
     * GET /admin/case-notes
     */
    public function index(Request $request)
    {
        $dateKey = (string) $request->input('date', 'all');  // all|today|last7|last30|month|range
        $q       = trim((string) $request->input('q', ''));
        $from    = $request->input('from');  // optional Y-m-d for range
        $to      = $request->input('to');    // optional Y-m-d for range

        // Use the query builder with table alias so we control the deleted_at filter.
        $query = DB::table('tbl_case_notes as n')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'n.counselor_id')
            ->leftJoin('tbl_students as s', 's.id', '=', 'n.student_id')
            ->select([
                'n.id',
                'n.student_name',
                DB::raw("COALESCE(c.name, CONCAT('Counselor #', n.counselor_id)) as counselor_name"),
                'n.note_date',
                'n.presenting_problem',
                'n.observations',
                'n.interventions',
                'n.response',
                'n.plan_followup',
                'n.created_at',
                'n.updated_at',
            ])
            ->whereNull('n.deleted_at'); // manual soft-delete filter since we use alias

        // Date filter
        $today = now()->toDateString();
        switch ($dateKey) {
            case 'today':
                $query->whereDate('n.note_date', $today);
                break;
            case 'last7':
            case '7d':
                $query->whereBetween('n.note_date', [now()->subDays(7)->toDateString(), $today]);
                break;
            case 'last30':
            case '30d':
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
                // all
                break;
        }

        // Search (student, counselor, and text content)
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

        // Order: newest note_date first, then id desc
        $query->orderByRaw('COALESCE(n.note_date, DATE(n.created_at)) DESC')
              ->orderBy('n.id', 'desc');

        $notes = $query->paginate(10)->withQueryString();

        // Reuse your existing index Blade (you already have it as case-notes.index)
        return view('admin.case-notes.index', [
            'notes'   => $notes,
            'dateKey' => $dateKey,
            'q'       => $q,
        ]);
    }

    /**
     * GET /admin/case-notes/{id}
     */
    public function show(int $id)
    {
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
}
