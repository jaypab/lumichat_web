<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\CourseAnalyticsRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    private const TABLE          = 'tbl_appointments';
    private const STUDENTS_TABLE = 'tbl_users';
    private const COUNS_TABLE    = 'tbl_counselors';

    public function __construct(
        protected CourseAnalyticsRepositoryInterface $analytics
    ) {}

    public function all(): Collection
    {
        // If you also want completed at bottom here, switch to the smart ordering helper.
        return $this->baseSelect()->orderByDesc('a.scheduled_at')->get();
    }

    public function paginateWithNames(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $q = $this->baseSelect();
        $this->applyCommonFilters($q, $filters);

        if (!empty($filters['q'])) {
            $term = '%' . trim((string)$filters['q']) . '%';
            $q->where(function ($w) use ($term) {
                $w->where('c.name', 'like', $term)
                  ->orWhere('u.name', 'like', $term);
            });
        }

        // ✅ completed last + period-aware date ordering
        $this->applySmartOrderingWithCompletedLast($q, (string)($filters['period'] ?? 'all'));

        return $q->paginate($perPage)->withQueryString();
    }

    public function findDetailedById(int $id): ?object
    {
        return $this->baseSelectForShow()
            ->where('a.id', $id)
            ->first();
    }

    public function findById(int $id, array $with = []): ?object
    {
        return DB::table(self::TABLE)->where('id', $id)->first();
    }

    public function create(array $data): object
    {
        $id = DB::table(self::TABLE)->insertGetId($data);
        return $this->findById($id);
    }

    public function update(int $id, array $data): bool
    {
        return (bool) DB::table(self::TABLE)->where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return (bool) DB::table(self::TABLE)->where('id', $id)->delete();
    }

    public function firstAppointmentYearForStudent(int $studentId): ?int
    {
        $y = DB::table(self::TABLE)
            ->where('student_id', $studentId)
            ->whereNotNull('scheduled_at')
            ->selectRaw('MIN(YEAR(scheduled_at)) as y')
            ->value('y');

        return $y ? (int)$y : null;
    }

    public function monthlyCountsForStudent(int $studentId, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end   = Carbon::create($year, 12, 31)->endOfDay();

        return DB::table(self::TABLE)
            ->where('student_id', $studentId)
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereNotNull('scheduled_at')
            ->selectRaw('MONTH(scheduled_at) AS m, COUNT(*) AS c')
            ->groupByRaw('MONTH(scheduled_at)')
            ->orderByRaw('m')
            ->pluck('c', 'm')
            ->toArray();
    }

    public function statsByStatus(array $filters = []): array
    {
        $q = DB::table(self::TABLE);
        $this->applyCommonFilters($q, $filters);

        return $q->selectRaw('status, COUNT(*) as total')
                 ->groupBy('status')
                 ->pluck('total', 'status')
                 ->toArray();
    }

    public function saveFinalReport(int $appointmentId, string $diagnosis, ?string $finalNote, int $finalizedBy): array
    {
        $ap = DB::table(self::TABLE)->where('id', $appointmentId)->first();
        if (!$ap) return ['ok' => false, 'reason' => 'not_found'];
        if ($ap->status !== 'completed') return ['ok' => false, 'reason' => 'not_completed'];

        DB::transaction(function () use ($appointmentId, $ap, $diagnosis, $finalNote, $finalizedBy) {
            DB::table(self::TABLE)->where('id', $appointmentId)->update([
                'final_note'   => $finalNote,
                'finalized_by' => $finalizedBy,
                'finalized_at' => now(),
                'updated_at'   => now(),
            ]);

            DB::table('tbl_diagnosis_reports')->insert([
                'student_id'       => $ap->student_id,
                'counselor_id'     => $ap->counselor_id,
                'diagnosis_result' => $diagnosis,
                'notes'            => $finalNote,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $this->analytics->refreshForStudent((int) $ap->student_id);
        });

        return ['ok' => true];
    }

public function updateStatusByAction(int $appointmentId, string $action): array
{
    $map = [
        'confirm'  => 'confirmed',
        'cancel'   => 'cancelled',
        'complete' => 'completed',
        'no_show'  => 'no_show',
        'reopen'   => 'pending',
    ];

    if (!isset($map[$action])) {
        return ['ok' => false, 'reason' => 'invalid_action'];
    }

    $to = $map[$action];

    return \DB::transaction(function () use ($appointmentId, $to) {
        $row = \DB::table('tbl_appointments')->lockForUpdate()->where('id', $appointmentId)->first();
        if (!$row) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        $from = (string) $row->status;

        // (Keep any business rules you already enforce here...)
        \DB::table('tbl_appointments')->where('id', $appointmentId)->update([
            'status'     => $to,
            'updated_at' => now(),
        ]);

        // Refresh minimal fields for notifications
        $fresh = \DB::table('tbl_appointments')->where('id', $appointmentId)->first([
            'id','student_id','counselor_id','scheduled_at','status'
        ]);

        return ['ok' => true, 'from' => $from, 'to' => $to, 'row' => $fresh];
    });
}


    /* ===================== Helpers ===================== */

    protected function baseSelect(): Builder
    {
        return DB::table(self::TABLE.' as a')
            ->leftJoin(self::COUNS_TABLE.' as c', 'c.id', '=', 'a.counselor_id')
            ->join(self::STUDENTS_TABLE.' as u', 'u.id', '=', 'a.student_id')
            ->select([
                'a.id','a.scheduled_at','a.created_at as booked_at','a.status',
                DB::raw('COALESCE(c.name,"—") as counselor_name'),
                'u.name as student_name',
            ]);
    }

    protected function baseSelectForShow(): Builder
    {
        return DB::table(self::TABLE.' as a')
            ->leftJoin(self::COUNS_TABLE.' as c', 'c.id', '=', 'a.counselor_id')
            ->join(self::STUDENTS_TABLE.' as u', 'u.id', '=', 'a.student_id')
            ->select([
                'a.*',
                'c.name  as counselor_name','c.email as counselor_email','c.phone as counselor_phone',
                'u.name  as student_name','u.email as student_email',
            ]);
    }

    protected function applyCommonFilters(Builder $q, array $filters): void
    {
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $q->where('a.status', $filters['status']);
        }

        $period = $filters['period'] ?? 'all';
        if ($period && $period !== 'all') {
            $now = Carbon::now();
            if ($period === 'upcoming') {
                $q->where('a.scheduled_at', '>=', $now);
            } elseif ($period === 'today') {
                $q->whereDate('a.scheduled_at', $now->toDateString());
            } elseif ($period === 'this_week') {
                $q->whereBetween('a.scheduled_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);
            } elseif ($period === 'this_month') {
                $q->whereBetween('a.scheduled_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
            } elseif ($period === 'past') {
                $q->where('a.scheduled_at', '<', $now);
            }
        }
    }

    /**
     * Completed at bottom + period-aware ordering:
     * - Always: completed last (status bucket).
     * - 'today'/'upcoming'/'this_week'/'this_month' → ASC inside bucket.
     * - 'past' → DESC inside bucket.
     * - 'all' → Future first (ASC), then Past (DESC), completed (DESC) at the bottom.
     */
    protected function applySmartOrderingWithCompletedLast(Builder $q, string $period): void
    {
        $now = now();

        // Bucket 1: non-completed (0) | Bucket 2: completed (1)
        $q->orderByRaw("CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END ASC");

        if ($period === 'past') {
            // Within each bucket: newest past first
            $q->orderBy('a.scheduled_at', 'desc');
            return;
        }

        if (in_array($period, ['today','upcoming','this_week','this_month'], true)) {
            // Within each bucket: soonest first
            $q->orderBy('a.scheduled_at', 'asc');
            return;
        }

        // 'all' or anything else:
        // Sub-bucket inside non-completed: future first (ASC), then past (DESC)
        // Completed will also be ordered by date DESC at the very end.
        $q->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN 0 ELSE 1 END", [$now]) // future then past
          ->orderByRaw("CASE WHEN a.scheduled_at >= ? THEN a.scheduled_at END ASC",  [$now]) // future asc
          ->orderByRaw("CASE WHEN a.scheduled_at <  ? THEN a.scheduled_at END DESC", [$now]) // past desc
          ->orderByRaw("CASE WHEN a.status = 'completed' THEN a.scheduled_at END DESC");      // completed desc
    }

    // ==== Availability helpers (unchanged) ====
    public function counselorIdsFreeAt(Carbon $scheduledAt): array
    {
        // Use 60-minute slot consistently
        $slotEnd = $scheduledAt->copy()->addMinutes(60);
        $dow     = (int) $scheduledAt->isoWeekday();
        $dateStr = $scheduledAt->toDateString();

        $cids = DB::table('tbl_counselors')->where('is_active', 1)->pluck('id')->all();
        if (empty($cids)) return [];

        $free = [];
        foreach ($cids as $cid) {
            // Resolve availability windows for this weekday (adjust if you also store date-specific rows)
            $ranges = DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $cid)
                ->where('weekday', $dow)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->get(['start_time','end_time','slot_type']);

            $fits = false;
            foreach ($ranges as $r) {
                if (!is_string($r->start_time) || !is_string($r->end_time) || $r->start_time==='' || $r->end_time==='') {
                    continue;
                }
                try {
                    $rangeStart = Carbon::parse("$dateStr {$r->start_time}");
                    $rangeEnd   = Carbon::parse("$dateStr {$r->end_time}");
                } catch (\Throwable $e) { continue; }

                $inside = $scheduledAt->gte($rangeStart) && $slotEnd->lte($rangeEnd);
                if (!$inside) continue;
                if (($r->slot_type ?? 'available') === 'blocked') { $fits = false; break; }
                $fits = true;
            }
            if (!$fits) continue;

            // Hard conflict at exact start (pending/confirmed/ongoing)
            $taken = DB::table(self::TABLE)
                ->where('counselor_id', $cid)
                ->where('scheduled_at', $scheduledAt)
                ->whereIn('status', ['pending','confirmed','ongoing'])
                ->exists();

            if (!$taken) $free[] = (int) $cid;
        }

        return $free;
    }

    public function counselorIsFreeAt(int $counselorId, Carbon $scheduledAt): bool
    {
        return in_array($counselorId, $this->counselorIdsFreeAt($scheduledAt), true);
    }
    public function assignCounselor(int $appointmentId, int $counselorId): array
    {
        return DB::transaction(function () use ($appointmentId, $counselorId) {

            // Lock row
            $ap = DB::table(self::TABLE)->where('id', $appointmentId)->lockForUpdate()->first();
            if (!$ap) return ['ok'=>false,'reason'=>'not_found'];

            if (!in_array($ap->status, ['pending','confirmed'], true))
                return ['ok'=>false,'reason'=>'invalid_status'];

            if (!empty($ap->counselor_id))
                return ['ok'=>false,'reason'=>'already_assigned'];

            if (!$ap->scheduled_at || Carbon::parse($ap->scheduled_at)->lte(now()))
                return ['ok'=>false,'reason'=>'in_past'];

            $start = Carbon::parse($ap->scheduled_at)->second(0);

            // Availability check (60-min, pooled windows already respected by helper)
            if (!$this->counselorIsFreeAt($counselorId, $start))
                return ['ok'=>false,'reason'=>'not_available'];

            // Compare-and-set (prevents races)
            $affected = DB::table(self::TABLE)
                ->where('id', $appointmentId)
                ->whereNull('counselor_id')
                ->update([
                    'counselor_id' => $counselorId,
                    'updated_at'   => now(),
                ]);

            if ($affected === 0) return ['ok'=>false,'reason'=>'race_taken'];

            return ['ok'=>true];
        });
    }
}
