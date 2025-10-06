<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

class ChatController extends Controller
{
    /* =========================================================================
     | Helpers: language, risk, appointment, crisis
     * =========================================================================*/
    private function confirmedAfterOffer(string $text, int $sessionId): bool
    {
        $t = mb_strtolower($text);
        if (!preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|sige|go|go ahead|proceed|please|yes please)\b/u', $t)) {
            return false;
        }
        $lastBot = \App\Models\Chat::where('chat_session_id', $sessionId)->where('sender','bot')->latest('sent_at')->first();
        if (!$lastBot) return false;
        try { $last = \Illuminate\Support\Facades\Crypt::decryptString($lastBot->message); }
        catch (\Throwable $e) { $last = (string) $lastBot->message; }
        return (bool) preg_match('/\b(counsel(?:or|ling)|appointment|schedule|book|connect)\b/i', $last);
    }

    private function inferLanguage(string $t): string
    {
        $x = mb_strtolower($t);
        $cebWords = [
            'nag','ko','kaayo','unsa','karon','gani','balaka','kulba','kapoy','nalipay',
            'gusto','pa-schedule','magpa-iskedyul','pwede','palihug','bug-at','dili',
            'maayong','kumusta','mohilak','hikog','paglaum','jud','lagi','bitaw'
        ];
        $hits = 0;
        foreach ($cebWords as $w) {
            if (str_contains($x, $w)) $hits++;
        }
        return $hits >= 2 ? 'ceb' : 'en';
    }

    private function pickLanguageVariant(string $reply, string $lang): string
    {
        // Avoid splitting https:// — only split " / "
        $parts = preg_split('/\s+\/\s+/u', $reply, 2);
        return (count($parts) === 2) ? (($lang === 'ceb') ? trim($parts[1]) : trim($parts[0])) : $reply;
    }

    private function evaluateRiskLevel(string $text): string
    {
        $t = mb_strtolower($text);
        $t = preg_replace('/\s+/u', ' ', $t ?? '');

        // HIGH
        $high = [
            '\bi\s*(?:wanna|want(?:\s*to)?|plan|planning|intend|need|will|gonna)\s*(?:to\s*)?(?:die|kill myself|end (?:it|my life)|commit suicide|unalive|disappear|be gone)\b',
            '\b(?:kill myself|commit suicide|end it all|no reason to live|life is pointless)\b',
            '\bi\s*(?:wish|want)\s*(?:i\s*)?(?:were|was)\s*dead\b',
            '\bi\s*(?:can\'?t|cannot)\s*go on\b',
            '\b(?:jump off|overdose|poison myself|hang myself)\b',
            '\b(?:self[- ]harm|cut(?:ting)? myself)\b',
            '\bgusto na ko mamatay\b',
            '\bmaghikog\b',
            '\bwala na koy paglaum\b',
            '\bgusto ko mawala\b',
            '\btapuson na nako tanan\b',
        ];
        foreach ($high as $p) {
            if (preg_match('/' . $p . '/iu', $t)) return 'high';
        }

        // Co-occurrence heuristic
        $acts   = ['suicide','die','unalive','kill myself','end my life','end it','jump','overdose','poison','cut','disappear','be gone','mamatay','hikog','wala na koy paglaum','mawala'];
        $intent = ['wanna','want','plan','planning','thinking','feel like','i should','i will','i might','really want','gonna','gusto','buot','tingali','murag'];
        foreach ($acts as $a) foreach ($intent as $b) {
            if (str_contains($t, $a) && str_contains($t, $b)) return 'high';
        }

        // MODERATE
        $moderate = [
            '\bi\s*(?:hate|loath|despise)\s*myself\b',
            '\b(?:i (?:want|wish) (?:to )?disappear|i (?:don\'?t|do not) want to exist|i wish i wasn\'?t here|i wish i never existed)\b',
            '\b(?:i(?:\'m| am)? (?:not ?ok(?:ay)?|empty|worthless|a burden|beyond help))\b',
            '\b(?:give up on life|i don\'?t want to live|i feel like dying)\b',
            '\b(?:depress(?:ed|ing)?|anxious|panic|overwhelmed|burnout|stressed)\b',
            '\bnagkabalaka ko\b',
            '\bkulba\b',
            '\bkapoy kaayo\b',
            '\bbug-at kaayo\b',
            '\bna[- ]?overwhelm\b',
            '\bdili ko okay\b',
            '\bwala koy gana\b',
        ];
        foreach ($moderate as $p) {
            if (preg_match('/' . $p . '/iu', $t)) return 'moderate';
        }

        return 'low';
    }

    private function buildRasaMetadata(int $sessionId, string $lang, string $risk): array
    {
        return [
            'lumichat' => [
                'session_id' => $sessionId,
                'lang'       => $lang,
                'risk'       => $risk,
                'app'        => 'lumichat-web',
            ]
        ];
    }

    private function crisisMessageWithLink(): string
    {
        $c   = config('services.crisis');
        $emg = e($c['emergency_number'] ?? '911');
        $hn  = e($c['hotline_name'] ?? 'Hopeline PH (24/7)');
        $hp  = e($c['hotline_phone'] ?? '0917-558-4673 / (02) 804-4673');
        $ht  = e($c['hotline_text'] ?? 'Text 0917-558-4673');
        $url = e($c['hotline_url'] ?? 'https://www.facebook.com/HopelinePH/');

        return <<<HTML
<div class="space-y-2 leading-relaxed">
  <p class="font-semibold">We’re here to help. / Ania mi para motabang.</p>
  <ul class="list-disc pl-5 text-sm">
    <li>If you’re in immediate danger, call <strong>{$emg}</strong>. / Kung emerhensya, tawag sa <strong>{$emg}</strong>.</li>
    <li>24/7 support: <strong>{$hn}</strong> — call <strong>{$hp}</strong>, {$ht}, or visit
      <a href="{$url}" target="_blank" rel="noopener" class="underline">{$url}</a>.
    </li>
  </ul>
  <p class="text-sm">You can also book a time with a school counselor: / Pwede pud ka magpa-book sa counselor:</p>
  <div class="pt-1">{APPOINTMENT_LINK}</div>
</div>
HTML;
    }

    private function wantsAppointment(string $text): bool
    {
        $t = mb_strtolower($text);

        // Strong signals: action + counselor/therapy/advisor (your originals)
        $strong = [
            '/\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b[\s\S]{0,80}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b[\s\S]{0,80}\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b/iu',
            '/\b(i\s+want|i\'?d\s+like|can\s+i|please)\b[\s\S]{0,40}\b(schedule|book|appointment)\b[\s\S]{0,40}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\bsee\s+(?:a\s+)?counselor\b/iu',
            '/\b(pa-?schedule|magpa-?iskedyul|mo-?book)\b[\s\S]{0,80}\b(counsel(?:or|ing)?|konselor|tambag|makig[- ]?istorya)\b/iu',
        ];
        foreach ($strong as $r) if (preg_match($r, $t)) return true;

        // Soft signals: user says they want an appointment/schedule/booking even without the word "counselor"
        if (preg_match('/\b(appoint(?:ment)?|schedule|book(?:ing)?|reserve|set\s*(?:an?|up)?\s*appointment)\b/iu', $t)) {
            return true;
        }

        // Conversational phrasing: “can I talk to someone”, “speak with someone”
        if (preg_match('/\b(talk to|speak with|see|meet)\b[\s\S]{0,40}\b(someone|somebody|counsel(?:or)?|advisor|therap(?:ist)?)\b/iu', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Build the full Rasa webhook URL from env/config safely.
     * (No logic change to behavior, just centralized construction.)
     */
    private function rasaWebhookUrl(): string
    {
        // If services.rasa.url or RASA_URL is provided, prefer it.
        $direct = (string) config('services.rasa.url', env('RASA_URL', ''));
        if (!empty($direct)) {
            return $direct;
        }

        $base  = rtrim((string) env('RASA_BASE_URL', 'http://127.0.0.1:5005'), '/');
        $path  = '/' . ltrim((string) env('RASA_WEBHOOK_PATH', '/webhooks/rest/webhook'), '/');
        $token = trim((string) env('RASA_TOKEN', ''), "\"'"); // strip accidental quotes

        // Append token once
        if ($token !== '') {
            $sep = (str_contains($path, '?') ? '&' : '?');
            return $base . $path . $sep . 'token=' . urlencode($token);
        }
        return $base . $path;
    }

    /* =========================================================================
     | UI pages
     * =========================================================================*/

    public function index(Request $request)
    {
        $userId = Auth::id();

        // If we were asked to start fresh (via New Chat), don't auto-attach latest.
        $startFresh = (bool) session('start_fresh', false);
        if ($startFresh) {
            session()->forget('start_fresh');
        }

        $activeId = session('chat_session_id');

        if (!$activeId && !$startFresh) {
            $latest = ChatSession::where('user_id', $userId)->latest('updated_at')->first();
            if ($latest) {
                session(['chat_session_id' => $latest->id]);
                $activeId = $latest->id;
            }
        }

        $showGreeting = !$activeId;

        $chats = collect();
        if ($activeId) {
            $chats = Chat::where('user_id', $userId)
                ->where('chat_session_id', $activeId)
                ->orderBy('sent_at')
                ->get()
                ->map(function ($chat) {
                    try { $chat->message = \Illuminate\Support\Facades\Crypt::decryptString($chat->message); }
                    catch (\Throwable $e) { $chat->message = '[Encrypted]'; }
                    return $chat;
                });
        }

        return view('chat', compact('chats', 'showGreeting'));
    }

    public function newChat(Request $request)
    {
        session()->forget('chat_session_id');
        session(['start_fresh' => true]);
        return redirect()->route('chat.index');
    }


    /* =========================================================================
     | Store a user message, call Rasa, risk/booking/crisis logic
     * =========================================================================*/
    public function store(Request $request)
    {
        // 1) Validation (+ idempotency)
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000', function ($attr, $val, $fail) {
                $s = is_string($val) ? preg_replace('/\s+/u', ' ', $val) : '';
                $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s ?? '');
                if (trim($s) === '') return $fail('Message cannot be empty.');
                if ($s !== strip_tags($s)) return $fail('HTML is not allowed in messages.');
            }],
            '_idem'  => ['required','uuid','unique:chats,idempotency_key'],
        ]);

        $rawInput = (string) $validated['message'];
        $text = preg_replace('/\s+/u', ' ', $rawInput);
        $text = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $text ?? '');
        $text = trim($text);

        $userId    = Auth::id();
        $sessionId = session('chat_session_id');

        // 2) Session ownership check
        $session = null;
        if ($sessionId) {
            $session = ChatSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->first();
        }
        if (!$session) {
            $session = ChatSession::create([
                'user_id'       => $userId,
                'topic_summary' => 'Starting conversation...',
                'is_anonymous'  => 0,
                'risk_level'    => 'low',
            ]);
            session(['chat_session_id' => $session->id]);
            $this->logActivity('chat_session_created', 'New chat session auto-created', $session->id, [
                'is_anonymous' => false,
                'reused'       => false,
            ]);
        }
        $sessionId = $session->id;

        // 3) Language + risk
        $lang    = $this->inferLanguage($text);
        $msgRisk = $this->evaluateRiskLevel($text);

        // 4) Save user message (encrypted + idempotency)
        $userMsg = Chat::create([
            'user_id'         => $userId,
            'chat_session_id' => $sessionId,
            'sender'          => 'user',
            'message'         => Crypt::encryptString($text),
            'sent_at'         => now(),
            'idempotency_key' => $validated['_idem'],
        ]);

        $count = Chat::where('chat_session_id', $sessionId)->where('sender', 'user')->count();
        if ($count === 1) {
            preg_match('/\b(sad|depress|help|anxious|angry|lonely|stress|tired|happy|excited|not okay|nagool|kapoy|kulba|nalipay)\b/i', $text, $m);
            $summary = $m[0] ?? Str::limit($text, 40, '…');
            $session->update(['topic_summary' => ucfirst($summary)]);
        }

        // 5) Call Rasa
        // FIX: central & safe URL + timeout/SSL from env (no logic change)
        $rasaUrl  = $this->rasaWebhookUrl();
        $metadata = $this->buildRasaMetadata($sessionId, $lang, $msgRisk);
        $botReplies = [];

        $timeout = (int) config('services.rasa.timeout', (int) env('RASA_TIMEOUT', 8));
        $verify  = filter_var(env('RASA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

        $r = null; // prevent "undefined $r" in logger below
        try {
            $r = Http::timeout($timeout)
                ->withOptions(['verify' => $verify])
                ->withHeaders(['Accept' => 'application/json'])
                ->post($rasaUrl, [
                    'sender'   => 'u_' . $userId . '_s_' . $sessionId,
                    'message'  => $text,
                    'metadata' => $metadata,
                ]);

            if ($r->ok()) {
                $payload = $r->json() ?? [];
                foreach ($payload as $piece) {
                    if (!empty($piece['text'])) $botReplies[] = $piece['text'];
                }
            }
        } catch (\Throwable $e) {
            $botReplies = [
                "It’s okay to feel that way. I’m here to listen. Would you like to share more? / Sige ra na, ania ko maminaw. Gusto nimo isulti pa ug dugang?"
            ];
        }

        if (empty($botReplies)) {
            $botReplies = [
                "I’m here to support you. Would you like to share more about how you’re feeling? / Ania ko para motabang. Gusto nimo isulti pa ug dugang kung unsa imong gibati?"
            ];
            $this->logActivity('rasa_no_reply', 'Rasa returned no replies', $sessionId, [
                'response' => is_object($r) ? $r->body() : null,
            ]);
        } else {
            if (count($botReplies) > 3) {
                $this->logActivity('rasa_multiple_replies', count($botReplies) . ' replies from Rasa', $sessionId, [
                    'sample'   => array_slice($botReplies, 0, 3),
                    'full'     => $botReplies,
                ]);
            }
        }

        // 6) Risk elevation + crisis prompt
        $current = $session->risk_level ?: 'low';
        $order   = ['low' => 0, 'moderate' => 1, 'high' => 2];
        $new     = ($order[$msgRisk] > $order[$current]) ? $msgRisk : $current;
        if ($new !== $current) $session->update(['risk_level' => $new]);

        $this->logActivity('risk_detected', "Risk level: {$msgRisk}", $sessionId, [
            'risk_level'      => $msgRisk,
            'message_preview' => Str::limit($text, 120),
        ]);

        $crisisAlreadyShown = session('crisis_prompted_for_session_' . $sessionId, false);
        if (!$crisisAlreadyShown && $msgRisk === 'high') {
            session(['crisis_prompted_for_session_' . $sessionId => true]);
            $this->logActivity('crisis_prompt', 'Crisis resources displayed', $sessionId, null);
            array_unshift($botReplies, $this->crisisMessageWithLink());
        }

        // 6.5) Inject appointment CTA when user asked & Rasa didn’t add it
        $askedForAppt = $this->wantsAppointment($text) || $this->confirmedAfterOffer($text, $sessionId);
        $hasApptPlaceholder = false;
        foreach ($botReplies as $rpl) {
            if (is_string($rpl) && str_contains($rpl, '{APPOINTMENT_LINK}')) { $hasApptPlaceholder = true; break; }
        }
        if ($askedForAppt && !$hasApptPlaceholder) {
            $ctaReply = "You can book a time with a school counselor here: {APPOINTMENT_LINK} / Pwede ka magpa-book sa school counselor dinhi: {APPOINTMENT_LINK}";
            if ($msgRisk === 'high') {
                $botReplies[] = $ctaReply;     // after crisis info
            } else {
                array_unshift($botReplies, $ctaReply);
            }
            $this->logActivity('appointment_detected', 'User asked to schedule; CTA injected', $sessionId, [
                'preview' => Str::limit($text, 120),
            ]);
        }

        // 7) Build appointment link safely + render replies (pick language FIRST)
        $link = Route::has('features.enable_appointment')
            ? URL::signedRoute('features.enable_appointment')
            : (Route::has('appointment.index')
                ? route('appointment.index')
                : url('/appointment'));

        $ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

        $botPayload = [];
        foreach ($botReplies as $reply) {
            $reply = $this->pickLanguageVariant($reply, $lang);
            if (is_string($reply) && str_contains($reply, '{APPOINTMENT_LINK}')) {
                $reply = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $reply);
            }
            $bot = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => Crypt::encryptString($reply),
                'sent_at'         => now(),
            ]);

            $botPayload[] = [
                'text'       => $reply,
                'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('H:i'),
                'sent_at'    => $bot->sent_at->toIso8601String(),
            ];
        }

        // FIX: remove duplicate block; compute once
        $tz       = config('app.timezone');
        $nowHuman = now()->timezone($tz)->format('H:i');

        $rawReplies = is_array($botPayload) ? $botPayload : [$botPayload];
        $replies    = array_values(array_filter(array_map(function ($r) {
            if (is_array($r)) {
                if (isset($r['text']))      return trim((string) $r['text']);
                if (isset($r['message']))   return trim((string) $r['message']);
                if (isset($r['bot_reply'])) return trim((string) $r['bot_reply']);
                return trim((string) json_encode($r));
            }
            return trim((string) $r);
        }, $rawReplies), fn ($s) => $s !== ''));

        return response()->json([
            'user_message' => [
                'text'       => $text,
                'time_human' => $userMsg->sent_at->timezone($tz)->format('H:i'),
                'sent_at'    => $userMsg->sent_at->toIso8601String(),
            ],
            'bot_reply'  => $replies,
            'time_human' => $nowHuman,
        ]);
    }

    /* =========================================================================
     | History utilities
     * =========================================================================*/

    public function history(Request $request)
    {
        $q = trim($request->get('q', ''));

        $sessions = ChatSession::with(['chats' => function ($query) {
                $query->latest('sent_at')->limit(1);
            }])
            ->where('user_id', Auth::id())
            ->when($q !== '', fn($query) => $query->where('topic_summary', 'like', "%{$q}%"))
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        foreach ($sessions as $session) {
            foreach ($session->chats as $chat) {
                try {
                    $chat->message = Crypt::decryptString($chat->message);
                } catch (\Throwable $e) {
                    $chat->message = '[Unreadable]';
                }
            }
        }

        return view('chat-history', compact('sessions', 'q'));
    }

    public function viewSession($id)
    {
        $session = ChatSession::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $messages = Chat::where('chat_session_id', $id)
            ->where('user_id', Auth::id())
            ->orderBy('sent_at')
            ->get()
            ->map(function ($c) {
                try {
                    $c->message = Crypt::decryptString($c->message);
                } catch (\Throwable $e) {
                    $c->message = '[Unreadable]';
                }
                return $c;
            });

        return view('chat-view', compact('session', 'messages'));
    }

    public function deleteSession($id)
    {
        ChatSession::where('id', $id)->where('user_id', Auth::id())->delete();

        if ((int) session('chat_session_id') === (int) $id) {
            session()->forget('chat_session_id');
        }

        return redirect()->route('chat.history')->with('status', 'Session deleted');
    }

    public function bulkDelete(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string)$request->input('ids', ''))));
        if (!empty($ids)) {
            ChatSession::where('user_id', Auth::id())
                ->whereIn('id', $ids)
                ->delete();

            if (in_array((int) session('chat_session_id'), $ids, true)) {
                session()->forget('chat_session_id');
            }
        }
        return redirect()->route('chat.history')->with('status', 'Selected sessions deleted');
    }

    public function activate($id)
    {
        $session = ChatSession::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        session(['chat_session_id' => $session->id]);
        $session->touch();
        return redirect()->route('chat.index')->with('status', 'session-activated');
    }

    /* =========================================================================
     | Activity logger
     * =========================================================================*/
    private function logActivity(string $event, string $description, int $sessionId, ?array $meta = null): void
    {
        try {
            ActivityLog::create([
                'event'        => $event,
                'description'  => $description,
                'actor_id'     => Auth::id(),
                'subject_type' => ChatSession::class,
                'subject_id'   => $sessionId,
                'meta'         => $meta,
            ]);
        } catch (\Throwable $e) {
            // best-effort only
        }
    }
}
