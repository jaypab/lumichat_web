<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CounselorRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CounselorController extends Controller
{
    // ==== Constants (dedupe + consistency) ====
    private const SLOT_MINUTES = 30;
    private const PER_PAGE      = 10;
    private const FLASH_SUCCESS = 'success';

    // ==== Views ====
    private const VIEW_INDEX  = 'admin.counselors.index';
    private const VIEW_CREATE = 'admin.counselors.create';
    private const VIEW_EDIT   = 'admin.counselors.edit';

    public function __construct(
        protected CounselorRepositoryInterface $counselors
    ) {}

    /**
     * List counselors with their availabilities (ordered).
     */
    public function index(): View
    {
        $counselors = $this->counselors->paginateWithFilters([
            'with_availabilities_ordered' => true,
        ], self::PER_PAGE);

        // ⬇️ attach booking stats for the counselors on this page
        $this->attachBookingStats($counselors);

        return view(self::VIEW_INDEX, compact('counselors'));
    }

    /**
     * Show create form.
     */
    public function create(): View
    {
        return view(self::VIEW_CREATE);
    }

    /**
     * Store a new counselor and their availability slots.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rulesStore());

        $this->counselors->create($data);

        return redirect()
            ->route('admin.counselors.index')
            ->with(self::FLASH_SUCCESS, 'Counselor added.');
    }

    /**
     * Show edit form.
     */
    public function edit(int $id): View
    {
        $counselor = $this->counselors->findById($id, ['availabilities']);
        abort_if(!$counselor, 404);

        return view(self::VIEW_EDIT, compact('counselor'));
    }

    /**
     * Update a counselor and replace availability slots (simple replace).
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate($this->rulesUpdate($id));

        $this->counselors->update($id, $data);

        return redirect()
            ->route('admin.counselors.index')
            ->with(self::FLASH_SUCCESS, 'Counselor added successfully!');
    }

    /**
     * Remove a counselor.
     */
    public function destroy(int $id): RedirectResponse
    {
        $this->counselors->delete($id);

        return back()->with(self::FLASH_SUCCESS, 'Counselor removed.');
    }

    // ==== Private helpers (kept from your original) ====

    /**
     * Validation rules for creating a counselor.
     */
    private function rulesStore(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255', 'unique:tbl_counselors,email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'is_active'  => ['required', 'boolean'],

            // availability[]: [{ weekday, start_time, end_time }]
            'availability'              => ['array'],
            'availability.*.weekday'    => ['required', 'integer', 'between:0,6'],
            'availability.*.start_time' => ['required', 'date_format:H:i'],
            'availability.*.end_time'   => ['required', 'date_format:H:i', 'after:availability.*.start_time'],
        ];
    }

    /**
     * Validation rules for updating a counselor.
     */
    private function rulesUpdate(int $counselorId): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'email'      => [
                'required',
                'email',
                'max:255',
                Rule::unique('tbl_counselors', 'email')->ignore($counselorId),
            ],
            'phone'      => ['nullable', 'string', 'max:30'],
            'is_active'  => ['required', 'boolean'],

            'availability'              => ['array'],
            'availability.*.weekday'    => ['required', 'integer', 'between:0,6'],
            'availability.*.start_time' => ['required', 'date_format:H:i'],
            'availability.*.end_time'   => ['required', 'date_format:H:i', 'after:availability.*.start_time'],
        ];
    }
    /**
     * Adds booking aggregates to each counselor in the current page:
     *  - today_count, upcoming_count
     *  - next_at (Carbon|null)
     *  - is_busy_now (bool), busy_until (Carbon|null)
     */
    private function attachBookingStats($paginatorOrCollection): void
    {
        $items = method_exists($paginatorOrCollection, 'getCollection')
            ? $paginatorOrCollection->getCollection()
            : $paginatorOrCollection;

        if ($items->isEmpty()) return;

        $ids = $items->pluck('id')->all();
        $now = now();

        // Aggregates (today/upcoming counts, busy_until) — your existing block is fine
        $rows = DB::table('tbl_appointments as a')
            ->whereIn('a.counselor_id', $ids)
            ->whereIn('a.status', ['pending','confirmed'])
            ->groupBy('a.counselor_id')
            ->select([
                'a.counselor_id',
                DB::raw("SUM(CASE WHEN DATE(a.scheduled_at) = ".$now->toDateString()." THEN 1 ELSE 0 END) as today_count"),
                DB::raw("SUM(CASE WHEN a.scheduled_at >= '".$now."' THEN 1 ELSE 0 END) as upcoming_count"),
                DB::raw("MIN(CASE WHEN a.scheduled_at >= '".$now."' THEN a.scheduled_at ELSE NULL END) as next_at"),
                DB::raw("
                    MAX(
                        CASE
                            WHEN a.scheduled_at <= '".$now."'
                            AND DATE_ADD(a.scheduled_at, INTERVAL ".self::SLOT_MINUTES." MINUTE) > '".$now."'
                            THEN DATE_ADD(a.scheduled_at, INTERVAL ".self::SLOT_MINUTES." MINUTE)
                            ELSE NULL
                        END
                    ) as busy_until
                "),
            ])
            ->get()
            ->keyBy('counselor_id');

        // Find the actual "next" appointment row (id + student) per counselor
        $next = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as s', 's.id', '=', 'a.student_id') // adjust if your students table differs
            ->whereIn('a.counselor_id', $ids)
            ->whereIn('a.status', ['pending','confirmed'])
            ->where('a.scheduled_at', '>=', $now)
            ->orderBy('a.scheduled_at')
            ->get([
                'a.counselor_id',
                'a.id as next_appt_id',
                'a.student_id as next_student_id',
                's.name as next_student_name',
                'a.scheduled_at as next_at',
            ])
            ->groupBy('counselor_id')
            ->map(fn($g) => $g->first()); // first = earliest upcoming

        // Attach to each counselor item
        $items->transform(function ($c) use ($rows, $next) {
            $r = $rows[$c->id] ?? null;
            $n = $next[$c->id] ?? null;

            $c->today_count       = (int)($r->today_count ?? 0);
            $c->upcoming_count    = (int)($r->upcoming_count ?? 0);
            $c->busy_until_c      = !empty($r->busy_until) ? Carbon::parse($r->busy_until) : null;
            $c->is_busy_now       = (bool) $c->busy_until_c;

            // next appointment metadata
            $c->next_at_c         = !empty($n?->next_at) ? Carbon::parse($n->next_at) : (!empty($r?->next_at) ? Carbon::parse($r->next_at) : null);
            $c->next_appt_id      = $n->next_appt_id ?? null;
            $c->next_student_id   = $n->next_student_id ?? null;
            $c->next_student_name = $n->next_student_name ?? null;

            return $c;
        });
    }
}
