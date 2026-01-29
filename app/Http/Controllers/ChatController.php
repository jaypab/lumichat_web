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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use App\Support\RiskHeuristics;

// Import our new service classes
use App\Services\ChatNLUService;
use App\Services\ChatResponseService;
use App\Services\ChatSessionService;

class ChatController extends Controller
{
    /**
     * Constructor with dependency injection for service classes
     */
    public function __construct(
        private ChatNLUService $nluService,
        private ChatResponseService $responseService,
        private ChatSessionService $sessionService
    ) {}

    /* =========================================================================
     | Helpers: language, risk, appointment, crisis
     * =========================================================================*/
    private function confirmedAfterOffer(string $text, int $sessionId): bool
    {
        $t = mb_strtolower($text);
        if (!preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|go|go ahead|proceed|please|yes please)\b/u', $t)) {
            return false;
        }
        $lastBot = \App\Models\Chat::where('chat_session_id', $sessionId)
            ->where('sender', 'bot')
            ->latest('sent_at')
            ->first();
        if (!$lastBot) return false;
        try {
            $last = \Illuminate\Support\Facades\Crypt::decryptString($lastBot->message);
        } catch (\Throwable $e) {
            $last = (string) $lastBot->message;
        }
        return (bool) preg_match('/\b(counsel(?:or|ling)|appointment|schedule|book|connect)\b/i', $last);
    }

    private function preferredName(): string
    {
        $u = auth()->user();
        if (!$u) return 'there';

        // Try common fields you might have; fall back to "name" or email local part
        $candidates = [
            $u->preferred_name ?? null,
            $u->first_name     ?? null,
            $u->given_name     ?? null,
            $u->name           ?? null,
        ];
        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c !== '') return $c;
        }
        // email local-part fallback
        $email = (string) ($u->email ?? '');
        if (str_contains($email, '@')) return strtok($email, '@');
        return 'there';
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

    /**
     * Build the full Rasa webhook URL from env/config safely.
     */
    private function rasaWebhookUrl(): string
    {
        $direct = (string) config('services.rasa.url', env('RASA_URL', ''));
        if (!empty($direct)) {
            return $direct;
        }

        $base  = rtrim((string) env('RASA_BASE_URL', 'http://127.0.0.1:5005'), '/');
        $path  = '/' . ltrim((string) env('RASA_WEBHOOK_PATH', '/webhooks/rest/webhook'), '/');
        $token = trim((string) env('RASA_TOKEN', ''), "\"'"); // strip accidental quotes

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
    $userId  = Auth::id();

    // 👇 Only use whatever is explicitly in the session.
    // Do NOT auto-attach the latest session anymore.
    $activeId = session('chat_session_id');

    // If there's no active session, we show the greeting/blank state.
    $showGreeting = !$activeId;

    $chats = collect();
    $greeting = null;
    $isFirstLoad = false;
    
    if ($activeId) {
        // ✅ Load all messages including greeting from database
        $chats = Chat::where('chat_session_id', $activeId)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get()
            ->map(function ($chat) {
                try {
                    $chat->message = Crypt::decryptString($chat->message);
                } catch (\Throwable $e) {
                    $chat->message = '[Encrypted]';
                }
                return $chat;
            });
    } elseif ($showGreeting && session('start_fresh')) {
        // ✅ Create a fresh session with greeting message
        $isFirstLoad = true;  // Flag to trigger animation
        session()->forget('start_fresh');
        
        $newSession = ChatSession::create([
            'user_id' => $userId,
            'title' => 'Starting conversation...',
            'risk_level' => 'low',
        ]);
        
        session(['chat_session_id' => $newSession->id]);
        $activeId = $newSession->id;
        
        // Get user's first name for greeting
        $first = $this->sessionService->preferredName();
        
        // Create personalized greeting
        $greetingText = "Hi {$first}! 💜 I'm Lumi, and I'm really glad you're here. This is a safe space for whatever you're feeling — whether it's big or small, messy or clear, I'm here to listen. What's on your mind today?";
        
        // Save greeting message to database for persistence
        Chat::create([
            'chat_session_id' => $newSession->id,
            'user_id' => $userId,
            'sender' => 'bot',
            'message' => Crypt::encryptString($greetingText),
            'sent_at' => now(),
        ]);
        
        // ✅ Pass greeting to JavaScript for animated display on first load only
        // Keep it OUT of $chats during animation to avoid double display
        // On subsequent page loads, activeId will be set and greeting will load from DB normally
        $greeting = $greetingText;
    }

    // you don't actually use $thread in the blade, so either remove or keep if needed
    $thread  = null;
    $isLocked = false;

    return view('chat', compact('chats', 'showGreeting', 'thread', 'isLocked', 'greeting'));
}


    public function newChat(Request $request)
    {
        // Clear any active session and mark that we want a fresh start.
        session()->forget('chat_session_id');
        session(['start_fresh' => true]);

        return redirect()->route('chat.index');
    }

    /* =========================================================================
     | Store a user message, call Rasa, risk/booking/crisis logic
     * =========================================================================*/
