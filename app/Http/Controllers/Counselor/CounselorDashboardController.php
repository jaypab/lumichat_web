<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CounselorDashboardController extends Controller
{
    public function index()
    {
        $cid      = $this->counselorId();
        $now      = Carbon::now();
        $todayYmd = $now->toDateString();

        // Active statuses we treat as “booked/working” for the counselor
        $activeStatuses = ['pending', 'confirmed', 'ongoing'];

        /*
        |--------------------------------------------------------------------------
        | KPI: Today’s Appointments (active only) + WoW delta
        |--------------------------------------------------------------------------
        */
        $todaysCount = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->whereDate('scheduled_at', $todayYmd)
            ->count();

        $todayLastWeek = Carbon::parse($todayYmd)->subWeek()->toDateString();
        $todaysPrev    = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->whereDate('scheduled_at', $todayLastWeek)
            ->whereIn('status', $activeStatuses)
            ->count();

        $todaysDelta = $todaysPrev > 0
            ? (int) round((($todaysCount - $todaysPrev) / $todaysPrev) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KPI: Pending (all dates) + 7-day window delta
        |--------------------------------------------------------------------------
        */
        $pendingCount = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('status', 'pending')
            ->count();

        $winEnd   = Carbon::now();
        $winStart = $winEnd->copy()->subDays(7);
        $prevEnd  = $winStart->copy();
        $prevStart= $prevEnd->copy()->subDays(7);

        $pendingCurr = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$winStart, $winEnd])
            ->count();

        $pendingPrev = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('status', 'pending')
            ->whereBetween('scheduled_at', [$prevStart, $prevEnd])
            ->count();

        $pendingDelta = $pendingPrev > 0
            ? (int) round((($pendingCurr - $pendingPrev) / $pendingPrev) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KPI: Queue (all future active) + 7-day window delta
        |--------------------------------------------------------------------------
        | This shows how many active appointments are sitting in the future queue.
        */
        $queueCount = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->where('scheduled_at', '>=', $now)
            ->whereIn('status', $activeStatuses)
            ->count();

        $queueCurr = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->whereBetween('scheduled_at', [$winStart, $winEnd])
            ->whereIn('status', $activeStatuses)
            ->count();

        $queuePrev = DB::table('tbl_appointments')
            ->where('counselor_id', $cid)
            ->whereBetween('scheduled_at', [$prevStart, $prevEnd])
            ->whereIn('status', $activeStatuses)
            ->count();

        $queueDelta = $queuePrev > 0
            ? (int) round((($queueCurr - $queuePrev) / $queuePrev) * 100)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | KPI: Open Hours (This Week) – hour-granular
        |--------------------------------------------------------------------------
        */
        $openHours = $this->openHoursThisWeek($cid, $now);

        /*
        |--------------------------------------------------------------------------
        | Upcoming Appointments (next 5, active only)
        |--------------------------------------------------------------------------
        */
        $upcoming = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_users as u', 'u.id', '=', 'a.student_id')
            ->where('a.counselor_id', $cid)
            ->where('a.scheduled_at', '>=', $now)
            ->whereIn('a.status', $activeStatuses)
            ->orderBy('a.scheduled_at')
            ->limit(5)
            ->get([
                'a.id',
                'a.scheduled_at',
                'a.status',
                DB::raw('COALESCE(u.name, "Student") as student_name'),
            ])
            ->map(fn($r) => [
                'id'      => $r->id,
                'when'    => $r->scheduled_at,   // string datetime
                'student' => $r->student_name,
                'status'  => $r->status,
            ])
            ->toArray();

        /*
        |--------------------------------------------------------------------------
        | Render
        |--------------------------------------------------------------------------
        | Blade used: resources/views/Counselor_Interface/dashboard.blade.php
        | (your current file)
        */
        return view('Counselor_Interface.dashboard', [
            // KPI – appointments
            'todaysCount'  => $todaysCount,
            'todaysDelta'  => $todaysDelta,
            'pendingCount' => $pendingCount,
            'pendingDelta' => $pendingDelta,
            'queueCount'   => $queueCount,
            'queueDelta'   => $queueDelta,

            // KPI – availability
            'openHours'    => $openHours,

            // Lists
            'upcoming'     => $upcoming,
        ]);
    }

    /**
     * Compute hour-granular open hours for the current ISO week (Mon–Fri).
     * Rules:
     *  - Start from recurring AVAILABLE (date IS NULL, weekday=1..5).
     *  - If a date has explicit AVAILABLE, use those instead of recurring.
     *  - Subtract any BLOCKED (recurring or dated).
     *  - Subtract each booked hour (pending|confirmed|ongoing).
     *  - Ignore weekends.
     */
    private function openHoursThisWeek(int $counselorId, Carbon $ref): int
    {
        $monday = (clone $ref)->startOfWeek(Carbon::MONDAY)->startOfDay();
        $friday = (clone $monday)->copy()->addDays(4)->endOfDay();

        // Recurring rows (date IS NULL)
        $recAvail = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->where('slot_type', 'available')
            ->get(['weekday', 'start_time', 'end_time']);

        $recBlocks = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->whereNull('date')
            ->where('slot_type', 'blocked')
            ->get(['weekday', 'start_time', 'end_time']);

        $recAvailByWd  = [];
        $recBlocksByWd = [];
        foreach ($recAvail as $r)  { $recAvailByWd[(int)$r->weekday][]  = [$r->start_time,  $r->end_time]; }
        foreach ($recBlocks as $r) { $recBlocksByWd[(int)$r->weekday][] = [$r->start_time, $r->end_time]; }

        $totalOpen = 0;

        foreach (CarbonPeriod::create($monday, '1 day', $friday) as $day) {
            /** @var Carbon $day */
            $wd = (int) $day->isoWeekday();  // 1..7
            if ($wd >= 6) continue;          // skip Sat/Sun

            $ymd = $day->toDateString();

            // Dated rows
            $datedAvail = DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $counselorId)
                ->whereDate('date', $ymd)
                ->where('slot_type', 'available')
                ->orderBy('start_time')
                ->get(['start_time', 'end_time']);

            $datedBlocks = DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $counselorId)
                ->whereDate('date', $ymd)
                ->where('slot_type', 'blocked')
                ->orderBy('start_time')
                ->get(['start_time', 'end_time']);

            // Effective available windows
            $availWindows = $datedAvail->count() > 0
                ? $datedAvail->map(fn($r) => [$r->start_time, $r->end_time])->all()
                : ($recAvailByWd[$wd] ?? []);

            // Effective block windows = dated + recurring
            $blocks = array_merge(
                $datedBlocks->map(fn($r) => [$r->start_time, $r->end_time])->all(),
                $recBlocksByWd[$wd] ?? []
            );

            // Build open hour map
            $openHourSlots = [];
            foreach ($availWindows as [$s, $e]) {
                foreach ($this->enumerateHours("$ymd $s", "$ymd $e") as $slot) {
                    $openHourSlots[$slot] = true; // de-dup overlaps
                }
            }

            // Remove blocked hours
            foreach ($blocks as [$s, $e]) {
                foreach ($this->enumerateHours("$ymd $s", "$ymd $e") as $slot) {
                    unset($openHourSlots[$slot]);
                }
            }

            // Subtract booked hours (active)
            if (!empty($openHourSlots)) {
                $bookedHours = DB::table('tbl_appointments')
                    ->where('counselor_id', $counselorId)
                    ->whereDate('scheduled_at', $ymd)
                    ->whereIn('status', ['pending', 'confirmed', 'ongoing'])
                    ->pluck(DB::raw('TIME_FORMAT(scheduled_at, "%H:00:00")'))
                    ->all();

                foreach ($bookedHours as $h) {
                    $key = "$ymd $h";
                    if (isset($openHourSlots[$key])) unset($openHourSlots[$key]);
                }
            }

            $totalOpen += count($openHourSlots);
        }

        return $totalOpen;
    }

    /**
     * Enumerate on-the-hour slots inside [start, end).
     * e.g. 09:00–12:00 → 09:00, 10:00, 11:00 (3 hours)
     */
    private function enumerateHours(string $startDt, string $endDt): array
    {
        $slots = [];
        $start = Carbon::parse($startDt)->minute(0)->second(0);
        $end   = Carbon::parse($endDt);

        while ($start->lt($end)) {
            $next = $start->copy()->addHour();
            if ($next->gt($end)) break;       // keep end exclusive
            $slots[] = $start->format('Y-m-d H:00:00');
            $start   = $next;
        }
        return $slots;
    }

    /**
     * Resolve counselor id used by both availability & appointments.
     */
    private function counselorId(): int
    {
        $u = Auth::user();
        if (!$u) abort(401, 'Unauthenticated.');

        if (!empty($u->counselor_id)) {
            $cid = (int) $u->counselor_id;
            if (DB::table('tbl_counselors')->where('id', $cid)->exists()) return $cid;
        }

        if (!empty($u->email)) {
            $cid = DB::table('tbl_counselors')->where('email', $u->email)->value('id');
            if ($cid) return (int) $cid;
        }

        abort(422, 'This account is not linked to a counselor record. Ask admin to set users.counselor_id or match your email in tbl_counselors.');
    }
}
