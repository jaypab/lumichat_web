<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ChatbotSessionController extends Controller
{
    private const FLASH_SWAL   = 'swal';
    private const PER_PAGE     = 10;
    private const DATE_KEY_ALL = 'all';
    private const DATE_KEYS    = ['all', '7d', '30d', 'month'];

    /** Minutes per slot (keep in sync with student side) */
    private const STEP_MINUTES = 30;

    /** Appointments that block a counselor’s slot */
    private const BLOCKING_STATUSES = ['pending','confirmed','completed'];

    /** For THIS session, these statuses mean “already booked” (disable Book button) */
    private const SESSION_ACTIVE_STATUSES = ['pending','confirmed'];

    public function __construct(
        protected ChatbotSessionRepositoryInterface $sessions
    ) {}

    /** List chatbot sessions with optional free-text and date filters. */
    public function index(Request $request): View
    {
        $q       = trim((string) $request->input('q', ''));
        $dateReq = (string) $request->input('date', self::DATE_KEY_ALL);
        $dateKey = in_array($dateReq, self::DATE_KEYS, true) ? $dateReq : self::DATE_KEY_ALL;

        // inside index()
        $sessions = $this->sessions->paginateWithFilters($q, $dateKey, self::PER_PAGE);

        // Sessions already linked to any blocking appointment (existing behavior)
        $handledSessionIds = DB::table('tbl_appointments')
            ->whereNotNull('chatbot_session_id')
            ->whereIn('status', self::BLOCKING_STATUSES) // ['pending','confirmed','completed']
            ->pluck('chatbot_session_id')
            ->unique()
            ->all();

        // 🔒 NEW: per-student guards
        $studentsWithActive = DB::table('tbl_appointments')
            ->whereIn('status', ['pending','confirmed'])
            ->pluck('student_id')
            ->unique()
            ->all();

        $studentsWithCompleted = DB::table('tbl_appointments')
            ->where('status', 'completed')
            ->pluck('student_id')
            ->unique()
            ->all();

        return view('admin.chatbot_sessions.index', compact(
            'sessions', 'q', 'dateKey',
            'handledSessionIds',
            'studentsWithActive',
            'studentsWithCompleted'
        ));
    }

    /** Show a single session with ordered chats. */
    public function show(int $id): View
    {
        $session = $this->sessions->findWithOrderedChats($id);
        abort_unless($session, 404);

        $hasActiveForThisSession = DB::table('tbl_appointments')
            ->where('chatbot_session_id', $session->id)
            ->whereIn('status', self::SESSION_ACTIVE_STATUSES) // ['pending','confirmed']
            ->exists();

        // 🔒 NEW: If the student already has any active appointment, block booking here too
        $hasActiveForStudent = DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
            ->exists();

        return view('admin.chatbot_sessions.show', compact(
            'session',
            'hasActiveForThisSession',
            'hasActiveForStudent' // <-- pass to Blade
        ));
    }

    /** Return per-day counts for a user's sessions (within a date range). */
    public function calendarCounts(int $id, Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        if (!$from || !$to) {
            return response()->json(['error' => 'from/to required'], 422);
        }

        $userId = $this->sessions->getUserIdBySessionId($id);
        if (!$userId) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $counts = $this->sessions->perDayCountsForUser((int) $userId, $from, $to);

        return response()->json(['counts' => $counts]);
    }

    /** Get counselor-wise slots for a date (Mon–Fri). */
    // inside ChatbotSessionController.php
   public function slots(int $id, Request $request): JsonResponse
    {
        $dateStr = (string) $request->query('date', '');
        if (!$dateStr || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['message' => 'Provide date=YYYY-MM-DD.'], 422);
        }

        $date   = Carbon::parse($dateStr)->startOfDay();
        $now    = now();
        $dowIso = $date->isoWeekday(); // 1..7 (Mon..Sun)

        if ($dowIso < 1 || $dowIso > 5) {
            return response()->json([
                'counselors' => [],
                'slots'      => [],
                'pooled'     => [],
                'message'    => 'Appointments are available Monday to Friday only.'
            ]);
        }

        $counselors = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name']);

        if ($counselors->isEmpty()) {
            return response()->json(['counselors'=>[], 'slots'=>[], 'pooled'=>[], 'message'=>'No active counselors.']);
        }

        // helper to snap to 30-min grid
        $snap = function (Carbon $dt): Carbon {
            $m = (int) floor($dt->minute / 30) * 30;
            return $dt->copy()->setTime($dt->hour, $m, 0);
        };

        $slotsByCounselor = [];
        $allTimes = []; // collect all unique HH:MM we will later count pooled capacity for

        foreach ($counselors as $c) {
            $ranges = DB::table('tbl_counselor_availabilities')
                ->where('counselor_id', $c->id)
                ->where('weekday', $dowIso)
                ->orderBy('start_time')
                ->get(['start_time','end_time']);

            $col = [];
            foreach ($ranges as $r) {
                if (!is_string($r->start_time) || !is_string($r->end_time) || $r->start_time==='' || $r->end_time==='') {
                    continue;
                }
                $cursor = $snap(Carbon::parse($date->toDateString().' '.$r->start_time)->second(0));
                $end    = Carbon::parse($date->toDateString().' '.$r->end_time)->second(0);

                while ($cursor->lt($end)) {
                    $slot = $snap($cursor);
                    $next = $slot->copy()->addMinutes(30);
                    if ($next->gt($end)) break;

                    $isPast = $date->isSameDay($now) && $slot->lte($now);

                    // block only if this counselor already taken at this exact time
                    $taken = DB::table('tbl_appointments')
                        ->where('counselor_id', $c->id)
                        ->where('scheduled_at', $slot)
                        ->whereIn('status', self::BLOCKING_STATUSES)
                        ->exists();

                    if (!$taken) {
                        $hhmm = $slot->format('H:i');
                        $col[] = [
                            'value'    => $hhmm,
                            'label'    => $slot->format('g:i A'),
                            'disabled' => $isPast,
                        ];
                        $allTimes[$hhmm] = true; // remember for pooled counting
                    }

                    $cursor = $cursor->addMinutes(30);
                }
            }

            $slotsByCounselor[$c->id] = collect($col)->unique('value')->sortBy('value')->values()->all();
        }

        // 🔢 Pooled capacity per HH:MM (how many counselors are free)
        $repo = app(\App\Repositories\Contracts\AppointmentRepositoryInterface::class);
        $pooled = [];
        foreach (array_keys($allTimes) as $hhmm) {
            $t = Carbon::parse($date->toDateString().' '.$hhmm.':00');
            $pooled[$hhmm] = count($repo->counselorIdsFreeAt($t));
        }

        return response()->json([
            'counselors' => $counselors->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->values(),
            'slots'      => $slotsByCounselor,
            'pooled'     => $pooled, // <-- NEW
        ]);
    }


    /** Admin books appointment for the session’s student with a chosen counselor+time. */
    public function book(int $id, Request $request): JsonResponse
    {
        $session = $this->sessions->findWithOrderedChats($id);
        if (!$session || empty($session->user_id)) {
            return response()->json(['message'=>'Session not found.'], 404);
        }
        $studentId = (int) $session->user_id;

        // Block if student already has pending/confirmed (you added this earlier)
        $hasActiveForStudent = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveForStudent) {
            return response()->json([
                'message' => 'This student already has an active appointment (pending/confirmed).'
            ], 409);
        }

        $validated = $request->validate([
            'date'         => ['required','date_format:Y-m-d'],
            'time'         => ['required','regex:/^\d{2}:\d{2}$/'],
            'counselor_id' => ['required','integer','exists:tbl_counselors,id'],
        ]);

        // snap to grid (existing)
        $raw  = Carbon::parse($validated['date'].' '.$validated['time'].':00')->second(0);
        $slot = (function(Carbon $dt){ $m=(int)floor($dt->minute/30)*30; return $dt->copy()->setTime($dt->hour,$m,0);} )($raw);
        if ($raw->ne($slot)) {
            return response()->json(['message'=>'Please choose a 30-minute step time (e.g., 09:00, 09:30).'], 422);
        }

        $dowIso = $slot->isoWeekday();
        if ($dowIso < 1 || $dowIso > 5) {
            return response()->json(['message'=>'Appointments are available Monday to Friday only.'], 422);
        }
        if ($slot->lte(now())) {
            return response()->json(['message'=>'Please choose a future time.'], 422);
        }

        $counselorId   = (int) $validated['counselor_id'];
        $counselorName = DB::table('tbl_counselors')->where('id',$counselorId)->value('name') ?? null; // ★ NEW
        $note          = $this->composeBookingNote($session, $slot, $counselorName);                   // ★ NEW

        // verify counselor availability (existing)
        $fits = DB::table('tbl_counselor_availabilities')
            ->where('counselor_id', $counselorId)
            ->where('weekday', $dowIso)
            ->get(['start_time','end_time'])
            ->contains(function($r) use ($slot) {
                $endOf = $slot->copy()->addMinutes(30);
                $start = Carbon::parse($slot->toDateString().' '.$r->start_time);
                $end   = Carbon::parse($slot->toDateString().' '.$r->end_time);
                return $slot->gte($start) && $endOf->lte($end);
            });
        if (!$fits) {
            return response()->json(['message'=>'Selected time is outside counselor availability.'], 422);
        }

        try {
            DB::transaction(function () use ($studentId, $counselorId, $slot, $session, $note) { // ★ add $note
                // re-check session active (race)
                $activeNow = DB::table('tbl_appointments')
                    ->where('chatbot_session_id', $session->id)
                    ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($activeNow) throw new \RuntimeException('SESSION_ACTIVE');

                // re-check student active (race)
                $activeNowForStudent = DB::table('tbl_appointments')
                    ->where('student_id', $studentId)
                    ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($activeNowForStudent) throw new \RuntimeException('STUDENT_ACTIVE');

                // counselor taken?
                $taken = DB::table('tbl_appointments')
                    ->where('counselor_id', $counselorId)
                    ->where('scheduled_at', $slot)
                    ->whereIn('status', self::BLOCKING_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($taken) throw new \RuntimeException('TAKEN');

                // Insert appointment + the heartfelt note ★ NEW
                DB::table('tbl_appointments')->insert([
                    'student_id'         => $studentId,
                    'counselor_id'       => $counselorId,
                    'scheduled_at'       => $slot,
                    'status'             => 'confirmed',
                    'note'               => $note,              // ★ NEW
                    'chatbot_session_id' => $session->id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // (Optional) also create a notification if such a table exists ★ NEW
                if (Schema::hasTable('tbl_notifications')) {
                    DB::table('tbl_notifications')->insert([
                        'user_id'    => $studentId,
                        'title'      => 'Appointment Scheduled',
                        'body'       => $note,
                        'type'       => 'appointment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'TAKEN')          return response()->json(['message'=>'That counselor/time just filled. Pick another slot.'], 409);
            if ($e->getMessage() === 'SESSION_ACTIVE') return response()->json(['message'=>'This session already has an active appointment (pending/confirmed).'], 409);
            if ($e->getMessage() === 'STUDENT_ACTIVE') return response()->json(['message'=>'This student already has an active appointment (pending/confirmed).'], 409);
            throw $e;
        }

        // Success payload now previews the message to the admin ★ NEW
        return response()->json([
            'ok'   => true,
            'html' => sprintf(
                '
                <div class="kv-grid">
                <div class="kv"><span class="label">Student:</span>   <span class="value">%s</span></div>
                <div class="kv"><span class="label">Counselor:</span> <span class="value">%s</span></div>
                <div class="kv"><span class="label">Date:</span>      <span class="value">%s</span></div>
                <div class="kv"><span class="label">Time:</span>      <span class="value">%s</span></div>
                </div>

                <div style="margin:6px 0 2px"><b>Note sent to student:</b></div>
                <div style="white-space:pre-wrap">%s</div>
                ',
                e($session->user->name ?? ('#'.$studentId)),
                e($counselorName ?? '—'),
                e($slot->format('M d, Y')),
                e($slot->format('g:i A')),
                e($note)
            ),
        ]);
    }

    private function composeBookingNote(object $session, \Carbon\Carbon $slot, ?string $counselorName = null): string
    {
        $studentName = (string) ($session->user->name ?? '');
        $firstName   = \Illuminate\Support\Str::of($studentName)->trim()->before(' ')->value() ?: 'there';

        $niceDate = $slot->format('l, M d, Y');
        $niceTime = $slot->format('g:i A');
        $who      = $counselorName ? "with {$counselorName}" : "with our guidance counselor";

        // You can move this to config if you like:
        $location = 'Guidance Office, Tagoloan Community College';

        return "Hi {$firstName},\n\n"
            . "LumiCHAT noticed you might be going through a lot, and we want to support you. "
            . "We’ve set a confidential check-in for you:\n\n"
            . "📅 {$niceDate} • ⏰ {$niceTime}\n"
            . "👤 {$who}\n"
            . "📍 {$location}\n\n"
            . "This is 100% confidential and judgment-free. Please arrive about 10 minutes early and bring your school ID if possible. "
            . "If you need to reschedule, just reply to this message or visit the Guidance Office.\n\n"
            . "We’re here for you. One step at a time—you are not alone.";
    }
    // App\Http\Controllers\Admin\ChatbotSessionController.php
    public function exportPdf(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $dateReq = (string) $request->input('date', self::DATE_KEY_ALL);
        $dateKey = in_array($dateReq, self::DATE_KEYS, true) ? $dateReq : self::DATE_KEY_ALL;

        $rows = method_exists($this->sessions, 'allWithFilters')
            ? $this->sessions->allWithFilters($q, $dateKey)
            : (function () use ($q, $dateKey) {
                $p = $this->sessions->paginateWithFilters($q, $dateKey, PHP_INT_MAX);
                return method_exists($p, 'items') ? collect($p->items()) : collect($p);
            })();

        // inline logo
        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

       $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([  
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
        ]);

        // 🔴 was missing
        $pdf->loadView('admin.chatbot_sessions.pdf', [
            'rows'        => $rows,
            'q'           => $q,
            'dateKey'     => $dateKey,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        return $pdf->download('Chatbot_Sessions'.now()->format('Ymd_His').'.pdf');
    }

    public function exportOne(int $session)
    {
        $row = $this->sessions->findWithOrderedChats($session)
            ?? DB::table('tbl_chatbot_sessions')->where('id', $session)->first();
        abort_unless($row, 404);

        // inline logo
        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        // risk & code
        $riskLevel = strtolower((string)($row->risk_level ?? $row->risk ?? ''));
        $riskScore = (int)($row->risk_score ?? 0);
        $isHigh    = in_array($riskLevel, ['high','high-risk','high_risk'], true) || $riskScore >= 80;

        $year = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y') : now()->format('Y');
        $code = 'LMC-' . $year . '-' . str_pad((string)$session, 4, '0', STR_PAD_LEFT);

        if (!is_dir(storage_path('fonts'))) {
            @mkdir(storage_path('fonts'), 0777, true);
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
        ]);

        // 🔴 was missing
        $pdf->loadView('admin.chatbot_sessions.session_pdf', [
            'session'     => $row,
            'code'        => $code,
            'logoData'    => $logoData,
            'isHighRisk'  => $isHigh,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download('Chatbot_Session_'.$session.'_'.now()->format('Ymd_His').'.pdf');
    }

}