public function store(Request $request)
{
    // ===== 0) Basic name pieces =====
    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

    // ===== 1) Validate input (+ idempotency) =====
    $request->validate([
        'message'      => ['required', 'string', 'max:2000', function ($attr, $val, $fail) {
            $s = is_string($val) ? preg_replace('/\s+/u', ' ', $val) : '';
            $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s ?? '');
            if (trim($s) === '') return $fail('Message cannot be empty.');
            if ($s !== strip_tags($s)) return $fail('HTML is not allowed in messages.');
        }],
        'display_text' => ['nullable', 'string', 'max:2000'],
    ]);

    // Normalize text / display_text (balanced parens)
    $rawInput   = (string) $request->input('message', '');
    $cleanInput = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawInput);
    $text       = trim(preg_replace('/\s+/u', ' ', $cleanInput));  // payload sent to Rasa

    $rawDisplay   = (string) $request->input('display_text', '');
    $cleanDisplay = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawDisplay);
    $display      = trim(preg_replace('/\s+/u', ' ', $cleanDisplay)); // nice label for UI

    // ✅ This is what we will SAVE + show in history
    $storedText   = $display !== '' ? $display : $text;

    // Prefer “human” text for heuristics
    $analysisText = $display !== '' ? $display : $text;

    // Idempotency
    $idem = (string) $request->input('_idem', '');
    if (!\Illuminate\Support\Str::isUuid($idem)) {
        $idem = (string) \Illuminate\Support\Str::uuid();
    }

    $userId    = \Illuminate\Support\Facades\Auth::id();
    $sessionId = (int) session('chat_session_id');

    // ===== 2) Ensure session exists =====
    $session     = null;
    $justCreated = false;

    if ($sessionId) {
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    if (!$session) {
        // No active session yet → create a brand new one
        $session = ChatSession::create([
            'user_id'       => $userId,
            'topic_summary' => 'Starting conversation...',
            'is_anonymous'  => 0,
            'risk_level'    => 'low',
        ]);
        session(['chat_session_id' => $session->id]);
        $justCreated = true;
    }

    // Once we actually have a real session, we are no longer in "fresh" waiting mode.
    session()->forget('start_fresh');

    $sessionId = (int) $session->id;

    // ===== 3) ENGLISH-ONLY ANALYSIS (typo-tolerant) =====
    $norm       = $this->nluNormalize($analysisText);
    $lang       = 'en';
    $intents    = $this->classifyIntents($norm);
    $flags      = $intents['flags'];
    $scores     = $intents['score'];
    $labels     = $this->labelEmotions($norm);
    $riskStruct = $this->assessRisk($norm);
    $msgRisk    = $riskStruct['level'] ?? 'low';
    $selfThreat = ($msgRisk === 'high');

    // 🔁 Track how many MODERATE-risk messages happened in this session
    $modKey        = 'moderate_hits_for_session_'.$sessionId;
    $moderateHits  = (int) session($modKey, 0);
    if ($msgRisk === 'moderate') {
        $moderateHits++;
        session([$modKey => $moderateHits]);
    }

    // Auto-coping trigger on every 5th moderate hit (5, 10, 15, …)
    $autoCopingTrigger = ($msgRisk === 'moderate'
        && $moderateHits > 0
        && $moderateHits % 5 === 0);


    // Detect if the message is basically not understandable (gibberish, symbols, etc.)
    $unreadable = $this->isUnreadableInput($norm);

    // Detect clearly non–mental-health topics,
    // but ONLY if the input is *not* unreadable.
    $nonMental = !$unreadable && $this->isNonMentalTopic($norm, $labels, $riskStruct, $flags);

    // ===== 3.a) Save user message (idempotent) =====
    try {
        $userMsg = Chat::firstOrCreate(
            ['idempotency_key' => $idem],
            [
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'user',
                'message'         => \Illuminate\Support\Facades\Crypt::encryptString($storedText),
                'sent_at'         => now(),
            ]
        );
    } catch (\Illuminate\Database\QueryException $e) {
        $userMsg = Chat::where('idempotency_key', $idem)->first();
        if (!$userMsg) throw $e;
    }

        // ===== 3.b) Topic summary / emotion counts (only for mental-health content) =====
    if (!$nonMental) {
        // how many user messages are in this session so far
        $sessionUserMsgCount = Chat::where('chat_session_id', $sessionId)
            ->where('sender', 'user')
            ->count();

        // build a ChatGPT-style title from the latest message + heuristics
        $newTitle = $this->buildSessionTitle(
            $norm,
            $analysisText,
            $labels,
            $intents['flags'] ?? [],
            $riskStruct,
            $sessionUserMsgCount,
            $session->topic_summary ?? null,
        );

        if ($newTitle !== '' && $newTitle !== $session->topic_summary) {
            $session->topic_summary = $newTitle;
            $session->save();
        }

        // emotion counters (unchanged logic, just kept under same if)
        try {
            if (!empty($labels)) {
                $current = $this->emotionsAsCounts($session->emotions ?? []);
                $updated = $this->incrementEmotionCounts($current, $labels);
                if ($updated !== $current) {
                    $session->emotions = $updated;
                    $session->save();
                }
            }
        } catch (\Throwable $e) {}
    }
    // ===== 3.c) BOT INTRO / CAPABILITIES ANSWER (short-circuit) =====
    if ($flags['asks_capabilities'] ?? false) {
        $introText = "Im lumichat your mental health companion what can i help with you today?";

        $bot = Chat::create([
            'user_id'         => $userId,
            'chat_session_id' => $sessionId,
            'sender'          => 'bot',
            'message'         => Crypt::encryptString($introText),
            'sent_at'         => now(),
        ]);

        return response()->json([
            'user_message' => [
                'text'       => $storedText,
                'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'    => now()->toIso8601String(),
            ],
            'bot_reply' => [[
                'id'         => $bot->id,
                'text'       => $introText,
                'buttons'    => [],
                'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'    => $bot->sent_at->toIso8601String(),
            ]],
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
        ]);
    }

// ===== 4) Decline-referral (SOFT RESET – no lock, keep chat open) =====
$declinedReferral = $this->declinedReferral($analysisText);
    if ($declinedReferral) {
        $ventKey = 'vent_turns_for_session_' . $sessionId;
        session([$ventKey => 0]);
        session()->forget('crisis_prompted_for_session_' . $sessionId);

        $closing1 = Chat::create([
            'user_id'         => $userId,
            'chat_session_id' => $sessionId,
            'sender'          => 'bot',
            'message'         => \Illuminate\Support\Facades\Crypt::encryptString(
                "Of course, {$first}. Thank you so much for trusting me with your feelings today — that takes real courage. 💜"
            ),
            'sent_at'         => now(),
        ]);

        $closing2 = Chat::create([
            'user_id'         => $userId,
            'chat_session_id' => $sessionId,
            'sender'          => 'bot',
            'message'         => \Illuminate\Support\Facades\Crypt::encryptString(
                "Anytime you need to talk, vent, or just have someone listen, I'm here. You never have to go through anything alone. Take care of yourself. 🤍"
            ),
            'sent_at'         => now(),
        ]);

        return response()->json([
            'user_message' => [
                'text'       => $storedText,
                'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'    => now()->toIso8601String(),
            ],
            'bot_reply' => [
                [
                    'id'         => $closing1->id,
                    'text'       => "No problem. Thank you for sharing with me today.",
                    'buttons'    => [],
                    'time_human' => $closing1->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                    'sent_at'    => $closing1->sent_at->toIso8601String(),
                ],
                [
                    'id'         => $closing2->id,
                    'text'       => "If you ever feel like talking again, you can just send another message and we’ll start from there.",
                    'buttons'    => [],
                    'time_human' => $closing2->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                    'sent_at'    => $closing2->sent_at->toIso8601String(),
                ],
            ],
            'locked'     => false,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
        ]);
    }

// ===== 4.b) NON-MENTAL TOPICS → CONTEXTUAL BOUNDARY + GENTLE BRIDGE =====
if ($nonMental && !$unreadable) {
    $replyText = $this->nonMentalReply(
        $sessionId,
        $first,
        $norm,
        $flags
    );

    $bot = Chat::create([
        'user_id'         => $userId,
        'chat_session_id' => $sessionId,
        'sender'          => 'bot',
        'message'         => Crypt::encryptString($replyText),
        'sent_at'         => now(),
    ]);

    return response()->json([
        'user_message' => [
            'text'       => $storedText,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => now()->toIso8601String(),
        ],
        'bot_reply' => [[
            'id'         => $bot->id,
            'text'       => $replyText,
            'buttons'    => [],
            'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot->sent_at->toIso8601String(),
        ]],
        'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}



    // ===== 5) Flow control (venting, coping, Rasa) – ONLY MENTAL CONTENT REACHES HERE =====
    $botReplies = [];
    $rasaUrl    = $this->rasaWebhookUrl();

    $ventKey   = 'vent_turns_for_session_'.$sessionId;
    $ventTurns = (int) session($ventKey, 0);

    $lastIntent = $this->lastBotIntent($sessionId);

    $askedForAppt =
        ($flags['wants_appointment'] ?? false)
        || (($flags['yes'] ?? false) && $lastIntent === 'offer_appointment')
        || $this->confirmedAfterOffer($analysisText, $sessionId);

    if ($selfThreat && !$askedForAppt) {
        $msgRisk = 'high';
    }

    $bypass           = $this->shouldBypassVenting_EN($norm, $msgRisk, $flags);
    $inEmotionalRange = ($msgRisk !== 'low') || !empty($labels);
    $inVentWindow     = $inEmotionalRange && ($ventTurns < 3) && !$bypass;

    // Session-level stats for smarter context
    $sessionEmotionCounts = $this->emotionsAsCounts($session->emotions ?? []);
    $sessionUserMsgCount  = Chat::where('chat_session_id', $sessionId)
        ->where('sender', 'user')
        ->count();

    // Coping throttle (anti-spam)
    $copingThrottleKey = 'coping_last_offer_'.$sessionId;
    $copingCooldownSec = 300;
    $nowEpoch          = time();
    $canOfferCoping    = !session()->has($copingThrottleKey)
        || ($nowEpoch - (int) session($copingThrottleKey, 0) >= $copingCooldownSec);

    // High-level message type (for Rasa + analytics)
    $messageType = 'other';

    if ($unreadable) {
        $messageType = 'unreadable';
    } elseif ($selfThreat || $msgRisk === 'high') {
        $messageType = 'crisis';
    } elseif ($askedForAppt) {
        $messageType = 'appointment_request';
    } elseif (($flags['wants_coping'] ?? false) || $autoCopingTrigger) {
        $messageType = 'coping_request';
    } elseif ($inVentWindow) {

        $messageType = 'emotional_vent';
    } elseif ($flags['is_question'] ?? false) {
        $messageType = 'question';
    }

        // Conversation trajectory + stage for smarter policies
    $trajectory = $this->analyzeTrajectory(
        $session,
        $msgRisk,
        $sessionEmotionCounts,
        $sessionUserMsgCount
    );

    $conversationStage = $this->detectConversationStage(
        $session,
        $msgRisk,
        $flags,
        $sessionUserMsgCount,
        $askedForAppt,
        $inVentWindow
    );

    // Build metadata (richer for Rasa policies)
    $metadata = [
        'lumichat' => [
            'session_id' => $sessionId,
            'lang'       => 'en',
            'risk'       => $msgRisk,
            'app'        => 'lumichat-web',
            'message'    => [
                'type'        => $messageType,
                'risk'        => $msgRisk,
                'self_threat' => $selfThreat,
                'labels'      => $labels,
                'flags'       => $flags,
                'scores'      => $scores,
            ],
            'session'    => [
                'stage'              => $conversationStage,      // opening / exploration / coping / crisis / closing / etc.
                'trajectory'         => $trajectory,             // rising_risk / stable_low / persistent_high / etc.
                'overall_risk'       => $session->risk_level ?: 'low',
                'topic_summary'      => $session->topic_summary,
                'emotion_counts'     => $sessionEmotionCounts,
                'user_message_count' => $sessionUserMsgCount,
                'vent_turns'         => $ventTurns,
                'in_vent_window'     => $inVentWindow,
                'non_mental_topic'   => false,                   // only mental messages reach here
                'asked_for_appt'     => $askedForAppt,
            ],
            'analysis'   => [
                'emotions'        => $labels,
                'intents'         => $flags,
                'scores'          => $scores,
                'last_bot_intent' => $lastIntent,
            ],
        ],
        'user' => [
            'id'    => auth()->id(),
            'name'  => $name,
            'first' => $first,
        ],
    ];



    // ===== 6) Branching + deciding if we call Rasa =====
    $callRasa    = false;
    $rasaMessage = $text;

    if ($flags['done'] ?? false) {
        $ventKey = 'vent_turns_for_session_'.$sessionId;
        session([$ventKey => 0]);

        $botReplies[] = [
            'text'    => "Thank you for sharing with me today, {USER_FIRST}. If you ever want to talk again or try more coping tools, you can just send another message and we’ll continue from there.",
            'buttons' => [],
        ];
        $botReplies[] = [
            'text'    => "And if at any point you’d like to talk to a school counselor in person, you can book an appointment here:",
            'buttons' => [
                ['title' => 'Book counselor', 'payload' => '{APPOINTMENT_LINK}'],
            ],
        ];
    } elseif ($unreadable) {
        $botReplies[] = [
            'text'    => "I’m not sure I understood that, {USER_FIRST}. Could you say it in a simpler or clearer way so I can support you better?",
            'buttons' => [],
        ];
    } elseif (($flags['refused_to_share'] ?? false)) {
        $supportLine = "It’s completely okay if you’re not ready to share right now, {USER_FIRST}. You’re not alone here, and there’s no pressure.";
        $ctaReply    = "If it would help to talk later, you’re always welcome to come back. You can also book time with a school counselor here: {APPOINTMENT_LINK}";

        $botReplies[] = ['text' => $supportLine, 'buttons' => []];
        $botReplies[] = [
            'text'    => $ctaReply,
            'buttons' => [
                ['title' => 'Book counselor', 'payload' => '{APPOINTMENT_LINK}'],
            ],
        ];
    } elseif ($inVentWindow && !$askedForAppt && $msgRisk !== 'high') {
        $stage = max(1, min(3, $ventTurns + 1));
        
        // ✅ Get conversation memory FIRST for context
        $memory = $this->sessionService->getMemory($sessionId);
        $previousTopic = $this->sessionService->getPrimaryTopic($memory);
        
        // ✅ Extract context from user input with memory context
        $topic = $this->nluService->extractTopic($norm, $previousTopic, $memory);
        $keywords = $this->nluService->extractKeyPhrases($norm);
        
        // ✅ Build memory-aware response (references previous turns)
        $replyText = $this->responseService->buildMemoryAwareResponse(
            $first,
            $norm,
            $labels,
            $topic,
            $keywords,
            $stage,
            $memory
        );
        
        // ✅ Update conversation memory AFTER generating response
        $this->sessionService->updateMemory(
            $sessionId,
            $topic,
            $keywords,
            $labels,
            $replyText  // Store the question we asked
        );
        
        $botReplies[] = ['text' => $replyText, 'buttons' => []];
        session([$ventKey => $ventTurns + 1]);
   } elseif (
    (
        ($flags['wants_coping'] ?? false)
        || (($flags['yes'] ?? false) && $lastIntent === 'offer_coping')
        || $autoCopingTrigger   // 👈 every 5th moderate hit
    )
    && $canOfferCoping
) {
    // we’re explicitly going into coping flow
    session([$copingThrottleKey => $nowEpoch]);
    $callRasa = true;
} else {
    $callRasa = true;
}

    // ===== 6.a) If needed, call Rasa now and append replies =====
    if ($callRasa) {
        $timeout = (int) config('services.rasa.timeout', (int) env('RASA_TIMEOUT', 8));
        $verify  = filter_var(env('RASA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

        try {
            $r = Http::timeout($timeout)
                ->withOptions(['verify' => $verify])
                ->withHeaders(['Accept' => 'application/json'])
                ->post($rasaUrl, [
                    'sender'   => 'u_' . $userId . '_s_' . $sessionId,
                    'message'  => $rasaMessage,
                    'metadata' => $metadata,
                ]);

            if ($r->ok()) {
                $payload = $r->json() ?? [];
                foreach ($payload as $piece) {
                    if (is_array($piece)) {
                        $txt = isset($piece['text']) ? (string) $piece['text'] : '';
                        $btn = (isset($piece['buttons']) && is_array($piece['buttons'])) ? $piece['buttons'] : [];
                        if ($txt !== '' || !empty($btn)) {
                            $botReplies[] = ['text' => $txt, 'buttons' => $btn];
                        }
                    } else {
                        $txt = trim((string) $piece);
                        if ($txt !== '') {
                            $botReplies[] = ['text' => $txt, 'buttons' => []];
                        }
                    }
                }
            }
       } catch (\Throwable $e) {
        $botReplies = [[
            'text' => $this->fallbackSupportLine(),
        ]];
    }

    if (empty($botReplies)) {
        $botReplies = [[
            'text' => $this->fallbackSupportLine(),
        ]];
    }
    }

    // ===== 7) Risk elevation logging (only for mental messages) =====
    $current = $session->risk_level ?: 'low';
    $order   = ['low' => 0, 'moderate' => 1, 'high' => 2];
    $new     = ($order[$msgRisk] > $order[$current]) ? $msgRisk : $current;
    if ($new !== $current) {
        $session->update(['risk_level' => $new]);
    }

    $this->logActivity('risk_detected', "Risk level: {$msgRisk}", $sessionId, [
        'risk_level'      => $msgRisk,
        'message_preview' => Str::limit($text, 120),
    ]);

    $crisisAlreadyShown = session('crisis_prompted_for_session_' . $sessionId, false);
    if (!$crisisAlreadyShown && $msgRisk === 'high') {
        session(['crisis_prompted_for_session_' . $sessionId => true]);
        $this->logActivity('crisis_prompt', 'Crisis context sent to Rasa', $sessionId, null);
    }

    // ===== 7.5) Appointment CTA injection (only if not already present) =====
    $askedForAppt = $askedForAppt || $this->confirmedAfterOffer($analysisText, $sessionId);
    $hasApptPlaceholder = false;
    foreach ($botReplies as $rpl) {
        if (is_array($rpl) && isset($rpl['text']) && is_string($rpl['text']) && str_contains($rpl['text'], '{APPOINTMENT_LINK}')) {
            $hasApptPlaceholder = true; break;
        }
    }

    // ===== 8) Build appointment link + save bot replies (with name personalization) =====
    $link = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
        ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
        : (\Illuminate\Support\Facades\Route::has('appointment.index') ? route('appointment.index') : url('/appointment'));

    $ctaHtml = '<a href="' . e($link) . '">Book an appointment</a>';

    $botPayload = [];
    foreach ($botReplies as $replyObj) {
        $replyText = (string) ($replyObj['text'] ?? '');
        $replyBtns = (isset($replyObj['buttons']) && is_array($replyObj['buttons'])) ? $replyObj['buttons'] : [];

        if (str_contains($replyText, '{APPOINTMENT_LINK}')) {
            $replyText = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $replyText);
                
        }

        $safeName  = e($name);
        $safeFirst = e($first);
        $replyText = str_replace(
            ['{USER_NAME}', '{USER_FIRST}', '{USER}', '{NAME}'],
            [$safeName,     $safeFirst,     $safeFirst, $safeName],
            $replyText
        );
        // 💜 Add emotional tone based on the student’s current message
            $replyText = $this->applyEmotionTone($replyText, $norm, $labels, $msgRisk);


        $normalizedBtns = [];
        foreach ($replyBtns as $b) {
            $title   = (string)($b['title'] ?? 'Open');
            $payload = $b['payload'] ?? null;
            $url     = $b['url'] ?? null;

            if (is_string($payload) && trim($payload) === '{APPOINTMENT_LINK}') {
                $normalizedBtns[] = ['title' => $title, 'url' => $link];
            } else {
                $one = ['title' => $title];
                if ($url)     $one['url'] = $url;
                if ($payload) $one['payload'] = $payload;
                $normalizedBtns[] = $one;
            }
        }

        $bot = Chat::create([
            'user_id'         => $userId,
            'chat_session_id' => $sessionId,
            'sender'          => 'bot',
            'message'         => Crypt::encryptString($replyText),
            'sent_at'         => now(),
        ]);

        $botPayload[] = [
            'id'         => $bot->id,
            'text'       => $replyText,
            'buttons'    => $normalizedBtns,
            'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot->sent_at->toIso8601String(),
        ];
    }

    // ===== Final JSON =====
    return response()->json([
        'user_message' => [
            'text'       => $storedText,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => now()->toIso8601String(),
        ],
        'bot_reply'  => $botPayload,
        'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}


    // Normalize any stored shape (null | list | map) into a simple label=>count map.
    private function emotionsAsCounts(null|array|string $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) return [];

        // If already a map of counts, normalize to int.
        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            $out = [];
            foreach ($value as $k => $v) {
                if (!is_string($k)) continue;
                $out[strtolower($k)] = max(0, (int) $v);
            }
            return $out;
        }

        // If it was a list (["sad","anxious"]), turn into counts.
        $out = [];
        foreach ($value as $label) {
            if (!is_string($label) || $label === '') continue;
            $k = strtolower($label);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    // Increment counts for the newly detected labels.
    private function incrementEmotionCounts(array $counts, array $labels): array
    {
        // If someone accidentally passes the full detectEmotions() struct, unwrap it.
        if (isset($labels['emotions']) && is_array($labels['emotions'])) {
            $labels = $labels['emotions'];
        }

        foreach ($labels as $label) {
            if (!is_string($label) || $label === '') continue;
            $k = strtolower($label);
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        return $counts;
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
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get()
            ->map(function ($c) {
                try { $c->message = Crypt::decryptString($c->message); }
                catch (\Throwable $e) { $c->message = '[Unreadable]'; }
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
        $session = ChatSession::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        session(['chat_session_id' => $session->id]);

        // User explicitly chose a session → no more "fresh" mode.
        session()->forget('start_fresh');

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

      private function empathicPrompt(string $first, array $labels, int $stage): string
    {
  $first = trim($first) !== '' ? $first : 'there';

  $top = array_values(array_unique(array_slice($labels, 0, 2)));
  $mirror = '';
  if (!empty($top)) {
      $mirror = ' — ' . implode(' & ', array_map(fn($x)=>strtolower($x), $top));
  }

  $primary = strtolower($top[0] ?? '');
  // Enhanced validation with warmer, more conversational tone
  $validate = [
      'sad'          => "I hear you, and that sounds really heavy to carry",
      'anxious'      => "That sounds so tense — anxiety can be exhausting",
      'stressed'     => "Wow, that's a lot to handle all at once",
      'tired'        => "You must be absolutely exhausted",
      'angry'        => "I can really feel the frustration in your words",
      'lonely'       => "Feeling alone can hurt in such a deep way",
      'hopeless'     => "When hope feels distant, everything can feel scary — I'm here",
      'not_ok'       => "It makes complete sense that you're not feeling okay right now",
      'disappointed' => "That disappointment must really sting",
      'hurt'         => "Being hurt like that can cut so deep",
      'confused'     => "It's totally okay not to have it all figured out",
      'bored'        => "Feeling stuck and restless can be so draining",
      'jealous'      => "Those feelings can be really hard to sit with",
      'ashamed'      => "Shame is such a heavy, painful feeling to carry",
      'guilty'       => "Guilt can take up so much space in your heart",
      'overwhelmed'  => "Everything piling up like this must feel like way too much",
      'overthinking' => "It sounds like your mind hasn't had any peace lately",
  ];
  $v = $validate[$primary] ?? "Thank you so much for trusting me with this";

  // Stage 1: opening up - warmer, more inviting questions
  if ($stage <= 1) {
      $options = [
          "{$v}{$mirror}, {USER_FIRST}. I'm really listening — what's been weighing heaviest on you lately?",
          "{$v}{$mirror}. You don't need to have it all figured out — just share whatever feels right. What's going on?",
          "I'm here, {USER_FIRST}{$mirror}. Take all the time you need — what's been on your heart recently?",
          "{$v}{$mirror}, {USER_FIRST}. This is a safe space for whatever you're feeling. What part of this has been the hardest?",
      ];
  }
  // Stage 2: exploring causes and patterns - more supportive, less interrogative
  elseif ($stage === 2) {
      $options = [
          "I'm here with you, {USER_FIRST}. When you look back a bit, was there a moment that made these feelings stronger, or has it been more gradual?",
          "Thanks so much for opening up, {USER_FIRST}. If you think back over the past days or weeks, what tends to trigger this feeling for you?",
          "You've been so brave in sharing, {USER_FIRST}. Are there certain people, places, or situations that seem to bring this up?",
          "You're doing your best in a really tough situation, {USER_FIRST}. When did you first start noticing that things felt this heavy?",
      ];
  }
  // Stage 3+: meaning, needs, and next steps - collaborative and gentle
  else {
      $options = [
          "Thank you for sharing so openly, {USER_FIRST}. If you put it in your own words, how would you describe what you're carrying right now?",
          "You're explaining this so clearly, even if it feels messy inside, {USER_FIRST}. What do you feel you need most from people around you right now?",
          "You really don't have to have all the answers, {USER_FIRST}. If there was one small thing that could make today a bit lighter, what might that be?",
          "You've been holding so much, {USER_FIRST}. What worries you the most about this, and what do you wish could change?",
      ];
  }

  return $options[array_rand($options)];
    }


    /** Did the user explicitly decline the referral? */
    private function declinedReferral(string $raw): bool
    {
        $t = trim(mb_strtolower($raw));

        // Rasa-style payload from your quick action: /deny{"confirm_topic":"referral"}
        if (preg_match('~^/deny\s*(\{.*\})?~i', $t, $m)) {
            if (!empty($m[1])) {
                try {
                    $j = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
                    if (isset($j['confirm_topic']) && strtolower($j['confirm_topic']) === 'referral') {
                        return true;
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            // if no json but plain /deny, still accept as decline when last offer was appointment
            return true;
        }

        // Fallback: common phrasing
        if (preg_match('/\b(not now|no thanks|maybe later|pass for now)\b/u', $t)) {
            return true;
        }

        return false;
    }

    /** Session lock helpers (session-scoped, no DB) */
    private function isLocked(int $sessionId): bool
    {
        return (bool) session('chat_locked_for_session_'.$sessionId, false);
    }
    private function lockSession(int $sessionId, string $reason='declined_referral'): void
    {
        session(['chat_locked_for_session_'.$sessionId => $reason ?: true]);
    }
    private function lockReason(int $sessionId): string
    {
        $v = session('chat_locked_for_session_'.$sessionId, false);
        return is_string($v) ? $v : ($v ? 'declined_referral' : '');
    }
    /** Core normalization + typo softening (English only). */
    private function nluNormalize(string $raw): string
    {
        $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u','',$raw) ?? '';
        $s = str_replace(["\r","\n","\t"], ' ', $s);
        // leetspeak & common swaps
        $s = strtr($s, [
            '0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','7'=>'t','@'=>'a','$'=>'s','!'=>'i'
        ]);
        // collapse 3+ repeats -> 2 (e.g., "soooo"→"soo")
        $s = preg_replace('/([a-z])\1{2,}/iu', '$1$1', $s);
        // spaces around punctuation to help tokenization
        $s = preg_replace('/([^\w\s])/u', ' $1 ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim(mb_strtolower($s));
    }

    /** English only (kept for API parity). */
    private function detectEnglish(): string { return 'en'; }

    /** Token list (a–z only) */
    private function tokens(string $t): array
    {
        preg_match_all('/[a-z]+/u', $t, $m);
        return $m[0] ?? [];
    }

    private function hasAnyWord(string $text, array $terms): bool
{
    $toks = $this->tokens($text);
    if (empty($toks)) return false;

    foreach ($terms as $term) {
        $term      = mb_strtolower($term);
        $len       = mb_strlen($term);
        $firstChar = mb_substr($term, 0, 1);

        foreach ($toks as $tok) {
            $tok = mb_strtolower($tok);

            // Exact match
            if ($tok === $term) {
                return true;
            }

            // Fuzzy match: only allow if same first letter and within edit distance 1
            $tokLen = mb_strlen($tok);
            if ($len <= 10 && abs($len - $tokLen) <= 1) {
                if (mb_substr($tok, 0, 1) !== $firstChar) {
                    continue; // 👈 prevents "sad" matching "mad"
                }
                if (levenshtein($tok, $term) <= 1) {
                    return true;
                }
            }
        }
    }

    return false;
}
private function hasAnyWordExact(string $text, array $terms): bool
{
    $tokens = $this->tokens($text); // all lowercased a–z words
    if (empty($tokens)) return false;
    $tokens = array_map('mb_strtolower', $tokens);

    foreach ($terms as $term) {
        $term = mb_strtolower($term);
        if (in_array($term, $tokens, true)) {
            return true;
        }
    }
    return false;
}

    /** Regex builder allowing spaces/hyphens between words and minor letter swaps. */
    private function flex(string $phrase): string
    {
        // kill myself -> k[i1]ll\s*my\s*self
        $letters = [
            'a'=>'[a@4]','e'=>'[e3]','i'=>'[i1!]','o'=>'[o0]','s'=>'[s5$]','t'=>'[t7]','l'=>'[l1]',
            // keep others as is
        ];
        $out = preg_replace_callback('/[a-z]/i', fn($m)=>($letters[strtolower($m[0])]??$m[0]), $phrase);
        $out = preg_replace('/\s+/', '\s*[- ]?\s*', $out);
        return $out;
    }

/** Rich risk assessment with typo/slang + EN / Taglish coverage. */
private function assessRisk(string $raw): array
{
    $t = $this->nluNormalize($raw);

    // -----------------------------------------------------------------
    // 0) Negation shield – “I don’t want to die / hurt myself”
    //    We don’t want this to instantly flag as HIGH if they’re
    //    explicitly saying they *don’t* want to.
    // -----------------------------------------------------------------
    $negationShield = false;

    // English
    if (preg_match(
        '/\bi\s*(don\'?t|do\s+not)\s*(want|plan|intend)\s*to\s*'
        .'(die|kill myself|hurt myself|end my life|harm myself)\b/u',
        $t
    )) {
        $negationShield = true;
    }

    // Tagalog / Taglish
    if (preg_match(
        '/\bayoko(?:ng)?\s+(mamatay|masaktan\s+ang\s+sarili|magpakamatay|mawala)\b/u',
        $t
    )) {
        $negationShield = true;
    }

    // -----------------------------------------------------------------
    // 1) Slang / shorthand that directly implies self-harm/suicide
    // -----------------------------------------------------------------
    $slangHigh = [
        'kms',                 // kill myself
        'kys',                 // kill yourself / kill yourself (still critical)
        'end it', 'end it all',
        'unalive',
        'i can\'t go on', 'cant go on', 'can\'t go on',
        'no reason to live', 'nothing to live for',
        'life is pointless', 'life is meaningless',

       
    ];

    foreach ($slangHigh as $s) {
        if (preg_match('/\b'.$this->flex($s).'\b/u', $t)) {
            return [
                'level' => $negationShield ? 'moderate' : 'high',
                'hits'  => [$s],
            ];
        }
    }

    // -----------------------------------------------------------------
    // 2) Explicit HIGH phrases (methods, direct self-harm)
    // -----------------------------------------------------------------
    $highPhrases = [
        // English – direct self-harm / suicide
        'kill myself', 'kill my self',
        'commit suicide', 'sui cide', 'suicide', 'sucide', 'suicde', 'suiside',
        'end my life', 'end myl ife',
        'i want to die', 'i wanna die', 'i plan to die', 'i am going to die',
        'wish i was dead', 'wish i were dead',
        'overdose', 'overdosing',
        'hang myself', 'hang my self',
        'jump off a bridge', 'jump off the bridge',
        'jump off a building', 'jump off the building',
        'jump in front of a bus', 'jump in front of a train',
        'slit my wrist', 'slit my wrists',
        'cut my wrists', 'cut my wrist',
        'self harm', 'self-harm', 'hurt myself on purpose',

        // “Kill me” variants – still treat as critical in this context
        'someone kill me', 'pls kill me', 'please kill me',

        // Filipino / Taglish explicit phrases
        'magpakamatay na lang ako',
        'papatayin ko ang sarili ko', 'papatayin ko sarili ko',
        'saktan ang sarili ko', 'saktan ko ang sarili ko',
        'hiwain ang pulsuhan ko', 'hiwain ko ang pulsuhan ko', 'hiwain ko ang pulso ko',
        'tumalon sa tulay', 'tumalon sa building', 'tumalon sa bubong',
        'tatalon ako sa tulay', 'tatalon ako sa building', 'tatalon ako sa bubong',
    ];

    foreach ($highPhrases as $p) {
        if (preg_match('/\b'.$this->flex($p).'\b/u', $t)) {
            return [
                'level' => $negationShield ? 'moderate' : 'high',
                'hits'  => [$p],
            ];
        }
    }
    // -----------------------------------------------------------------
// 2.5) Standalone "die" / "mamatay" etc. in emotional/personal context
// -----------------------------------------------------------------
$criticalActs = ['die', 'mamatay', 'magpakamatay'];

if (!$negationShield && $this->hasAnyWordExact($t, $criticalActs)) {
    // Any first-person / self reference
    $hasSelf = (bool) preg_match(
        '/\b(i|im|i\'m|ive|i\'ve|me|my|mine|myself|ako|ko|akin)\b/u',
        $t
    );

    // Emotional / context words that make "die" clearly about the self
    $hasEmotionContext = $this->hasAnyWord($t, [
        'sad','down','tired','hopeless','lonely','empty','numb',
        'pointless','meaningless','worthless','useless','burden',
        'hurt','hurting','pain','scared','afraid',
        // also allow "positive" framing like "smile" → still a death wish
        'smile','happy','peaceful','okay','ok',
    ]);

    // Very short lines like "die with a smile", "just die", etc.
    $isShort = mb_strlen($t) <= 30;

    if ($hasSelf || $hasEmotionContext || $isShort) {
        return [
            'level' => $negationShield ? 'moderate' : 'high',
            'hits'  => ['die_direct'],
        ];
    }
}


    // -----------------------------------------------------------------
    // 3) Intent + act within proximity (~8 tokens), both orders
    // -----------------------------------------------------------------
    $intent = '(wanna|want|plan|planning|think|thinking|feel like|should|will|might|need|tryna|trying|balak|plano|gusto ko)';
    $act    = '('
        .'suic(?:ide|de|e)'
        .'|die'
        .'|unalive'
        .'|kill\s*my\s*self'
        .'|end\s*my\s*life'
        .'|end\s*it'
        .'|overdose'
        .'|hang'
        .'|jump'
        .'|cut'
        .'|self[- ]?harm'
        .'|hurt\s*my\s*self'
        .'|magpakamatay'
        .'|mamatay'
        .'|saktan\s+ang\s+sarili'
        .'|maglaslas'
    .')';

    if (
        preg_match('/\b'.$intent.'\b(?:\W+\w+){0,8}\b'.$act.'\b/iu', $t)
        || preg_match('/\b'.$act.'\b(?:\W+\w+){0,8}\b'.$intent.'\b/iu', $t)
    ) {
        return [
            'level' => $negationShield ? 'moderate' : 'high',
            'hits'  => ['intent+act'],
        ];
    }

    // -----------------------------------------------------------------
    // 4) MODERATE – expanded hopeless / self-hatred set
    // -----------------------------------------------------------------
    $moderate = [
        // Self-hatred / worthlessness
        'i hate myself', 'i hat myself', 'i hte myself',
        'i am worthless', 'worthles', 'worthless', 'useless', 'i am a burden', 'burdn',
        'i\'m a burden', 'im a burden', 'feeling like a burden',

        // Disappearing / not existing
        'i want to disappear', 'i wanna disappear',
        'i dont want to exist', 'i don\'t want to exist',
        'i wish i never existed', 'i wish i wasn\'t here',

        // “Done with life” / “tapos na ako” style
        'feel like dying', 'feel lik dyin',
        'give up on life', 'i give up on life', 'i want to give up',
        'tired of everything', 'done with everything',
        'pagod na ako sa lahat', 'pagod na ako', 'sobra na pagod ko',
        'ayoko na', 'sawa na ako', 'sawang sawa na ako',

        // Strong depressive / anxiety words
        'overwhelmed', 'overwhelm',
        'burnout', 'burn out', 'burned out',
        'panic', 'anxiety', 'anxious',
        'depressed', 'depressd', 'depresed', 'deprssd', 'deprsd',

        // Not okay / low-functioning
        'i am not okay', 'im not ok', 'i\'m not ok', 'im not okay', 'i\'m not okay',
        'not okey', 'not ok',
        'i am not okey', 'i am not fine', 'im not fine', 'i\'m not fine',
    ];

    foreach ($moderate as $p) {
        if (preg_match('/\b'.$this->flex($p).'\b/u', $t)) {
            return ['level' => 'moderate', 'hits' => [$p]];
        }
    }

    // -----------------------------------------------------------------
    // 5) Default
    // -----------------------------------------------------------------
    return ['level' => 'low', 'hits' => []];
}

    /** Broad emotion tagging (with misspellings, synonyms, and key phrases). */
        private function labelEmotions(string $raw): array
        {
            $t      = $this->nluNormalize($raw);
            $labels = [];

            $map = [
                'sad' => [
                    'sad','down','blue','low','tearful','cry','crying','cried','grief',
                    'heartbroken','brokenhearted','broken inside','hurt inside',
                    'depressed','depressd','depresed','deprssd','deprsd',
                    'empty inside','numb inside','numb',
                ],

                'disappointed' => [
                    'disappoint','disappointed','dissapointed','dissappointed','disapointed',
                    'let down','letdown','let me down',
                ],
                    'anxious' => [
                        'anxious','anxiety','anxety','anxios',
                        'panicky','panic','panicking',
                        'afraid','scared','terrified','fearful','worried','worrying',
                        'nervous','on edge','uneasy',
                    ],

            'stressed' => [
                'stress','stressed','stressing',
                'pressure','pressured','under pressure',
                'overwhelm','overwhelmed','too much',
                'burnout','burn out','burned out',
            ],

            'tired' => [
                'tired','tiring','exhausted','exhausting','fatigued','fatigue',
                'drained','draining','worn out','wornout',
                'no energy','low energy','so tired','very tired',
            ],

            'angry' => [
                'angry','mad','furious','rage','raging',
                'irritated','annoyed','annoying','pissed','pissed off',
                'frustrated','frustrating','fed up','sick of this','sick of it',
            ],

            'lonely' => [
                'lonely','alone','isolated','left out','leftout',
                'no one understands','nobody understands','no one cares','nobody cares',
            ],

            'hopeless' => [
                'hopeless','no hope','pointless','meaningless',
                'no reason to live','nothing to live for','give up','giving up',
                'worthless','useless',
            ],

            'not_ok' => [
                'not ok','not okay','not fine','not okey',
                'i am not okay','im not ok','i\'m not ok','i\'m not okay','im not okay',
                'i am not fine','im not fine','i\'m not fine',
            ],

            'overwhelmed' => [
                'overwhelmed','cant cope','can\'t cope','cannot cope',
                'too much','too many things','everything piling up',
            ],

            'guilty' => [
                'guilty','guilt','my fault','all my fault','blame myself','blaming myself',
            ],

            'ashamed' => [
                'ashamed','shame','embarrassed','embarrassing','humiliated',
            ],

            'confused' => [
                'confused','confusing','lost','dont know what to do','don\'t know what to do',
                'unsure','uncertain','mixed up',
            ],

            'bored' => [
                'bored','boredom','meh','nothing to do','tired of this routine',
            ],

            // Extra label for "thinking too much" / spiralling
            'overthinking' => [
                'overthink','overthinking','can\'t stop thinking','cant stop thinking',
                'thoughts won\'t stop','thoughts wont stop','in my head a lot','in my head too much',
            ],
        ];

        foreach ($map as $label => $terms) {
            if ($this->hasAnyWord($t, $terms)) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }


 /** Intent classification (English only) with typo coverage + question guard. */
private function classifyIntents(string $raw): array
{
    $t = $this->nluNormalize($raw);

    // ---------------- Goodbye / closing detection ----------------
    // We treat pure "bye / good night / see you / take care / k bye / ok bye"
    // messages as conversation endings (done), NOT as non-mental topics.
    $goodbye = false;

    $closingPattern = '/^\s*('
        .'((ok|okay|k)\s*)?bye'                     // "bye", "ok bye", "k bye"
        .'|bye\s+bye'                               // "bye bye"
        .'|good\s*bye'
        .'|goodbye'
        .'|good\s*night'
        .'|goodnight'
        .'|see\s+you(?:\s+soon)?'                   // "see you", "see you soon"
        .'|talk\s+to\s+you\s+later'                 // "talk to you later", "ttyl" below
        .'|ttyl'
        .'|take\s*care'
        .'|thanks\s*,?\s*bye'
        .'|thank\s+you\s*,?\s*bye'
    .')\s*[!.…]*\s*$/iu';

    if (preg_match($closingPattern, $t)) {
        $goodbye = true;
    }

    // ---------------- Bot intro / capabilities ----------------
    $asksCapabilities = false;

    if (preg_match(
        '/\b('
            .'what\s+can\s+(you|u)\s+do'
            .'|what\s+do\s+you\s+do'
            .'|what\s+can\s+this\s+(bot|chatbot|app)\s+do'
            .'|what\s+is\s+(lumichat|this\s+bot|this\s+chatbot)'
            .'|who\s+are\s+you'
            .'|what\s+are\s+you'
            .'|how\s+can\s+you\s+help\s+me'
            .'|what\s+can\s+you\s+help\s+me\s+with'
        .')\b/iu',
        $t
    )) {
        $asksCapabilities = true;
    }

    // ---------------- Appointment intent ----------------
    $action  = '(appoint(?:ment)?|apointment|schedule|schedual|book(?:ing)?|bok|reserve|set\s*up)';
    $role    = '(counsel(?:or|ler)|counsellor|councelor|counslor|therap(?:ist|y)|advisor|someone to talk)';

    $wantsAppt = (bool) (
        (preg_match('/\b'.$action.'\b/iu', $t) && preg_match('/\b'.$role.'\b/iu', $t))
        || preg_match('/\bsee\s+(?:a\s+)?'.$role.'\b/iu', $t)
    );

    if (!$wantsAppt) {
        $hasBookingVerb = (bool) preg_match(
            '/\b(appoint(?:ment)?|apointment|schedule|schedual|book(?:ing)?|bok|reserve)\b/iu',
            $t
        );

        $hasRequestPhrase = (bool) preg_match(
            '/\b(i\s+want|i\'d\s+like|i\s+like\s+to|can\s+i|could\s+i|please)\b/iu',
            $t
        );

        $shortBookingOnly = (bool) preg_match('/\bi\s+want\s+to\s+book\b/iu', $t)
                         || (preg_match('/\bbook\b/iu', $t) && mb_strlen($t) <= 40);

        if (($hasBookingVerb && $hasRequestPhrase) || $shortBookingOnly) {
            $wantsAppt = true;
        }
    }

    // ---------------- Coping tips/help ----------------
    $coping = [
        'coping tips','cope tips','help me cope','ways to cope',
        'how to deal','how do i deal','how to handle','how do i handle',
        'advice for this','what can i do','give me tips','share tips','show tips',
        'grounding','breathing exercise','breath exercise'
    ];
    $wantsCoping = false;
    foreach ($coping as $p) {
        if (preg_match('/\b'.$this->flex($p).'\b/u', $t)) {
            $wantsCoping = true;
            break;
        }
    }

    // ---------------- Question detection ----------------
    $hasQmark = str_contains($t, '?');
    $startsWh = (bool) preg_match('/^\s*(how|what|why|can|should|where|when|who|which)\b/u', $t);
    $dontKnow = (bool) preg_match('/\bi\s*(don\'?t|do\s+not)\s+know\s+(why|what|how)\b/u', $t);
    $isQuestion = ($hasQmark || $startsWh) && !$dontKnow;

    // ---------------- Refuse to share ----------------
    $refuseShare = (bool) preg_match(
        '/\b(i\s*(?:don\'?t|do\s*not)\s*(?:want|feel like)\s*(?:to\s*)?(talk|share|say)'
        .'|prefer\s*not\s*to\s*(say|share|talk)'
        .'|not\s*now|maybe\s*later|not\s*ready|skip|pass)\b/u',
        $t
    );

    // ---------------- Yes / No ----------------
    $yes = (bool) preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|go ahead|proceed|please)\b/u', $t);
    $no  = (bool) preg_match('/\b(no|nope|not now|later|pass)\b/u', $t);

    // ---------------- Done (finished coping / conversation for now) ----------------
    $done = false;

    // Rasa-style payloads
    if (preg_match('~/(coping_done|done_coping|finish_coping)~i', $t)) {
        $done = true;
    }

    // Natural language "done" / "I'm okay now"
    if (!$done) {
        $done = (bool) preg_match(
            '/\b('
                .'done for now'
                .'|i\'?m done'
                .'|im done'
                .'|that\'?s enough'
                .'|that is enough'
                .'|that\'?s all'
                .'|that is all'
                .'|i\'?m okay now'
                .'|im okay now'
                .'|i am okay now'
                .'|i\'?m fine now'
                .'|im fine now'
                .'|i am fine now'
            .')\b/u',
            $t
        );
    }

    // Extra: treat bare "done" as done
    if (!$done) {
        $short = trim($t);
        if (in_array($short, ['done'], true)) {
            $done = true;
        }
    }

    // NEW: pure goodbye messages → mark as done too
    if (!$done && $goodbye) {
        $done = true;
    }

    return [
        'flags' => [
            'wants_appointment' => $wantsAppt,
            'wants_coping'      => $wantsCoping,
            'is_question'       => $isQuestion,
            'refused_to_share'  => $refuseShare,
            'yes'               => $yes,
            'no'                => $no,
            'done'              => $done,
            'asks_capabilities' => $asksCapabilities,
            'goodbye'           => $goodbye, // for future use
        ],
        'score' => [
            'length'            => mb_strlen($t),
            'wants_appointment' => $wantsAppt ? 1.0 : 0.0,
            'wants_coping'      => $wantsCoping ? 1.0 : 0.0,
            'is_question'       => $isQuestion ? 1.0 : 0.0,
        ],
    ];
}


    /** Coarse last-bot-intent for contextual "yes" handling. */
    private function lastBotIntent(int $sessionId): string
    {
        $lastBot = \App\Models\Chat::where('chat_session_id', $sessionId)->where('sender','bot')->latest('sent_at')->first();
        if (!$lastBot) return '';
        try { $txt = \Illuminate\Support\Facades\Crypt::decryptString($lastBot->message); } catch (\Throwable $e) { $txt = (string) $lastBot->message; }
        $t = mb_strtolower($txt);
        if (str_contains($t,'show tips') || str_contains($t,'want them now')) return 'offer_coping';
        if (str_contains($t,'book an appointment') || str_contains($t,'book counselor') || str_contains($t,'schedule')) return 'offer_appointment';
        return '';
    }

    /** Should we bypass venting? — stricter & length-aware (English only). */
    private function shouldBypassVenting_EN(string $text, string $risk, array $flags, int $lenLimit = 400): bool
    {
        if ($risk === 'high') return true;
        if ($flags['wants_appointment'] ?? false) return true;
        // short real question → bypass; long paragraphs with “why” keep venting
        if (($flags['is_question'] ?? false) && mb_strlen($text) <= $lenLimit) return true;
        return false;
    }

/** Detect clearly non–mental-health topics (games, homework, general info, etc.). */
private function isNonMentalTopic(string $norm, array $labels, array $riskStruct, array $flags): bool
{
     // Capability / intro questions should always be answered by Lumi,
    // not treated as non-mental.
    if ($flags['asks_capabilities'] ?? false) {
        return false;
    }
    $risk = $riskStruct['level'] ?? 'low';

    // 0) YES/NO/DONE replies are always contextual → NEVER treat as non-mental.
    if (($flags['yes'] ?? false) || ($flags['no'] ?? false) || ($flags['done'] ?? false)) {
        return false;
    }

    // 1) If there is ANY emotional or risk signal → treat as mental-health-related
    if ($risk !== 'low') return false;
    if (!empty($labels)) return false;
    if ($flags['wants_appointment'] ?? false) return false;
    if ($flags['wants_coping'] ?? false) return false;

    // 1.a) GREETINGS: "hi", "hello", etc. should NOT be treated as non-mental
    if ($this->hasAnyWord($norm, [
        'hi','hello','hey','helo','hii',
        'good morning','good afternoon','good evening',
    ])) {
        if (mb_strlen($norm) <= 40 && !($flags['is_question'] ?? false)) {
            return false;
        }
    }

    // 2) If it already talks about problems / struggle / thoughts / hard time → keep as mental.
    //    (Better to over-classify as mental than to push a struggling student away.)
    if ($this->hasAnyWord($norm, [
        'stress','stressed','anxiety','anxious','depress','depressed','sad',
        'overwhelmed','lonely','tired','burnout','panic',
        'problem','problems','struggle','struggling','struggles',
        'worry','worried','scared','scary','afraid',
        'hurt','hurting','pain','painful',
        'feel','feeling','feels','felt',
        'mind','thought','thoughts','overthink','overthinking','in my head',
        'empty','numb','lost','drained','hard','difficult',
        'okay','not ok','not okay','not fine','not okey',
        'wrong','weird','off',
    ])) {
        return false;
    }

    // 2.b) Self-disclosure / "opening up" gets treated as mental even if there are school/game words.
    $selfDisclosure = $this->looksLikeSelfDisclosure($norm);
    if ($selfDisclosure) {
        return false;
    }

       // 3) Keywords that usually indicate general / non-mental-health / factual topics
    $nonMentalKeywords = [

        // ===== Games / entertainment / pop culture =====
        'game','games','gaming','gamer','rank','mmr',
        'steam','valorant','valo','dota','gta','minecraft','roblox',
        'ml','mobile legends','mlbb','league of legends','lol','wild rift',
        'cod','call of duty','pubg','genshin','honkai','fortnite',
        'ps4','ps5','playstation','xbox','nintendo','switch','console','skin','skins','battle pass',

        'music','song','songs','album','playlist','lyrics',
        'movie','movies','film','films','cinema','series','episode','episodes',
        'kdrama','anime','manga','netflix','disney','disney+','spotify','tiktok','youtube',
        'idol','kpop','bts','blackpink','twice','enhypen','newjeans',
        'celebrity','celeb','actor','actress','influencer','streamer','vlogger',

        // ===== Food, recipes, places to eat =====
        'food','foods','recipe','recipes','cook','cooking','bake','baking',
        'restaurant','restaurants','cafe','cafes','milk tea','milktea','coffee shop','coffee',
        'fastfood','fast food','jollibee','mcdonalds','kfc','pizza','burger',
        'fries','ramen','sushi','buffet','menu','order','delivery','grab','foodpanda',

// ===== Tech / coding / computer support =====
        'programming','coding','code','source code','script','scripting',
        'algorithm','pseudocode','flowchart','debug','debugging','bug','bugs',
        'error','errors','exception','stack trace',
        'javascript','typescript','node','nodejs','php','laravel','django','flask','python',
        'java','csharp','c#','cpp','c++','ruby','rails','html','css',
        'react','vue','angular','svelte','tailwind','bootstrap',
        'mysql','postgres','database','databases','sql','query','queries','migration','seeder',
        'api','rest api','endpoint','request','response','json','jwt',
        'github','gitlab','bitbucket','git','branch','merge','commit','pull request','push','pull',
        'vscode','visual studio','eclipse','intellij','pycharm','ide',
        'server','hosting','hostinger','domain','dns','nginx','apache','iis',

        // devices / network / OS  👇 ADDED HERE
        'wifi','router','modem','internet','signal','lag','ping','fps','ms',
        'phone','phones','cellphone','cellphones','cell','cp',
        'smartphone','smartphones',
        'tablet','tablets','ipad','ipads',
        'gadget','gadgets',
        'screen','screens','lcd','touchscreen',
        'laptop','pc','desktop','computer','monitor','keyboard','mouse','headset',
        'android','iphone','ios','windows','macos','linux','ubuntu','update','upgrade',
        'install','installation','download','setup','config','configuration',

        // ===== Shopping / money / finance (neutral use) =====
        'shopping','shop','shops','mall','malls','grocery','groceries',
        'cart','checkout','order','orders','parcel','package','tracking',
        'sale','discount','promo','voucher','free shipping','cashback',
        'shopee','lazada','zalora','amazon','aliexpress',
        'price','prices','cheap','cheaper','expensive',
        'peso','dollar','php','usd',

        // ===== Travel / locations / itineraries =====
        'travel','trip','trips','tour','tourist','vacation','staycation',
        'itinerary','hotel','resort','hostel','airbnb',
        'plane','flight','flights','airport','terminal','ticket','tickets',
        'visa','passport','luggage','baggage','check in','boarding',
        'beach','mountain','island','city','province',
        'boracay','palawan','siargao','baguio','cebu','davao','manila',

        // ===== Sports / physical hobbies (mostly neutral) =====
        'basketball','volleyball','football','soccer','futsal',
        'badminton','tennis','table tennis','pingpong','ping pong',
        'swimming','run','running','jogging','gym','workout','exercise',
        'league','tournament','match','practice','training','coach','team',

        // ===== Creative hobbies / tools =====
        'drawing','draw','art','painting','sketch','sketching',
        'design','logo','poster','layout','editing',
        'photoshop','illustrator','canva','figma','premiere','after effects',
        'capcut','filter','preset',
        'camera','dslr','mirrorless','lens','tripod','gimbal',
        'tiktok edit','video edit','thumbnail',

        // ===== Beauty / fashion / lifestyle =====
        'makeup','lipstick','mascara','eyeliner','foundation','concealer',
        'skincare','skin care','serum','moisturizer','sunscreen',
        'outfit','ootd','clothes','clothing','tshirt','shirt','pants','jeans',
        'shoes','sneakers','bag','bags','accessories','necklace','bracelet',
        'haircut','hairstyle','salon','nail','nails','manicure','pedicure',

        // ===== Academics / subject-only content (NO emotion words) =====
        'math','algebra','geometry','trigonometry','calculus','statistic','statistics',
        'physics','chemistry','biology','science','scientific',
        'history','geography','economics',
        'formula','formulas','equation','equations',
        'solve','solution','answer key','proof',
        'definition','define','explain','explanation',
        'module','modules','assignment','assignments','homework','seatwork',
        'quiz','quizzes','exam','exams','test','tests','midterm','finals',
        'topic','lesson','chapter',

        // ===== Social media / online platforms =====
        'facebook','fb','messenger','instagram','ig','tiktok','twitter','x',
        'snapchat','discord','server','group chat','gc',
        'post','posts','comment','comments','dm','dms',
        'fyp','timeline','newsfeed','story','stories','reels','shorts',

        // ===== Money / career (neutral info) =====
        'salary','salaries','income','allowance',
        'budget','budgeting','savings','save money',
        'job','jobs','hiring','applicant','application','resume','cv',
        'interview','interviews','offer','promotion',
        'career','careers','position','positions',

        // ===== Health / body (non-emotional use) =====
        'fever','cough','cold','flu','sore throat','headache','migraine',
        'covid','virus','infection',
        'medicine','medication','tablet','capsule','syrup',
        'doctor','clinic','hospital','checkup','check up',
        'diet','keto','calories',
        'height','weight','bmi',

        // ===== School admin / process =====
        'enrollment','enrolment','enroll','enrol',
        'registration','register','account','portal','student portal',
        'sis','lms','canvas','moodle','google classroom',
        'password','username','login','log in','log out','logout',
        'reset password','change password',
        'form','forms','clearance','requirements','document','documents',
        'registrar','scholarship','scholarships','grant','grants','school id','id card',

        // ===== Random factual / how-to topics =====
        'capital','history of','invention','inventor','discover','discovery',
        'tutorial','guide','step by step','steps to','how to make',
        'recipe for','requirements','qualifications','eligibility',
        'job hiring','cover letter','interview questions',
        'law','legal','crime','case','court','constitution',
    ];

    $nonMentalHits = $this->countKeywordHits($norm, $nonMentalKeywords);

    // 3.a) Strong non-mental signal:
    //     - at least 2 separate hits
    //     - NOT self-disclosure
    if ($nonMentalHits >= 2 && !$selfDisclosure) {
        return true;
    }

    // 3.b) Single-keyword case:
    //     Only treat as non-mental if the message is short & factual-sounding (e.g., "valorant rank system?")
    if ($nonMentalHits === 1 && !$selfDisclosure) {
        $tokens = $this->tokens($norm);
        if (mb_strlen($norm) <= 60 && count($tokens) <= 8) {
            return true;
        }
    }

    // 4) Fallback: token/length-based garbage detection
    $tokens = $this->tokens($norm);
    if (empty($tokens)) {
        return true;
    }

    // Short random text with no basic meaning and not self-disclosure → non-mental
    if (count($tokens) <= 3 && mb_strlen($norm) <= 40) {
        if (
            !$this->hasAnyWord($norm, [
                'help','support','counselor','counselling','counseling',
                'problem','problems','sad','anxious','anxiety','depress','stress','worried',
                'disappoint','disappointed','dissapointed','dissappointed','disapointed',
                'feel','feeling','feels','mind','thought','thoughts',
            ])
            && !$selfDisclosure
        ) {
            return true;
        }
    }

    // Default: treat as mental-health-related (safer).
    return false;
}


/** Detects when the input is basically not understandable (random chars, no clear words). */
private function isUnreadableInput(string $norm): bool
{
    $norm = trim($norm);

    // 1) Completely empty after normalization
    if ($norm === '') {
        return true;
    }

    // 2) No letters at all (pure emojis, numbers, symbols)
    if (!preg_match('/[a-z]/i', $norm)) {
        return true;
    }

    // 3) Tokenize – if nothing looks like a word at all
    $tokens = $this->tokens($norm);
    if (empty($tokens)) {
        return true;
    }

    // 4) If ANY token is a clear, normal short word, treat as readable
    $whitelistTokens = [
        'hi','hey','hello',
        'ok','okay',
        'yes','no',
        'hmm','lol',
        'sad','help',
        'me','you','i',
        // 👇 NEW: endings / goodbyes should always be treated as readable
        'bye','goodbye','night','goodnight','gn','tc','thanks','thank'
    ];

    foreach ($tokens as $tk) {
        if (in_array(mb_strtolower($tk), $whitelistTokens, true)) {
            return false; // definitely readable
        }
    }

    // 5) Short mixed “garbage”: few tokens, small length, has digits/symbols,
    //    and no basic emotional/help words -> treat as unreadable.
    if (count($tokens) <= 2 && mb_strlen($norm) <= 8) {
        $hasDigitOrSymbol = (bool) preg_match('/[\d\W_]/u', $norm);

        $basicMeaningful = [
            'feel','feeling','tired','stressed','anxious','sad','angry','lonely',
            'fine','not','okay','ok','help','support'
        ];

        if ($hasDigitOrSymbol && !$this->hasAnyWord($norm, $basicMeaningful)) {
            return true;
        }
    }

    // If it passed all checks above, treat it as readable.
    return false;
}

    /**
     * Build a ChatGPT-style session title from the latest message.
     *
     * Examples it can generate:
     *  - "Feeling overwhelmed about school"
     *  - "Anxious about exams"
     *  - "Relationship stress with friends"
     *  - "Question about family expectations"
     *  - "Crisis thoughts about self-harm"
     */
  /**
 * Build a ChatGPT-style session title from the latest message.
 *
 * Examples it can generate:
 *  - "Feeling overwhelmed about school"
 *  - "Anxious about exams and deadlines"
 *  - "Question about family expectations"
 *  - "Crisis thoughts about self-harm"
 *  - "Thinking about seeing a counselor"
 */
private function buildSessionTitle(
    string $norm,
    string $analysisText,
    array $labels,
    array $flags,
    array $riskStruct,
    int $msgCount,
    ?string $currentTitle = null
): string {
    $risk = $riskStruct['level'] ?? 'low';
    $hits = $riskStruct['hits'] ?? [];

    // 0) If we already have a decent human title and convo is long, keep it.
    if (
        $currentTitle
        && $msgCount > 6
        && !str_starts_with($currentTitle, 'Starting conversation')
    ) {
        // But if risk just escalated to HIGH, we allow renaming to a crisis title.
        if ($risk !== 'high' || str_starts_with($currentTitle, 'Crisis')) {
            return $currentTitle;
        }
    }

    // 1) Primary emotion label → mood phrase
    $primary = strtolower($labels[0] ?? '');
    $moodPhrases = [
        'sad'          => 'Feeling sad',
        'anxious'      => 'Feeling anxious',
        'stressed'     => 'Feeling stressed',
        'tired'        => 'Exhausted and tired',
        'angry'        => 'Feeling angry',
        'lonely'       => 'Feeling lonely',
        'hopeless'     => 'Hopeless and overwhelmed',
        'overwhelmed'  => 'Feeling overwhelmed',
        'not_ok'       => 'Not feeling okay',
        'guilty'       => 'Feeling guilty',
        'ashamed'      => 'Struggling with shame',
        'confused'     => 'Feeling confused',
        'disappointed' => 'Disappointed',
        'bored'        => 'Feeling stuck and bored',
        'overthinking' => 'Overthinking a lot',
    ];

    // 2) Special cases: crisis / appointment / capabilities
    if ($risk === 'high') {
        // Slightly more specific crisis phrasing
        $crisisTitle = 'Crisis thoughts';
        if (in_array('die_direct', $hits, true) || $this->hasAnyWord($norm, ['kill','suicide','unalive'])) {
            $crisisTitle = 'Crisis thoughts about self-harm';
        } elseif ($this->hasAnyWord($norm, ['disappear','exist','worthless','burden'])) {
            $crisisTitle = 'Crisis thoughts about wanting to disappear';
        }
        $title = $crisisTitle;
    } elseif ($flags['wants_appointment'] ?? false) {
        $title = 'Thinking about seeing a counselor';
    } elseif ($flags['asks_capabilities'] ?? false && $msgCount <= 2) {
        $title = 'Getting to know LumiCHAT';
    } elseif ($primary && isset($moodPhrases[$primary])) {
        $title = $moodPhrases[$primary];
    } elseif ($flags['is_question'] ?? false) {
        $title = 'Question';
    } else {
        $title = 'Conversation';
    }

    // 3) Topic / domain detection (what is this *about*?)
    $topicSuffix = '';

    // Academics / school load
    if ($this->hasAnyWord($norm, [
        'school','class','classes','subject','subjects','assignment','assignments',
        'homework','module','modules','quiz','exam','exams','test','projects',
        'grades','grade','teacher','professor','course','courses','deadline','requirements',
    ])) {
        $topicSuffix = 'about school';
    }
    // Friends / romantic / social relationships
    elseif ($this->hasAnyWord($norm, [
        'friend','friends','bestfriend','best friend','bff','classmate','classmates',
        'crush','partner','boyfriend','girlfriend','relationship','relationships',
        'breakup','break up','ex','trust','betray','betrayed','blocked','seenzone',
    ])) {
        $topicSuffix = 'about relationships';
    }
    // Family
    elseif ($this->hasAnyWord($norm, [
        'family','parents','mother','father','mama','papa','mom','dad',
        'lola','lolo','siblings','brother','sister','tita','tito',
        'home','house',
    ])) {
        $topicSuffix = 'about family';
    }
    // Bullying / conflict
    elseif ($this->hasAnyWord($norm, [
        'bully','bullying','bullied',
        'away from me','they hate me','they don\'t like me',
        'conflict','fighting','argue','argument','toxic',
    ])) {
        $topicSuffix = 'about conflict or bullying';
    }
    // Self-worth / identity
    elseif ($this->hasAnyWord($norm, [
        'myself','self','identity','who i am','purpose','meaning',
        'worth','worthless','useless','burden','failure','fail','failed',
        'ugly','fat','insecure','self-esteem','confidence',
    ])) {
        $topicSuffix = 'about self-worth';
    }
    // Future / decisions / career
    elseif ($this->hasAnyWord($norm, [
        'future','career','job','work','working','income','money',
        'choice','choices','decisions','decision','path','course shift','shift course',
        'plan','plans','dream','dreams',
    ])) {
        $topicSuffix = 'about the future';
    }
    // Health / body / energy
    elseif ($this->hasAnyWord($norm, [
        'health','sick','ill','hospital','checkup',
        'body','weight','gain','lost weight','diet',
        'no energy','drained','fatigue','headache','insomnia','can\'t sleep','cant sleep',
    ])) {
        $topicSuffix = 'about health';
    }

    // 4) Combine mood + topic
    if ($topicSuffix !== '') {
        // e.g. "Feeling overwhelmed about school"
        $title = trim($title.' '.$topicSuffix);
    } elseif ($flags['is_question'] ?? false) {
        // For questions with no clear topic, show a short snippet.
        $snippet = \Illuminate\Support\Str::limit($analysisText, 40, '…');
        $title   = 'Question: '.$snippet;
    }

    // 5) Fallback: if still too generic, just use a clean snippet.
    if ($title === '' || mb_strlen($title) < 4 || $title === 'Conversation') {
        $title = \Illuminate\Support\Str::limit($analysisText, 40, '…');
    }

    // 6) Make sure it’s not insanely long.
    $title = \Illuminate\Support\Str::limit($title, 60, '…');

    // Capitalize first char for safety.
    return ucfirst($title);
}

/** Count how many distinct non-mental keywords appear in the text (with typo tolerance). */
private function countKeywordHits(string $text, array $terms): int
{
    $tokens = $this->tokens($text);
    if (empty($tokens)) return 0;

    $tokens = array_map('mb_strtolower', $tokens);
    $hits   = 0;
    $seen   = [];

    foreach ($terms as $term) {
        $term = mb_strtolower($term);
        if (isset($seen[$term])) continue;

        $len = mb_strlen($term);
        foreach ($tokens as $tok) {
            if ($tok === $term) {
                $hits++;
                $seen[$term] = true;
                break;
            }

            if ($len <= 10 && abs($len - mb_strlen($tok)) <= 1) {
                if (levenshtein($tok, $term) <= 1) {
                    $hits++;
                    $seen[$term] = true;
                    break;
                }
            }
        }
    }

    return $hits;
}
/** Heuristic: does this look like the student is opening up / talking about themselves? */
private function looksLikeSelfDisclosure(string $norm): bool
{
    $norm = trim($norm);

    // First-person pronouns (EN + a bit of Filipino)
    $hasPronoun = (bool) preg_match(
        '/\b(i|im|i\'m|ive|i\'ve|me|my|mine|myself|ako|ko|akin)\b/u',
        $norm
    );

    if (!$hasPronoun) {
        return false;
    }

    // Words that usually appear when someone is talking about what they’re going through
    if ($this->hasAnyWord($norm, [
        'feel','feeling','felt',
        'struggle','struggling','struggles',
        'hard','harder','difficult',
        'tired','exhausted','drained',
        'lost','empty','numb',
        'worried','scared','anxious','stressed','sad','lonely','hopeless','confused',
        'bother','bothering','hurt','hurting',
        'overthink','overthinking',
        'cant','cannot',
    ])) {
        return true;
    }

    // Phrases like "I don't know what's happening", "I don't know what to do"
    if (preg_match(
        '/\bi\s*(don\'?t|do\s*not)\s*know\s*(what(?:\'?s)?|whats|how|why|where)\b/u',
        $norm
    )) {
        return true;
    }

    // Phrases like "lately", "recently", "these days" often signal personal context
    if (preg_match('/\b(lately|recently|these days)\b/u', $norm)) {
        return true;
    }

    return false;
}
/**
 * Roughly classify non-mental topics so we can respond more appropriately.
 * This only runs AFTER isNonMentalTopic() already decided "non-mental".
 */
/**
 * Roughly classify non-mental topics so we can respond more appropriately.
 * This only runs AFTER isNonMentalTopic() already decided "non-mental".
 */
private function classifyNonMentalCategory(string $norm): string
{
    // Games / gaming
    if ($this->hasAnyWord($norm, [
        'game','games','gaming','gamer',
        'play','playing','rank','mmr',
        'valorant','valo','dota','doto','ml','mlbb',
        'mobile legends','league','lol','wild rift',
        'minecraft','roblox','gta','cod','pubg',
        'genshin','honkai','fortnite',
        'ps4','ps5','playstation','xbox','switch','nintendo',
        'console',
    ])) {
        return 'games';
    }

    // Movies / series / anime / general entertainment / music
    if ($this->hasAnyWord($norm, [
        'movie','movies','film','films','cinema',
        'series','episode','episodes','show','shows',
        'netflix','disney','disney+','hbo','prime video',
        'kdrama','anime','manga',
        'spotify','playlist','music','songs','song','album',
        'lyrics','mv',
        'tiktok','youtube','stream','streams','streamer','vlogger',
        'concert','tour','idol','kpop','bts','blackpink','twice','enhypen','newjeans',
    ])) {
        return 'entertainment';
    }

    // Food / eating
    if ($this->hasAnyWord($norm, [
        'food','foods','eat','eating','ate','hungry','craving','cravings','snack','snacks',
        'restaurant','restaurants','cafe','cafes','milk tea','milktea','coffee',
        'fastfood','fast food','jollibee','mcdonalds','kfc',
        'pizza','burger','fries','ramen','sushi','buffet',
        'cook','cooking','bake','baking','recipe','recipes','ingredients','menu',
        'order','delivery','takeout','grab','foodpanda',
    ])) {
        return 'food';
    }

    // Travel
    if ($this->hasAnyWord($norm, [
        'travel','trip','trips','tour','tourist','vacation','staycation',
        'itinerary','hotel','resort','hostel','airbnb',
        'flight','flights','airport','ticket','tickets','plane','boarding','terminal',
        'beach','mountain','island','city','province',
        'boracay','palawan','siargao','baguio','cebu','davao','manila',
    ])) {
        return 'travel';
    }

    // Shopping / money stuff (non-emotional use)
    if ($this->hasAnyWord($norm, [
        'shopping','shop','shops','mall','malls','grocery','groceries',
        'cart','checkout','order','orders','parcel','package','tracking',
        'sale','discount','promo','voucher','free shipping','cashback',
        'shopee','lazada','zalora','amazon','aliexpress',
        'price','prices','cheap','cheaper','expensive',
    ])) {
        return 'shopping';
    }

    // Sports / exercise
    if ($this->hasAnyWord($norm, [
        'basketball','volleyball','football','soccer','futsal',
        'badminton','tennis','table tennis','pingpong','ping pong',
        'swimming','run','running','jogging','gym','workout','exercise','training','practice',
        'league','tournament','match','game day','coach','team','teammate','teammates',
    ])) {
        return 'sports';
    }

    // Tech / coding / devices
    if ($this->hasAnyWord($norm, [
        'programming','coding','code','codes','script','scripts',
        'bug','bugs','debug','debugging','error','errors','exception',
        'issue','issues','fix','fixed','fixing','problem','problems',
        'crash','crashing','crashed','lag','freeze','frozen',

        // frameworks / langs
        'javascript','typescript','node','nodejs','php','laravel',
        'python','java','csharp','c#','cpp','c++','html','css',
        'react','vue','angular','svelte','tailwind','bootstrap',

        // db / backend
        'mysql','postgres','database','databases','sql','query','queries','migration','seeder',
        'api','rest api','endpoint','request','response','json','jwt',

        // tooling / git
        'github','gitlab','bitbucket','git','branch','merge','commit','pull','push',
        'vscode','visual studio','ide','editor',

        // infra / hosting / errors
        'server','hosting','hostinger','domain','dns','nginx','apache','iis',
        '500','404','server error','internal server error',

        // devices / os  👇 ADDED HERE
        'wifi','router','modem','internet','signal','ping','fps','ms',
        'phone','phones','cellphone','cellphones','cell','cp',
        'smartphone','smartphones',
        'tablet','tablets','ipad','ipads',
        'gadget','gadgets',
        'screen','screens','lcd','touchscreen',
        'laptop','pc','desktop','computer','monitor','keyboard','mouse','headset',
        'android','iphone','ios','windows','macos','linux','ubuntu','update','upgrade',
        'install','installation','download','setup','config','configuration',
    ])) {
        return 'tech';
    }



    // Creative / editing / design
    if ($this->hasAnyWord($norm, [
        'drawing','draw','art','artist','painting','paint','sketch','sketching',
        'design','logo','poster','layout','banner','thumbnail',
        'editing','edit','edits','video edit','photo edit',
        'photoshop','illustrator','canva','figma','premiere','after effects','capcut',
        'camera','dslr','mirrorless','lens','tripod','gimbal',
    ])) {
        return 'creative';
    }

    // Academics / homework / purely subject content
    if ($this->hasAnyWord($norm, [
        'math','algebra','geometry','trigonometry','calculus','statistic','statistics',
        'physics','chemistry','biology','science','scientific',
        'history','geography','economics',
        'formula','formulas','equation','equations',
        'solve','solution','answer key','proof',
        'definition','define','explain','explanation',
        'module','modules','assignment','assignments','homework','seatwork',
        'quiz','quizzes','exam','exams','test','tests','midterm','finals',
        'topic','lesson','chapter',
    ])) {
        return 'academics';
    }

    // Social media / online platforms (separate from generic entertainment)
    if ($this->hasAnyWord($norm, [
        'facebook','fb','messenger','instagram','ig','tiktok','twitter','x',
        'snapchat','discord','server','group chat','gc',
        'post','posts','comment','comments','dm','dms',
        'fyp','timeline','newsfeed','story','stories','reels','shorts',
    ])) {
        return 'social_media';
    }

    // Money / career / future in a neutral, factual way
    if ($this->hasAnyWord($norm, [
        'salary','salaries','income','allowance',
        'budget','budgeting','savings','save money',
        'peso','php','dollar','usd',
        'job','jobs','hiring','applicant','application','resume','cv',
        'interview','interviews','offer','promotion',
        'career','careers','position','positions',
    ])) {
        return 'money_career';
    }

    // Physical health / body (non-emotional use)
    if ($this->hasAnyWord($norm, [
        'fever','cough','cold','flu','sore throat','headache','migraine',
        'covid','virus','infection',
        'medicine','medication','tablet','capsule','syrup',
        'doctor','clinic','hospital','checkup','check up',
        'diet','workout plan','keto','calories',
        'height','weight','bmi',
    ])) {
        return 'health_body';
    }

    // School admin / accounts / process support
    if ($this->hasAnyWord($norm, [
        'enrollment','enrolment','enroll','enrol',
        'registration','register','account','portal','student portal',
        'sis','lms','canvas','moodle','google classroom',
        'password','username','login','log in','log out','logout',
        'reset password','change password',
        'form','forms','clearance','requirements','document','documents',
        'registrar','scholarship','grant','id card','school id',
    ])) {
        return 'admin_school';
    }

    // Fallback non-mental
    return 'other';
}

/**
 * Pick a variant from a list, rotating per session+key so it’s not always
 * the same reply for the same pattern.
 */
private function pickVariantForSession(int $sessionId, string $key, array $variants): string
{
    if (empty($variants)) {
        return '';
    }

    $sessionKey = 'variant_'.$sessionId.'_'.$key;
    $idx        = (int) session($sessionKey, 0);

    $reply = $variants[$idx % count($variants)];

    session([$sessionKey => $idx + 1]); // rotate next time
    return $reply;
}

private function nonMentalReply(int $sessionId, string $first, string $norm, array $flags): string
{
    $category   = $this->classifyNonMentalCategory($norm);
    $isQuestion = $flags['is_question'] ?? false;

    $first = trim($first) !== '' ? $first : 'there';

    switch ($category) {
        // ===================== GAMES =====================
        case 'games': {
            $key = 'nonmental_games_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "Gaming can really matter to us, {$first}. I’m mainly focused on your mental health, so I might not guide you on rank or mechanics — but if pressure from games, teammates, or losing is affecting how you feel, we can talk about that.",
                    "It sounds like you care a lot about gaming, {$first}. Lumi is more about what’s going on inside you than builds or tactics. If rank, performance, or comments from others are stressing you out, tell me how it’s been for you.",
                    "I hear you about the game, {$first}. I might not be the best coach for strategies, but if matches, tilt, or expectations are getting heavy on your mood, we can focus on that side together.",
                ]
                : [
                    "Playing games can be a real escape, {$first}. I’m not here to break down strategies, but if you’re using games to cope or if things in-game are stressing you out, we can unpack how that feels.",
                    "Games can be fun—and also frustrating, {$first}. Lumi’s role is to support your mental health. If losing streaks, rank, or people you play with are affecting your confidence or mood, I’m here for that part.",
                    "It makes sense that games are part of your day, {$first}. I may not handle guides, but if gaming is tied to stress, pressure, or how you see yourself, you can share that with me.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== ENTERTAINMENT (movies, series, music, etc.) =====================
        case 'entertainment': {
            $key = 'nonmental_entertainment_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a question about shows or music, {$first}. I’m mainly here for your mental health, so I may not give full reviews or recommendations. But if these stories or songs are reflecting how you feel, we can talk about that.",
                    "Movies, series, and music can hit close to home, {$first}. I might not be your best critic, but if a character, scene, or song reminds you of what you’re going through, I’d like to hear about that part.",
                ]
                : [
                    "Stories, series, and music can really mirror what we feel inside, {$first}. If something you’re watching or listening to is resonating with your situation, you can tell me how it connects to you.",
                    "It’s natural to get attached to shows or songs, {$first}. If a storyline, character, or lyric is hitting you hard emotionally, we can explore why it feels that way.",
                    "Entertainment can be a big escape, {$first}. If you’re using it to cope, or if it’s making you think more about your own life, I’m here to talk about what it brings up for you.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== FOOD =====================
        case 'food': {
            $key = 'nonmental_food_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a question about food or places to eat, {$first}. Lumi focuses more on how you’re doing emotionally, so I might not be the best for detailed food suggestions. But if eating, appetite, or body image has been affecting your mood, we can talk about that.",
                    "Food can be comforting or stressful too, {$first}. I may not give full restaurant or recipe advice, but if you’re noticing changes in your appetite or eating because of stress, I’m here to listen.",
                ]
                : [
                    "Food can be a source of comfort, stress, or both, {$first}. If you’re stress-eating, losing appetite, or worrying about your body, you can tell me more about that side of it.",
                    "Eating patterns can say a lot about how we’re really doing, {$first}. If you’ve noticed changes because of school pressure, mood, or personal issues, I’m here to talk about it.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== TRAVEL =====================
        case 'travel': {
            $key = 'nonmental_travel_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a travel or trip question, {$first}. I’m mainly focused on your mental well-being, so I might not be ideal for full itineraries. But if this trip, distance, or being away is affecting how you feel, we can talk about that.",
                    "Travel can be exciting and stressful at the same time, {$first}. I may not plan every detail, but if leaving, going home, or being far from people is heavy on you, I’m here for that part.",
                ]
                : [
                    "Trips and travel can bring up a lot of feelings, {$first} — from excitement to anxiety. If there’s something about this trip that worries you or makes you feel different, we can talk about it.",
                    "Sometimes travel is an escape, sometimes it’s a pressure, {$first}. If being away from home, school, or certain people is affecting you emotionally, you can share that with me.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== SHOPPING / MONEY (neutral) =====================
        case 'shopping': {
            $key = 'nonmental_shopping_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "This sounds like a shopping or product question, {$first}. Lumi is more focused on how you’re feeling inside, so I might not be your full guide for choices. But if money, buying things, or pressure to keep up with others is stressing you, we can talk about that.",
                    "Buying things and online shopping can affect how we feel too, {$first}. I may not handle all the details, but if spending, guilt, or comparison is weighing on you, I’d like to hear about it.",
                ]
                : [
                    "Shopping can feel like a reward or a distraction, {$first}. If you notice you’re buying things to cope with stress or sadness, we can explore what’s behind that.",
                    "Money and buying stuff can be tied to stress, guilt, or pressure to fit in, {$first}. If that’s part of what you’re going through, you can tell me more.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== SPORTS / EXERCISE =====================
        case 'sports': {
            $key = 'nonmental_sports_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a sports or training question, {$first}. I’m mainly here for your mental health, so I might not coach techniques. But if performance, expectations, or your team situation is affecting your confidence or mood, we can talk about that.",
                    "Sports can come with a lot of pressure, {$first}. I may not break down plays, but if fear of failing, losing, or letting others down is stressing you out, I’m here for that.",
                ]
                : [
                    "Sports can be both a stress reliever and a source of pressure, {$first}. If competition, try-outs, injuries, or expectations are weighing on you, you can tell me about it.",
                    "How you feel in training, games, or around teammates can really impact your mental health, {$first}. If something in that area has been heavy, I’m here to listen.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== TECH / CODING =====================
        case 'tech': {
            $key = 'nonmental_tech_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "This sounds like a tech or coding issue, {$first}. Lumi is focused on your mental health, so I might not fix it step-by-step. But if bugs, deadlines, or grades around this are stressing you or making you doubt yourself, I’d like to hear about that.",
                    "Tech problems can be draining, {$first}. I may not be your full debugger, but if this issue is making you anxious, frustrated, or burned out, we can talk about how it’s affecting you.",
                ]
                : [
                    "Tech and school requirements can really wear you down, {$first}. Instead of only looking at the error, I’m here to support how it’s been affecting your mood, confidence, or energy.",
                    "Those kinds of issues can pile up mentally, {$first}. If this has been stressing you, making you feel behind, or pressuring you, you can tell me more about that side.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== CREATIVE / EDITING / DESIGN =====================
        case 'creative': {
            $key = 'nonmental_creative_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a design or editing question, {$first}. I’m here mainly for how you’re doing emotionally, so I might not give full technical tutorials. But if creative blocks, pressure to be perfect, or feedback from others is affecting you, we can talk about that.",
                    "Creating art or content can be really personal, {$first}. I may not fix every detail, but if criticism, comparisons, or burnout around your work is hurting your confidence, I’m here to listen.",
                ]
                : [
                    "Creative work can be meaningful and exhausting at the same time, {$first}. If you’re feeling stuck, judged, or pressured to always produce something good, you can share that with me.",
                    "Art and editing often connect deeply to identity and self-worth, {$first}. If that’s part of what you’re feeling, I’d like to understand it better with you.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== ACADEMICS / HOMEWORK =====================
        case 'academics': {
            $key = 'nonmental_academics_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "It sounds like an academic or homework question, {$first}. Lumi is focused more on your well-being than full solutions. If this subject, exam, or requirement is stressing you or hurting your confidence, I’d really like to hear about that part.",
                    "I get that this is about schoolwork, {$first}. I might not give a complete step-by-step answer, but if this topic, quiz, or deadline is making you anxious or overwhelmed, we can talk through how it’s affecting you.",
                ]
                : [
                    "School can get heavy fast, {$first}. I’m not a pure homework bot, but if modules, quizzes, or requirements are piling up and stressing you, tell me what’s been hardest lately.",
                    "Academics can bring a lot of pressure, {$first}. Instead of solving items one by one, I can help you explore how the load, expectations, or fear of failing has been making you feel.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== SOCIAL MEDIA =====================
        case 'social_media': {
            $key = 'nonmental_social_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds related to social media, {$first}. Lumi focuses more on your mental health than on algorithms or reach. But if likes, comments, or what you see online is affecting your self-esteem or mood, we can talk about that.",
                    "Social media can really influence how we feel about ourselves, {$first}. I might not optimize your account, but if comparison, online drama, or pressure to post is weighing on you, I’m here for that.",
                ]
                : [
                    "Social media can be both a connection and a trigger, {$first}. If what you see online is making you feel insecure, left out, or pressured, you can tell me more about it.",
                    "It’s common to compare ourselves on social media, {$first}. If that’s affecting how you see yourself or your life, we can explore those feelings together.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== MONEY / CAREER (neutral) =====================
        case 'money_career': {
            $key = 'nonmental_money_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "This sounds like a money or career topic, {$first}. I’m mainly focused on your mental health, so I may not give full financial or career planning advice. But if pressure about money, future plans, or expectations is stressing you, we can talk about that.",
                    "Thinking about money and career can be really overwhelming, {$first}. I may not handle all the practical details, but if fear of the future or disappointing others is heavy on you, I’m here to listen.",
                ]
                : [
                    "Worries about money or the future can weigh a lot on students, {$first}. If expectations, responsibilities, or uncertainty are stressing you, you can share what it’s been like.",
                    "Career and finances can bring hidden anxiety and pressure, {$first}. If that’s something you’re carrying, I’d like to understand it more with you.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== PHYSICAL HEALTH / BODY (non-emotional) =====================
        case 'health_body': {
            $key = 'nonmental_healthbody_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a physical health question, {$first}. I’m not a medical professional, so I can’t give diagnoses or treatments. But if being sick, changes in your body, or health worries are affecting your mood or stress, we can talk about that.",
                    "Health concerns can be scary and stressful, {$first}. I may not replace a doctor, but I can help you process the fear, worry, or frustration you’re feeling about it.",
                ]
                : [
                    "Being unwell or worried about your body can really affect your mental health, {$first}. If this has been making you anxious, frustrated, or discouraged, you can tell me more.",
                    "It’s normal to feel stressed when something isn’t right physically, {$first}. I may not treat the symptoms, but I’m here to support how it’s affecting you emotionally.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== SCHOOL ADMIN / ACCOUNTS / PROCESS =====================
        case 'admin_school': {
            $key = 'nonmental_admin_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "That sounds like a school process or account question, {$first}. I’m mainly focused on your mental and emotional well-being, so I might not fix the portal or forms directly. But if these requirements or system issues are stressing you or making you feel overwhelmed, we can talk about that.",
                    "Enrollment, accounts, and school processes can be really frustrating, {$first}. I may not control the system, but I can help you process the stress, irritation, or pressure that comes with it.",
                ]
                : [
                    "School processes and documents can add a lot of hidden stress, {$first}. If you feel pressured, confused, or stuck because of requirements, you can share what it’s been like.",
                    "It’s understandable to feel annoyed or drained by school admin tasks, {$first}. I may not fix the system, but I’m here to support how it’s affecting you mentally.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }

        // ===================== DEFAULT / OTHER NON-MENTAL =====================
        default: {
            $key = 'nonmental_generic_'.($isQuestion ? 'q' : 's');
            $variants = $isQuestion
                ? [
                    "Thank you for your question, {$first}. Lumi mainly focuses on your emotional and mental well-being, so I might not go deep on this topic. If it’s affecting your stress, mood, or self-confidence somehow, you can tell me more about that.",
                    "I appreciate you asking, {$first}. I may not have a full answer on this specific topic, but if it’s connected to pressure, worry, or overthinking, I’m here to talk about that part with you.",
                ]
                : [
                    "Thanks for sharing that, {$first}. Lumi stays focused on your mental and emotional health, so I might not handle all of the details here. But if this situation is stressing you or affecting how you feel, you can tell me about it.",
                    "I hear you, {$first}. I may not be the best for that exact topic, but if it’s been heavy on your mind, stressful, or affecting your mood, I’m here to listen to that side of things.",
                ];
            return $this->pickVariantForSession($sessionId, $key, $variants);
        }
    }
}

private function fallbackSupportLine(): string
{
    $options = [
        "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. If you’re comfortable, you can tell me a bit more about what’s going on.",
        "Thank you for reaching out, {USER_FIRST}. Even if it’s hard to put into words, I’m here with you. What part feels heaviest right now?",
        "I’m glad you messaged me, {USER_FIRST}. You don’t have to go through this alone. What would you like to start with?",
        "You’re not bothering me at all, {USER_FIRST}. Your feelings matter here. What’s been staying in your mind the most these days?",
        "I’m here for you, {USER_FIRST}. Sometimes just talking about it slowly can help. What’s one thing you wish someone asked you about today?",
        "Even if everything feels confusing, {USER_FIRST}, you’ve already taken a brave step by reaching out. What’s been the hardest part to carry alone?",
    ];

    return $options[array_rand($options)];
}

/**
 * Infer how the conversation is trending across messages.
 * Examples:
 *  - single_message
 *  - stable_low
 *  - rising_risk
 *  - spike_to_high
 *  - persistent_high
 */
private function analyzeTrajectory(
    \App\Models\ChatSession $session,
    string $msgRisk,
    array $sessionEmotionCounts,
    int $sessionUserMsgCount
): string {
    $prevRisk = $session->risk_level ?: 'low';

    // Very early: nothing to analyse yet
    if ($sessionUserMsgCount <= 1) {
        return 'single_message';
    }

    // Spike into high risk from lower
    if ($msgRisk === 'high' && $prevRisk !== 'high') {
        return 'spike_to_high';
    }

    // Persistent high risk
    if ($msgRisk === 'high' && $prevRisk === 'high') {
        return 'persistent_high';
    }

    // Rising from low → moderate
    if ($msgRisk === 'moderate' && $prevRisk === 'low') {
        return 'rising_risk';
    }

    // Gradual build-up: many emotional messages but still low risk
    $totalEmo = array_sum(array_map('intval', $sessionEmotionCounts));
    if ($msgRisk === 'low' && $prevRisk === 'low') {
        if ($totalEmo >= 8 && $sessionUserMsgCount >= 6) {
            return 'persistent_low_emotional';
        }
        if ($totalEmo >= 4 && $sessionUserMsgCount >= 3) {
            return 'building_emotional_load';
        }
    }

    // Moderate but staying there for a while
    if ($msgRisk === 'moderate' && $prevRisk === 'moderate') {
        if ($sessionUserMsgCount >= 6) {
            return 'persistent_moderate';
        }
        return 'stable_moderate';
    }

    // Default: nothing fancy
    return 'stable_low';
}

/**
 * Higher-level "where are we in the conversation?" classifier.
 * Used to make Rasa policies + heuristics smarter.
 */
private function detectConversationStage(
    \App\Models\ChatSession $session,
    string $msgRisk,
    array $flags,
    int $sessionUserMsgCount,
    bool $askedForAppt,
    bool $inVentWindow
): string {
    // Explicit closing signals
    if (($flags['done'] ?? false) || ($flags['goodbye'] ?? false)) {
        return 'closing';
    }

    // Crisis has priority
    if ($msgRisk === 'high' || ($session->risk_level === 'high')) {
        return 'crisis';
    }

    // Appointment flow
    if ($askedForAppt) {
        return 'appointment_flow';
    }

    // Coping tools
    if ($flags['wants_coping'] ?? false) {
        return 'coping';
    }

    // Very early stage
    if ($sessionUserMsgCount <= 1) {
        return 'opening';
    }

    // Still in the "let me vent first" window
    if ($inVentWindow) {
        return 'venting';
    }

    // Mid-phase exploration (sharing details, back-and-forth)
    if ($sessionUserMsgCount <= 4) {
        return 'exploration';
    }

    // Longer ongoing support
    if ($sessionUserMsgCount <= 10) {
        return 'ongoing_support';
    }

    // Really long conversations
    return 'long_running';
}
/**
 * Lightly decorate Lumi's reply so it feels warmer / more human,
 * based on detected emotions + risk.
 *
 * - For HIGH risk: no emojis, just extra concern if needed.
 * - For LOW/MODERATE: may add comforting / validating phrases
 *   and (for light messages) a small smile / laugh.
 */
/**
 * Lightly decorate Lumi's reply so it feels warmer / more human,
 * based on detected emotions + risk.
 *
 * - For HIGH risk: no joke emojis, just extra concern.
 * - For LOW/MODERATE: may add comforting / validating phrases
 *   and (for light messages) a small smile / laugh.
 */
private function applyEmotionTone(string $replyText, string $norm, array $labels, string $risk): string
{
    $base = trim($replyText);
    if ($base === '') {
        return $replyText;
    }

    // Normalize input for simple checks
    $normLower = mb_strtolower($norm);

    // Don’t touch explicit crisis scripts too much.
    if ($risk === 'high') {
        // Ensure we sound clearly caring if the line is very neutral.
        $lower = mb_strtolower($base);
        if (
            !str_contains($lower, 'im really glad you told me') &&
            !str_contains($lower, "i'm really glad you told me") &&
            !str_contains($lower, 'you are not alone') &&
            !str_contains($lower, 'you\'re not alone') &&
            !str_contains($lower, 'if you are in immediate danger')
        ) {
            $base .= " I'm really here with you right now, and I care about what you're going through. You don't have to face this alone.";
        }
        return $base;
    }

    // Normalize labels for easy checks
    $labels = array_map('strtolower', $labels);
    $has    = function (string $label) use ($labels): bool {
        return in_array($label, $labels, true);
    };

    // Strong painful emotions → validating / comforting tone
    if ($has('sad') || $has('lonely') || $has('hopeless') || $has('not_ok')) {
        if (
            !str_contains($base, 'You\'re not alone') &&
            !str_contains($base, 'You are not alone') &&
            !str_contains($base, 'I\'m here')
        ) {
            $base .= " I'm here with you, and you're not alone in feeling this way. 💜";
        }
        return $base;
    }

    if ($has('anxious') || $has('stressed') || $has('overwhelmed') || $has('tired')) {
        if (!str_contains(mb_strtolower($base), 'one step')) {
            $base .= " What you're feeling makes complete sense given everything you're dealing with. Let's take this one small step at a time. 🤍";
        }
        return $base;
    }

    if ($has('angry')) {
        if (!str_contains(mb_strtolower($base), 'frustrat')) {
            $base .= " I can really feel how frustrating this is, and that reaction makes total sense. It's okay to feel angry. 😤";
        }
        return $base;
    }

    if ($has('guilty') || $has('ashamed')) {
        $lower = mb_strtolower($base);
        if (
            !str_contains($lower, "doesn't make you a bad") &&
            !str_contains($lower, 'does not make you a bad')
        ) {
            $base .= " Feeling this way doesn't make you a bad person — it actually shows how much you care. You're being so hard on yourself. 💭";
        }
        return $base;
    }

    // ========== LIGHTER FEELINGS / POSITIVE MOMENTS ==========

    // 1) Laughter / joke → small laugh emoji (only for low risk)
    if ($risk === 'low') {
        if (preg_match('/\b(lol|lmao|haha+|hehe+|joke|funny)\b/u', $normLower)) {
            // Avoid double emojis
            if (!preg_match('/[\x{1F600}-\x{1F64F}]/u', $base)) {
                $base .= " 😄";
            }
            return $base;
        }
    }

    // 2) Gratitude / relief → warm, supportive smile
    if ($risk !== 'high') {
        if (preg_match('/\b(thanks?|thank you|salamat|appreciate it)\b/u', $normLower)) {
            if (!preg_match('/[\x{1F600}-\x{1F64F}]/u', $base)) {
                $base .= " Of course! I'm really glad I could be here for you. That's what I'm here for — you're never bothering me. 🙂";
            } else {
                // If there is already an emoji, just add the line without another face
                $base .= " Of course! I'm really glad I could be here for you. That's what I'm here for.";
            }
            return $base;
        }

        // Student says they feel a bit better / okay now
        if (preg_match('/\b(better now|feel better|feeling better|okay now|ok na ako|medyo ok na)\b/u', $normLower)) {
            if (!preg_match('/[\x{1F600}-\x{1F64F}]/u', $base)) {
                $base .= " That's so good to hear! I'm really happy about that. And remember, I'm still here whenever you need — even if it's just to talk. 😊";
            } else {
                $base .= " That's so good to hear! I'm really happy about that. And I'm still here whenever you need.";
            }
            return $base;
        }
    }

    // Default: leave text as-is
    return $base;
}


}
