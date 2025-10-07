<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ChatbotSessionRepositoryInterface;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\Response;

class ChatbotSessionController extends Controller
{
    private const FLASH_SWAL   = 'swal';
    private const PER_PAGE     = 10;
    private const DATE_KEY_ALL = 'all';
    private const DATE_KEYS    = ['all', '7d', '30d', 'month'];

    /** Minutes per slot */
    private const STEP_MINUTES = 30;

    /** Appointments that block a counselor’s slot */
    private const BLOCKING_STATUSES = ['pending','confirmed','completed'];

    /** For THIS session, these statuses mean “already booked” (disable Book) */
    private const SESSION_ACTIVE_STATUSES = ['pending','confirmed'];

    public function __construct(
        protected ChatbotSessionRepositoryInterface $sessions
    ) {}

    /** INDEX: list chatbot sessions with “handled/cleared AFTER session” maps */
    public function index(Request $r): View
    {
        $q       = (string) $r->query('q', '');
        $dateKey = (string) $r->query('date', 'all');

        $sessions = $this->sessions->paginateWithFilters($q, $dateKey, self::PER_PAGE);

        // Build “handled/cleared after this session” maps for the page
        $pageSessions = collect($sessions->items());
        $sessionIds   = $pageSessions->pluck('id')->all();
        $byId         = $pageSessions->keyBy('id');
        $studentIds   = $pageSessions->pluck('user_id')->unique()->all();

        $active = DB::table('tbl_appointments')
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['pending','confirmed'])
            ->get(['student_id','created_at']);

        $completed = DB::table('tbl_appointments')
            ->whereIn('student_id', $studentIds)
            ->where('status', 'completed')
            ->get(['student_id','updated_at']);

        $activeByStudent    = $active->groupBy('student_id');
        $completedByStudent = $completed->groupBy('student_id');

        $handledAfter  = [];
        $clearedAfter  = [];

        foreach ($sessionIds as $sid) {
            $sess = $byId[$sid] ?? null;
            if (!$sess) { $handledAfter[$sid] = false; $clearedAfter[$sid] = false; continue; }

            $sStudent = (int) $sess->user_id;
            $sAt      = $sess->created_at;

            // handled if any active appt booked AFTER (or same time as) this session
            $handledAfter[$sid] = (bool) optional($activeByStudent->get($sStudent))->first(function ($ap) use ($sAt) {
                return $ap->created_at >= $sAt;
            });

            // cleared if any completed appt completed AFTER (or same time as) this session
            $clearedAfter[$sid] = (bool) optional($completedByStudent->get($sStudent))->first(function ($ap) use ($sAt) {
                return $ap->updated_at >= $sAt;
            });
        }

