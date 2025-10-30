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
use Illuminate\Support\Facades\Crypt;
use App\Models\ChatbotSessionRiskLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\RiskHeuristics;
use App\Models\Chat;

class ChatbotSessionController extends Controller
{
    private const FLASH_SWAL   = 'swal';
    private const PER_PAGE     = 10;
    private const DATE_KEY_ALL = 'all';
    private const DATE_KEYS    = ['all', '7d', '30d', 'month'];

    /** Minutes the global page re-auth stays valid (already added earlier) */
    private const REAUTH_WINDOW_MINUTES = 10;

    /** Minutes the *sensitive* re-auth stays valid (shorter) */
    private const REAUTH_SENSITIVE_MINUTES = 5;

    /** Minutes per slot */
    private const STEP_MINUTES = 30;

    /** Appointments that block a counselor’s slot */
    private const BLOCKING_STATUSES = ['pending','confirmed','completed'];

    /** For THIS session, these statuses mean “already booked” (disable Book) */
    private const SESSION_ACTIVE_STATUSES = ['pending','confirmed'];

    public function __construct(
        protected ChatbotSessionRepositoryInterface $sessions
    ) {}

    private function sensitiveOkay(): bool
    {
        $until = session('admin.reauth_sensitive_until');
        if (!$until) return false;
        try { return now()->lt(\Carbon\Carbon::parse($until)); } catch (\Throwable) { return false; }
    }

    private function reauthOkay(): bool
    {
        $until = session('admin.reauth_until');
        if (!$until) return false;
        try {
            return now()->lt(\Carbon\Carbon::parse($until));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function confirmSensitiveAjax(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required','string']]);

        $user = auth()->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);

        // throttle by user+IP
        $key = sprintf('reauth:sensitive:%s:%s', (string)$user->id, (string)$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $sec = RateLimiter::availableIn($key);
            return response()->json(['message' => "Too many attempts. Try again in {$sec}s."], 429);
        }

        if (!Hash::check((string)$request->input('password'), $user->password)) {
            RateLimiter::hit($key, 60);
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        RateLimiter::clear($key);
        session(['admin.reauth_sensitive_until' => now()->addMinutes(self::REAUTH_SENSITIVE_MINUTES)]);
        return response()->json(['ok' => true]);
    }

    /**
     * GET sensitive High-risk details (after sensitive re-auth)
     */
    public function sensitiveDetails(int $session): JsonResponse
    {
        if (!$this->sensitiveOkay()) {
            return response()->json(['message' => 'Second verification required.'], 403);
        }

        $row = $this->sessions->findWithOrderedChats($session);
        if (!$row) return response()->json(['message' => 'Not found.'], 404);

        // Build only the sensitive piece (same logic you use in show(), but isolated)
        $highRisk = (object)['id'=>null, 'text'=>null, 'sent_at'=>null];

        if (!empty($row->high_risk_chat_id)) {
            $m = \DB::table('chats')
                ->where('id', $row->high_risk_chat_id)
                ->where('chat_session_id', $row->id)
                ->first(['id','message','sent_at','sender']);
            if ($m && ($m->sender ?? 'user') === 'user') {
                $plain = $this->tryDecryptOrPlain($m->message) ?? '[Unreadable]';
                $highRisk->id = $m->id;
                $highRisk->text = $plain;
                $highRisk->sent_at = $m->sent_at;
            }
        }

        // Fallback: scan user messages oldest → newest; stop on FIRST high-risk hit
        if (!$highRisk->id) {
            $msgs = \DB::table('chats')
                ->where('chat_session_id', $row->id)
                ->where('sender', 'user')
                ->orderBy('sent_at')
                ->get(['id','message','sent_at']);

            foreach ($msgs as $m) {
                $plain = $this->tryDecryptOrPlain($m->message);
                if (!$plain) continue;

                if (RiskHeuristics::containsHighRisk($plain)) {
                    $highRisk->id = $m->id;
                    $highRisk->text = $plain;
                    $highRisk->sent_at = $m->sent_at;
                    break;
                }
            }
        }

        return response()->json([
            'ok'  => true,
            'id'  => $highRisk->id,
            'at'  => $highRisk->sent_at ? \Carbon\Carbon::parse($highRisk->sent_at)->format('F d, Y • h:i A') : null,
            'txt' => $highRisk->text,
        ]);
    }

    /**
     * POST /admin/reauth/confirm (AJAX)
     * Body: password
     */
    public function confirmPasswordAjax(Request $request): JsonResponse
    {
        // Basic validation
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Throttle based on user+IP
        $key = sprintf('reauth:%s:%s', (string) $user->id, (string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Too many attempts. Try again in '.$seconds.'s.',
            ], 429);
        }

        $password = (string) $request->input('password');

        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 60); // decay in 60s
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        // Success → clear attempts and set short-lived re-auth window
        RateLimiter::clear($key);
        session(['admin.reauth_until' => now()->addMinutes(self::REAUTH_WINDOW_MINUTES)]);

        return response()->json(['ok' => true, 'until' => session('admin.reauth_until')]);
    }