        return view('admin.chatbot_sessions.index', [
            'sessions'     => $sessions,
            'q'            => $q,
            'dateKey'      => $dateKey,
            'handledAfter' => $handledAfter,  // session_id => bool
            'clearedAfter' => $clearedAfter,  // session_id => bool
        ]);
    }

    /** SHOW: one session + ordered chats + per-session handled flags */
    public function show(int $id): View
    {
        $session = $this->sessions->findWithOrderedChats($id);
        abort_unless($session, 404);

        $hasAnyActiveForStudent = DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->whereIn('status', ['pending','confirmed'])
            ->exists();

        $hasActiveAfterThisSession = DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->whereIn('status', ['pending','confirmed'])
            ->where('created_at', '>=', $session->created_at)
            ->exists();

        $hasCompletedForThisSession = DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->where('status', 'completed')
            ->where('updated_at', '>=', $session->created_at)
            ->exists();

        // ✅ robust one-time flag: either column is set OR an active appt is already linked to this session
        $wasExpedited = !empty($session->expedited_at) || DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->where('chatbot_session_id', $session->id)
            ->whereIn('status', ['pending','confirmed'])
            ->exists();

        return view('admin.chatbot_sessions.show', [
            'session'                    => $session,
            'hasAnyActiveForStudent'     => $hasAnyActiveForStudent,
            'hasActiveAfterThisSession'  => $hasActiveAfterThisSession,
            'hasCompletedForThisSession' => $hasCompletedForThisSession,
            'wasExpedited'               => $wasExpedited,
        ]);
    }

    /** JSON: per-day counts for a user's sessions (calendar header) */
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

    /** JSON: counselor-wise slots + pooled capacity for a date (Mon–Fri) */
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
                'counselors' => [], 'slots' => [], 'pooled' => [],
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

        $snap = function (Carbon $dt): Carbon {
            $m = (int) floor($dt->minute / 30) * 30;
            return $dt->copy()->setTime($dt->hour, $m, 0);
        };

        $slotsByCounselor = [];
        $allTimes = [];

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
                        $allTimes[$hhmm] = true;
                    }
                    $cursor = $cursor->addMinutes(30);
                }
            }
            $slotsByCounselor[$c->id] = collect($col)->unique('value')->sortBy('value')->values()->all();
        }

        // Pooled capacity per HH:MM
        $repo   = app(AppointmentRepositoryInterface::class);
        $pooled = [];
        foreach (array_keys($allTimes) as $hhmm) {
            $t = Carbon::parse($date->toDateString().' '.$hhmm.':00');
            $pooled[$hhmm] = count($repo->counselorIdsFreeAt($t));
        }

        return response()->json([
            'counselors' => $counselors->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->values(),
            'slots'      => $slotsByCounselor,
            'pooled'     => $pooled,
        ]);
    }

    /** Admin books appointment for the session’s student with counselor+time */
    public function book(int $id, Request $request): JsonResponse
    {
        $session = $this->sessions->findWithOrderedChats($id);
        if (!$session || empty($session->user_id)) {
            return response()->json(['message'=>'Session not found.'], 404);
        }
        $studentId = (int) $session->user_id;

        // ✅ block if the student already has ANY active appointment (pending/confirmed)
        $hasActiveForStudent = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
            ->exists();
        if ($hasActiveForStudent) {
            return response()->json(['message' => 'Student already has an active appointment.'], 409);
        }

        $validated = $request->validate([
            'date'         => ['required','date_format:Y-m-d'],
            'time'         => ['required','regex:/^\d{2}:\d{2}$/'],
            'counselor_id' => ['required','integer','exists:tbl_counselors,id'],
        ]);

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
        $counselorName = DB::table('tbl_counselors')->where('id',$counselorId)->value('name') ?? null;
        $note          = $this->composeBookingNote($session, $slot, $counselorName);

        try {
            DB::transaction(function () use ($studentId, $counselorId, $slot, $session, $note) {
                // re-check for race
                $activeNowForStudent = DB::table('tbl_appointments')
                    ->where('student_id', $studentId)
                    ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($activeNowForStudent) throw new \RuntimeException('STUDENT_ACTIVE');

                $taken = DB::table('tbl_appointments')
                    ->where('counselor_id', $counselorId)
                    ->where('scheduled_at', $slot)
                    ->whereIn('status', self::BLOCKING_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($taken) throw new \RuntimeException('TAKEN');

                DB::table('tbl_appointments')->insert([
                    'student_id'         => $studentId,
                    'counselor_id'       => $counselorId,
                    'scheduled_at'       => $slot,
                    'status'             => 'confirmed',
                    'note'               => $note,
                    'chatbot_session_id' => $session->id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // Optional notification
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
            if ($e->getMessage() === 'STUDENT_ACTIVE') return response()->json(['message'=>'This student already has an active appointment (pending/confirmed).'], 409);
            throw $e;
        }

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

    private function composeBookingNote(object $session, Carbon $slot, ?string $counselorName = null): string
    {
        $studentName = (string) ($session->user->name ?? '');
        $firstName   = Str::of($studentName)->trim()->before(' ')->value() ?: 'there';

        $niceDate = $slot->format('l, M d, Y');
        $niceTime = $slot->format('g:i A');
        $who      = $counselorName ? "with {$counselorName}" : "with our guidance counselor";
        $location = 'Guidance Office, Tagoloan Community College';

        return "Hi {$firstName},\n\n"
            . "LumiCHAT noticed you might be going through a lot, and we want to support you. "
            . "We’ve set a confidential check-in for you:\n\n"
            . "📅 {$niceDate} • ⏰ {$niceTime}\n"
            . "👤 {$who}\n"
            . "📍 {$location}\n\n"
            . "This is 100% confidential and judgment-free. Please arrive ~10 minutes early and bring your school ID if possible. "
            . "If you need to reschedule, just reply to this message or visit the Guidance Office.\n\n"
            . "We’re here for you. One step at a time—you are not alone.";
    }

    /** EXPORT: list */
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

        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        $pdf->loadView('admin.chatbot_sessions.pdf', [
            'rows'        => $rows,
            'q'           => $q,
            'dateKey'     => $dateKey,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        return $pdf->download('Chatbot_Sessions_'.now()->format('Ymd_His').'.pdf');
    }

    /** EXPORT: one session */
    public function exportOne(int $session)
    {
        $row = $this->sessions->findWithOrderedChats($session);
        if (!$row) {
            if ($table = $this->sessionsTable()) {
                $row = DB::table($table)->where('id', $session)->first();
            }
        }
        abort_unless($row, 404);

        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        $riskLevel = strtolower((string)($row->risk_level ?? $row->risk ?? ''));
        $riskScore = (int)($row->risk_score ?? 0);
        $isHigh    = in_array($riskLevel, ['high','high-risk','high_risk'], true) || $riskScore >= 80;

        $year = $row->created_at ? Carbon::parse($row->created_at)->format('Y') : now()->format('Y');
        $code = 'LMC-' . $year . '-' . str_pad((string)$session, 4, '0', STR_PAD_LEFT);

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        $pdf->loadView('admin.chatbot_sessions.session_pdf', [
            'session'     => $row,
            'code'        => $code,
            'logoData'    => $logoData,
            'isHighRisk'  => $isHigh,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ]);

        return $pdf->download('Chatbot_Session_'.$session.'_'.now()->format('Ymd_His').'.pdf');
    }

    public function reschedule(int $id, Request $request): JsonResponse
    {
        $session = $this->sessions->findWithOrderedChats($id);
        if (!$session || empty($session->user_id)) {
            return response()->json(['message' => 'Session not found.'], 404);
        }
        $studentId = (int) $session->user_id;

        // one-time guard
        if (!empty($session->expedited_at)) {
            return response()->json(['message' => 'This session was already moved earlier.'], 409);
        }

        // earliest FUTURE active appt to move
        $appt = DB::table('tbl_appointments')
            ->where('student_id', $studentId)
            ->whereIn('status', self::SESSION_ACTIVE_STATUSES)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first();

        if (!$appt) {
            return response()->json(['message' => 'No active appointment to reschedule.'], 409);
        }

        // validate inputs (throws 422 JSON on fail)
        $request->validate([
            'date'         => ['required','date_format:Y-m-d'],
            'time'         => ['required','regex:/^\d{2}:\d{2}$/'],
            'counselor_id' => ['required','integer','exists:tbl_counselors,id'],
        ]);

        // pull values (avoid using $validated)
        $date        = (string) $request->input('date');
        $time        = (string) $request->input('time');
        $counselorId = (int)    $request->input('counselor_id');

        // build & check slot
        $raw  = Carbon::parse($date.' '.$time.':00')->second(0);
        $slot = (function(Carbon $dt){ $m=(int)floor($dt->minute/30)*30; return $dt->copy()->setTime($dt->hour,$m,0);} )($raw);
        if ($raw->ne($slot))                                return response()->json(['message'=>'Please choose a 30-minute step time.'], 422);
        if ($slot->isoWeekday() < 1 || $slot->isoWeekday() > 5) return response()->json(['message'=>'Mon–Fri only.'], 422);
        if ($slot->lte(now()))                              return response()->json(['message'=>'Please choose a future time.'], 422);

        $counselorName = DB::table('tbl_counselors')->where('id',$counselorId)->value('name') ?? null;
        $note = $this->composeRescheduleNote($session, $slot, $counselorName);

        try {
            DB::transaction(function () use ($session, $appt, $studentId, $counselorId, $slot, $note) {
                // lock session row (dynamic table name support)
                $sessTable = $this->sessionsTable();
                if ($sessTable) {
                    $sessRow = DB::table($sessTable)->where('id',$session->id)->lockForUpdate()->first();
                    if (!$sessRow || !empty($sessRow->expedited_at)) {
                        throw new \RuntimeException('ALREADY_EXPEDITED');
                    }
                }

                // lock current appt
                $current = DB::table('tbl_appointments')->where('id',$appt->id)->lockForUpdate()->first();
                if (!$current || !in_array($current->status, self::SESSION_ACTIVE_STATUSES, true)) {
                    throw new \RuntimeException('APPT_GONE');
                }

                // ensure target slot free
                $taken = DB::table('tbl_appointments')
                    ->where('counselor_id', $counselorId)
                    ->where('scheduled_at', $slot)
                    ->whereIn('status', self::BLOCKING_STATUSES)
                    ->lockForUpdate()
                    ->exists();
                if ($taken) throw new \RuntimeException('TAKEN');

                // move the appt
                DB::table('tbl_appointments')->where('id',$appt->id)->update([
                    'counselor_id'       => $counselorId,
                    'scheduled_at'       => $slot,
                    'note'               => $note,
                    'chatbot_session_id' => $session->id,
                    'updated_at'         => now(),
                ]);

                // mark session as expedited (if columns exist)
                if ($sessTable) {
                    $updates = ['updated_at' => now()];
                    if (Schema::hasColumn($sessTable, 'expedited_appt_id')) $updates['expedited_appt_id'] = $appt->id;
                    if (Schema::hasColumn($sessTable, 'expedited_at'))      $updates['expedited_at']      = now();
                    DB::table($sessTable)->where('id',$session->id)->update($updates);
                }

                // optional notification
                if (Schema::hasTable('tbl_notifications')) {
                    DB::table('tbl_notifications')->insert([
                        'user_id'    => $studentId,
                        'title'      => 'Appointment Rescheduled',
                        'body'       => $note,
                        'type'       => 'appointment',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage()==='ALREADY_EXPEDITED') return response()->json(['message'=>'This session was already moved earlier.'], 409);
            if ($e->getMessage()==='TAKEN')            return response()->json(['message'=>'That time just filled. Pick another.'], 409);
            if ($e->getMessage()==='APPT_GONE')        return response()->json(['message'=>'The appointment changed. Reload and try again.'], 409);
            throw $e;
        }

        return response()->json([
            'ok'   => true,
            'html' => sprintf(
                '
                <div class="kv-grid">
                <div class="kv"><span class="label">Student:</span>   <span class="value">%s</span></div>
                <div class="kv"><span class="label">Counselor:</span> <span class="value">%s</span></div>
                <div class="kv"><span class="label">New Date:</span>  <span class="value">%s</span></div>
                <div class="kv"><span class="label">New Time:</span>  <span class="value">%s</span></div>
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


    /** Different message when we move an appointment earlier */
    private function composeRescheduleNote(object $session, Carbon $slot, ?string $counselorName = null): string
    {
        $studentName = (string) ($session->user->name ?? '');
        $firstName   = Str::of($studentName)->trim()->before(' ')->value() ?: 'there';

        $niceDate = $slot->format('l, M d, Y');
        $niceTime = $slot->format('g:i A');
        $who      = $counselorName ? "with {$counselorName}" : "with our guidance counselor";
        $location = 'Guidance Office, Tagoloan Community College';

        return "Hi {$firstName},\n\n"
            . "Because your recent LumiCHAT session was flagged as high-risk, we moved your guidance appointment to an earlier time so we can check in with you sooner:\n\n"
            . "📅 {$niceDate} • ⏰ {$niceTime}\n"
            . "👤 {$who}\n"
            . "📍 {$location}\n\n"
            . "If this time won’t work, reply to this message or visit the Guidance Office and we’ll adjust it. You’re not alone—we’re here for you.";
    }

    private function sessionsTable(): ?string
    {
        foreach (['tbl_chatbot_sessions', 'chatbot_sessions', 'tbl_chatbot_session'] as $name) {
            if (Schema::hasTable($name)) return $name;
        }
        return null;
    }
}