    /** INDEX: list chatbot sessions with “handled/cleared AFTER session” maps */
    public function index(Request $r): View
    {
        $q       = (string) $r->query('q', '');
        $dateKey = (string) $r->query('date', 'all');
        $sort    = (string) $r->query('sort', 'newest');

        $sessions = $this->sessions->paginateWithFilters($q, $dateKey, self::PER_PAGE, $sort);
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
            'handledAfter' => $handledAfter,
            'clearedAfter' => $clearedAfter,
            'sort'         => $sort,
        ]);
    }

    /** SHOW: one session + ordered chats + per-session handled flags */
    public function show(int $id): View
    {
        if (!$this->reauthOkay()) {
            return view('admin.chatbot_sessions.show_gate', ['sessionId' => $id]);
        }

        $session = $this->sessions->findWithOrderedChats($id);
        abort_unless($session, 404);

        $sensitiveLocked = !$this->sensitiveOkay();

        // ----- Common aggregates
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

        $wasExpedited = !empty($session->expedited_at) || DB::table('tbl_appointments')
            ->where('student_id', $session->user_id)
            ->where('chatbot_session_id', $session->id)
            ->whereIn('status', ['pending','confirmed'])
            ->exists();

        $nextAppt = DB::table('tbl_appointments as a')
            ->leftJoin('tbl_counselors as c', 'c.id', '=', 'a.counselor_id')
            ->where('a.student_id', $session->user_id)
            ->whereIn('a.status', ['pending','confirmed'])
            ->where('a.scheduled_at', '>', now())
            ->orderBy('a.scheduled_at')
            ->select(['a.id','a.scheduled_at','a.status','c.name as counselor_name'])
            ->first();

        $logs     = ChatbotSessionRiskLog::where('chatbot_session_id', $session->id)->latest()->get();
        $lastRisk = $logs->first();

        // Build high-risk trigger ONLY when unlocked
        $highRisk = null;
        if (!$sensitiveLocked) {
            $highRisk = (object)['id'=>null, 'text'=>null, 'sent_at'=>null];

            // A) Prefer the stamped first-trigger id if present
            if (!empty($session->high_risk_chat_id)) {
                $row = DB::table('chats')
                    ->where('id', $session->high_risk_chat_id)
                    ->where('chat_session_id', $session->id)
                    ->first(['id','message','sent_at','sender']);

                if ($row && ($row->sender ?? 'user') === 'user') {
                    $plain = $this->tryDecryptOrPlain($row->message) ?? '[Unreadable]';
                    $highRisk->id = $row->id;
                    $highRisk->text = $plain;
                    $highRisk->sent_at = $row->sent_at;
                }
            }

            // B) Fallback for legacy sessions: scan **oldest → newest** and stop at FIRST match
            if (!$highRisk->id) {
                $msgs = DB::table('chats')
                    ->where('chat_session_id', $session->id)
                    ->where('sender', 'user')
                    ->orderBy('sent_at')          // oldest → newest
                    ->orderBy('id')
                    ->get(['id','message','sent_at']);

                foreach ($msgs as $m) {
                    $plain = $this->tryDecryptOrPlain($m->message);
                    if (!$plain) continue;

                    if (RiskHeuristics::containsHighRisk($plain)) {
                        $highRisk->id = $m->id;
                        $highRisk->text = $plain;
                        $highRisk->sent_at = $m->sent_at;
                        break; // FIRST match only
                    }
                }
            }
        }

        // NEW: collect all high-risk lines when unlocked
        $allHighRisk = [];
        if (!$sensitiveLocked) {
            $allHighRisk = $this->collectAllHighRiskItems($session);
        }

        return view('admin.chatbot_sessions.show', [
            'session'                    => $session,
            'riskLogs'                   => $logs,
            'lastRisk'                   => $lastRisk,
            'hasAnyActiveForStudent'     => $hasAnyActiveForStudent,
            'hasActiveAfterThisSession'  => $hasActiveAfterThisSession,
            'hasCompletedForThisSession' => $hasCompletedForThisSession,
            'wasExpedited'               => $wasExpedited,
            'nextAppt'                   => $nextAppt,
            'sensitiveLocked'            => $sensitiveLocked,
            'highRisk'                   => $highRisk,
            'allHighRisk'                => $allHighRisk,
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
                'occupied_by'=> [],
                'message'    => 'Appointments are available Monday to Friday only.'
            ]);
        }

        $counselors = DB::table('tbl_counselors')
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id','name']);

        if ($counselors->isEmpty()) {
            return response()->json([
                'counselors'=>[], 'slots'=>[], 'pooled'=>[], 'occupied_by'=>[],
                'message'=>'No active counselors.'
            ]);
        }

        // Your UI shows hour pills: 09,10,11,13,14,15
        $hourStarts = ['09:00','10:00','11:00','13:00','14:00','15:00'];

        $snap = function (Carbon $dt): Carbon {
            $m = (int) floor($dt->minute / 30) * 30;
            return $dt->copy()->setTime($dt->hour, $m, 0);
        };

        $slotsByCounselor = [];
        $occupiedBy       = [];           // NEW: per-counselor fully-booked hours
        $allTimes         = [];

        foreach ($counselors as $c) {
            // Track how many free half-hours exist inside each display hour
            $hourFreeCounts = array_fill_keys($hourStarts, 0);

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

                    if (!$taken && !$isPast) {
                        $hhmm = $slot->format('H:i');
                        $col[] = [
                            'value'    => $hhmm,
                            'label'    => $slot->format('g:i A'),
                            'disabled' => false,
                        ];
                        // Count free half-hours into hour bucket (e.g., 10:30 -> 10:00)
                        $hourLabel = substr($hhmm, 0, 2).':00';
                        if (isset($hourFreeCounts[$hourLabel])) {
                            $hourFreeCounts[$hourLabel]++;
                        }
                        $allTimes[$hhmm] = true;
                    }

                    $cursor = $cursor->addMinutes(30);
                }
            }

            // Determine which display hours are fully booked (no free half-hour)
            $occupied = [];
            foreach ($hourFreeCounts as $hour => $freeCount) {
                if ($freeCount === 0) {
                    $occupied[] = $hour;
                }
            }

            $slotsByCounselor[$c->id] = collect($col)->unique('value')->sortBy('value')->values()->all();
            $occupiedBy[$c->id]       = $occupied; // NEW
        }

        // Pooled capacity per HH:MM (as you had)
        $repo   = app(AppointmentRepositoryInterface::class);
        $pooled = [];
        foreach (array_keys($allTimes) as $hhmm) {
            $t = Carbon::parse($date->toDateString().' '.$hhmm.':00');
            $pooled[$hhmm] = count($repo->counselorIdsFreeAt($t));
        }

        return response()->json([
            'counselors' => $counselors->map(fn($r)=>['id'=>$r->id,'name'=>$r->name])->values(),
            'slots'      => $slotsByCounselor,   // unchanged
            'pooled'     => $pooled,             // unchanged
            'occupied_by'=> $occupiedBy,         // NEW
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

        // block if the student already has ANY active appointment (pending/confirmed)
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

        $createdId = null;

        try {
            DB::transaction(function () use ($studentId, $counselorId, $slot, $session, $note, &$createdId) {
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

                        $createdId = DB::table('tbl_appointments')->insertGetId([
                        'student_id'         => $studentId,
                        'counselor_id'       => $counselorId,
                        'scheduled_at'       => $slot,
                        'status'             => 'confirmed',
                        'note'               => $note,
                        'chatbot_session_id' => $session->id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);

                    // optional notification (unchanged) ...
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
                if ($e->getMessage() === 'TAKEN')
                    return response()->json(['message'=>'That counselor/time just filled. Pick another slot.'], 409);
                if ($e->getMessage() === 'STUDENT_ACTIVE')
                    return response()->json(['message'=>'This student already has an active appointment (pending/confirmed).'], 409);
                throw $e;
            }
        $start = $slot->copy()->second(0);
        $rel   = $start->isFuture()
            ? 'in '.$start->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW])
            : 'Started '.$start->diffForHumans(now(), ['parts'=>2,'short'=>true,'syntax'=>Carbon::DIFF_RELATIVE_TO_NOW]);

        return response()->json([
            'ok' => true,
            'appointment' => [
                'id'                => $createdId,
                'scheduled_at_iso'  => $start->toIso8601String(),
                'date_label'        => $start->format('M d, Y'),
                'time_label'        => $start->format('g:i A'),
                'rel_label'         => $rel,                  // e.g., "in 11h 2m"
                'counselor_name'    => $counselorName,        // may be null
            ],
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
                e($start->format('M d, Y')),
                e($start->format('g:i A')),
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
        $sort    = (string) $request->input('sort', 'newest');

        $rows = method_exists($this->sessions, 'allWithFilters')
            ? $this->sessions->allWithFilters($q, $dateKey, $sort)
            : (function () use ($q, $dateKey, $sort) {
                $p = $this->sessions->paginateWithFilters($q, $dateKey, PHP_INT_MAX, $sort);
                return method_exists($p, 'items') ? collect($p->items()) : collect($p);
            })();

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
            'dpi'                  => 96,
            'isPhpEnabled'         => true,
        ]);

        $pdf->loadView('admin.chatbot_sessions.pdf', [
            'rows'        => $rows,
            'q'           => $q,
            'dateKey'     => $dateKey,
            'generatedAt' => now()->format('Y-m-d H:i'),
            'logoData'    => $logoData,
        ]);

        $filename = 'Chatbot_Sessions_' . now()->format('Ymd_His') . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }
        return $pdf->stream($filename);
    }

    public function exportOne(Request $request, int $session)
    {
        $row = $this->sessions->findWithOrderedChats($session)
            ?? (optional($this->sessionsTable()) ? DB::table($this->sessionsTable())->where('id', $session)->first() : null);

        abort_unless($row, 404);

        $logoData = null;
        $logoPath = public_path('images/chatbot.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($logoPath));
        }

        $riskLevel = strtolower((string)($row->risk_level ?? $row->risk ?? ''));
        $riskScore = (int)($row->risk_score ?? 0);
        $isHigh    = in_array($riskLevel, ['high','high-risk','high_risk'], true) || $riskScore >= 80;

        $year = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y') : now()->format('Y');
        $code = 'LMC-' . $year . '-' . str_pad((string)$session, 4, '0', STR_PAD_LEFT);

        $sessionCounts = ['all' => null, 'd30' => null, 'd7' => null];
        if (!empty($row->user_id)) {
            $uid = (int) $row->user_id;
            $sessionCounts['all'] = DB::table('chat_sessions')->where('user_id', $uid)->count();
            $sessionCounts['d30'] = DB::table('chat_sessions')->where('user_id', $uid)->where('created_at', '>=', now()->subDays(30))->count();
            $sessionCounts['d7']  = DB::table('chat_sessions')->where('user_id', $uid)->where('created_at', '>=', now()->subDays(7))->count();
        }

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled'      => true,
            'dpi'                  => 96,
            'isPhpEnabled'         => true,
        ]);

        $pdf->loadView('admin.chatbot_sessions.session_pdf', [
            'session'       => $row,
            'code'          => $code,
            'logoData'      => $logoData,
            'isHighRisk'    => $isHigh,
            'generatedAt'   => now()->format('Y-m-d H:i'),
            'sessionCounts' => $sessionCounts,
        ]);

        $filename = 'Chatbot_Session_' . $session . '_' . now()->format('Ymd_His') . '.pdf';

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }
        return $pdf->stream($filename);
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
        foreach ([
            // most likely first
            'chat_sessions',
            'tbl_chat_sessions',

            // older names used elsewhere
            'tbl_chatbot_sessions',
            'chatbot_sessions',
            'tbl_chatbot_session',
        ] as $name) {
            if (\Illuminate\Support\Facades\Schema::hasTable($name)) {
                return $name;
            }
        }
        return null;
    }

    public function highRiskAll(int $sessionId): \Illuminate\Http\JsonResponse
    {
        if (!$this->sensitiveOkay()) {
            return response()->json(['message' => 'Second verification required.'], 403);
        }

        $row = $this->sessions->findWithOrderedChats($sessionId);
        if (!$row) return response()->json(['message' => 'Not found.'], 404);

        // Resolve chats table
        try { $chatTable = app(Chat::class)->getTable() ?: 'chats'; }
        catch (\Throwable) { $chatTable = 'chats'; }

        $cols = ['id','message','sent_at'];
        if (Schema::hasColumn($chatTable,'sender'))       $cols[] = 'sender';
        if (Schema::hasColumn($chatTable,'is_high_risk')) $cols[] = 'is_high_risk';
        if (Schema::hasColumn($chatTable,'risk_level'))   $cols[] = 'risk_level';

        // Only user messages, oldest→newest
        $q = DB::table($chatTable)
            ->where('chat_session_id', $row->id)
            ->orderBy('sent_at')
            ->orderBy('id');

        if (Schema::hasColumn($chatTable,'sender')) {
            $q->where('sender','user');
        }

        $msgs = $q->get($cols);

        $out = [];
        foreach ($msgs as $m) {
            $plain = $this->tryDecryptOrPlain($m->message);
            if (!$plain) continue;

            // Respect explicit DB flags too
            $flagged = (isset($m->is_high_risk) && (int)$m->is_high_risk === 1)
                    || (isset($m->risk_level)   && strtolower((string)$m->risk_level) === 'high');

            if (RiskHeuristics::containsHighRisk($plain) || $flagged) {
                $out[] = [
                    'id'     => $m->id,
                    'sender' => isset($m->sender) ? (string)$m->sender : 'user',
                    'at'     => $m->sent_at ? Carbon::parse($m->sent_at)->format('F d, Y • h:i A') : null,
                    'text'   => $plain,
                ];
            }
        }

        return response()->json(['ok'=>true,'count'=>count($out),'items'=>$out,'session'=>(int)$row->id]);
    }

    // Collect every high-risk line for a session (used in show())
    private function collectAllHighRiskItems(object $sessionRow): array
    {
        // Resolve chats table
        try { $chatTable = app(\App\Models\Chat::class)->getTable() ?: 'chats'; }
        catch (\Throwable) { $chatTable = 'chats'; }

        $cols = ['id','message','sent_at'];
        if (\Schema::hasColumn($chatTable,'sender'))       $cols[] = 'sender';
        if (\Schema::hasColumn($chatTable,'is_high_risk')) $cols[] = 'is_high_risk';
        if (\Schema::hasColumn($chatTable,'risk_level'))   $cols[] = 'risk_level';

        $msgs = \DB::table($chatTable)
            ->where('chat_session_id', $sessionRow->id)
            ->when(\Schema::hasColumn($chatTable,'sender'), fn($q)=>$q->where('sender','user'))
            ->orderBy('sent_at')          // oldest → newest
            ->orderBy('id')
            ->get($cols);

        $out = [];
        foreach ($msgs as $m) {
            $plain = $this->tryDecryptOrPlain($m->message);
            if (!$plain) continue;

            // Respect explicit DB flags too
            $flagged = (isset($m->is_high_risk) && (int)$m->is_high_risk === 1)
                || (isset($m->risk_level) && strtolower((string)$m->risk_level) === 'high');

            if (RiskHeuristics::containsHighRisk($plain) || $flagged) {
                $out[] = [
                    'id'     => $m->id,
                    'sender' => isset($m->sender) ? (string)$m->sender : 'user',
                    'at'     => $m->sent_at ? \Carbon\Carbon::parse($m->sent_at)->format('F d, Y • h:i A') : null,
                    'text'   => $plain,
                ];
            }
        }
        return $out;
    }

    public function setRisk(int $id, Request $request): JsonResponse
    {
        // Validate
        $request->validate([
            'risk_level' => ['required','in:low,moderate,high'],
            'risk_score' => ['nullable','integer','between:0,100'],
            'risk_note'  => ['nullable','string','max:2000'], // provided when downgrading from high
        ]);

        // Find session (via repository, includes user)
        $session = $this->sessions->findById($id, ['user']);
        if (!$session) return response()->json(['message' => 'Session not found.'], 404);

        // Determine columns + table safely
        $table = $this->sessionsTable() ?? 'chat_sessions';

        // Read current
        $currentLevel = strtolower((string)($session->risk_level ?? $session->risk ?? ''));
        $newLevel     = (string) $request->input('risk_level');
        $newScore     = (int) ($request->input('risk_score') ?? 0);
        $note         = trim((string) $request->input('risk_note', ''));

        // If demoting from high -> (low|moderate), require a short note
        $isDemotion = in_array($currentLevel, ['high','high-risk','high_risk'], true)
                && in_array($newLevel, ['moderate','low'], true);

        if ($isDemotion && $note === '') {
            return response()->json(['message' => 'Please provide a short reason for the downgrade.'], 422);
        }

        // Persist
        \DB::transaction(function () use ($table, $id, $newLevel, $newScore, $currentLevel, $note) {
            // Update session risk columns if they exist
            $updates = ['updated_at' => now()];
            if (\Schema::hasColumn($table, 'risk_level')) $updates['risk_level'] = $newLevel;
            if (\Schema::hasColumn($table, 'risk'))       $updates['risk']       = $newLevel;
            if (\Schema::hasColumn($table, 'risk_score')) $updates['risk_score'] = $newScore;
            \DB::table($table)->where('id', $id)->update($updates);

            // Log the change (always)
            ChatbotSessionRiskLog::create([
                'chatbot_session_id' => $id,
                'admin_id'           => Auth::id(),
                'from_level'         => $currentLevel ?: null,
                'to_level'           => $newLevel,
                'to_score'           => $newScore,
                'note'               => $note ?: null,
            ]);
        });

        return response()->json(['ok' => true]);
    }

    private function tryDecryptOrPlain(?string $v): ?string
    {
        if ($v === null) return null;
        try { return Crypt::decryptString($v); } catch (\Throwable) { return $v; }
    }

    // shared predicate (kept for compatibility)
    private function containsHighRisk(string $text): bool
    {
        return RiskHeuristics::containsHighRisk($text);
    }
}
