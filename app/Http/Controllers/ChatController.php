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

class ChatController extends Controller
{
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

    private function evaluateRiskLevel(string $text): string
    {
        $t = RiskHeuristics::normalizeMsg($text);
        $t = preg_replace('/\s+/u', ' ', $t ?? '');

        // ===== HIGH =====
        // Direct phrases
        $high = [
            '\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b',
            '\bi\s*(?:wanna|want(?:\s*to)?|plan|planning|intend|need|will|gonna)\s*(?:to\s*)?(?:die|kill myself|end (?:it|my life)|commit suicide|unalive|disappear|be gone)\b',
            '\b(?:kill myself|commit suicide|end it all|no reason to live|life is pointless)\b',
            '\bi\s*(?:wish|want)\s*(?:i\s*)?(?:were|was)\s*dead\b',
            '\bi\s*(?:can\'?t|cannot)\s*go on\b',
            '\b(?:jump off|overdose|poison myself|hang myself)\b',
            '\b(?:self[- ]harm|cut(?:ting)? myself)\b',
        ];

        // respect negation for "die"
        $negatedDie = (bool) preg_match('/\b(?:don\'?t|do\s+not)\s+i\s+[^.?!]*\bdie\b/iu', $t);
        foreach ($high as $p) {
            if ($p === '\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b') {
                if (!$negatedDie && preg_match('/'.$p.'/iu', $t)) return 'high';
            } else if (preg_match('/'.$p.'/iu', $t)) {
                return 'high';
            }
        }

        // Co-occurrence with proximity (intent within ~8 words of act), both orders
        $acts   = '(suicide|die|unalive|kill myself|end my life|end it|jump|overdose|poison|cut|disappear|be gone)';
        $intent = '(wanna|want|plan|planning|thinking|feel like|i should|i will|i might|really want|gonna|need)';
        if (
            preg_match('/\b' . $intent . '\b(?:\W+\w+){0,8}?\b' . $acts . '\b/iu', $t)
            || preg_match('/\b' . $acts . '\b(?:\W+\w+){0,8}?\b' . $intent . '\b/iu', $t)
        ) {
            return 'high';
        }

        // ===== MODERATE =====
        $moderate = [
            '\bi\s*(?:hate|loath|despise)\s*myself\b',
            '\b(?:i (?:want|wish) (?:to )?disappear|i (?:don\'?t|do not) want to exist|i wish i wasn\'?t here|i wish i never existed)\b',
            '\b(?:i(?:\'m| am)? (?:not ?ok(?:ay)?|empty|worthless|a burden|beyond help))\b',
            '\b(?:give up on life|i don\'?t want to live|i feel like dying)\b',
            '\b(?:depress(?:ed|ing)?|anxious|panic|overwhelmed|burnout|stressed)\b',
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
        // Intentionally blank in this version
    }

    private function wantsAppointment(string $text): bool
    {
        $t = mb_strtolower($text);

        // ===== Strong, explicit patterns (English only) =====
        $strong = [
            // ENG: action near counselor
            '/\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b[\s\S]{0,80}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b[\s\S]{0,80}\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b/iu',
            '/\b(i\s+want|i\'?d\s+like|can\s+i|please)\b[\s\S]{0,40}\b(schedule|book|appointment)\b[\s\S]{0,40}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\bsee\s+(?:a\s+)?counselor\b/iu',
        ];
        foreach ($strong as $r) if (preg_match($r, $t)) return true;

        // ===== Softer patterns: action + counselor somewhere in the text =====
        $hasAction     = (bool) preg_match('/\b(appoint(?:ment)?|schedule|book(?:ing)?|reserve|set\s*(?:an?|up)?\s*appointment)\b/iu', $t);
        $hasCounselor  = (bool) preg_match('/\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu', $t);

        if ($hasAction && $hasCounselor) return true;

        return false;
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
        $userId = Auth::id();

        // If we were asked to start fresh (via New Chat), we just respect it.
        // DO NOT clear it here; let store()/activate() clear it when a session is really chosen.
        $startFresh = (bool) session('start_fresh', false);

        $activeId = session('chat_session_id');

        // Auto-attach latest session ONLY if:
        // - there is no activeId
        // - and we are NOT explicitly in "fresh" mode from New Chat
        if (!$activeId && !$startFresh) {
            $latest = ChatSession::where('user_id', $userId)
                ->latest('updated_at')
                ->first();

            if ($latest) {
                session(['chat_session_id' => $latest->id]);
                $activeId = $latest->id;
            }
        }

        // Show greeting page if there is no active session
        $showGreeting = !$activeId;

        $chats = collect();
        if ($activeId) {
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
        }

        // you don't actually use $thread in the blade, so either remove or keep if needed
        $thread = null;

        // also pass $isLocked if you still use it for the banner
        $isLocked = false;

        return view('chat', compact('chats', 'showGreeting', 'thread', 'isLocked'));
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
        $text       = trim(preg_replace('/\s+/u', ' ', $cleanInput));  // <-- payload sent to Rasa

        $rawDisplay   = (string) $request->input('display_text', '');
        $cleanDisplay = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawDisplay);
        $display      = trim(preg_replace('/\s+/u', ' ', $cleanDisplay)); // <-- nice label for UI

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

        // ===== 2.a) Seed a persistent greeting for this fresh session =====
        if ($justCreated) {
            $welcomeText = "Hi {$first}! I’m Lumi — how can I help you today?";

            // Avoid duplicates if something runs twice accidentally
            $alreadyHasBot = Chat::where('chat_session_id', $sessionId)
                ->where('sender', 'bot')
                ->exists();

            if (!$alreadyHasBot) {
                Chat::create([
                    'user_id'         => $userId,
                    'chat_session_id' => $sessionId,
                    'sender'          => 'bot',
                    'message'         => \Illuminate\Support\Facades\Crypt::encryptString($welcomeText),
                    'sent_at'         => now()->subSecond(),
                ]);
            }
        }

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
                    // ✅ Save the *label* or normal text, NOT the payload
                    'message'         => \Illuminate\Support\Facades\Crypt::encryptString($storedText),
                    'sent_at'         => now(),
                ]
            );
        } catch (\Illuminate\Database\QueryException $e) {
            $userMsg = Chat::where('idempotency_key', $idem)->first();
            if (!$userMsg) throw $e;
        }

        // ===== 3.b) Topic summary / emotion counts =====
        $count = Chat::where('chat_session_id', $sessionId)->where('sender', 'user')->count();
        if ($count === 1) {
            preg_match('/\b(sad|depress|help|anxious|angry|lonely|stress|tired|happy|excited|not okay)\b/i', $analysisText, $m);
            $summary = $m[0] ?? \Illuminate\Support\Str::limit($analysisText, 40, '…');
            $session->update(['topic_summary' => ucfirst($summary)]);
        }
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

        // ===== 4) Decline-referral (SOFT RESET – no lock, keep chat open) =====
        $declinedReferral = (function (string $s): bool {
            $t = trim(mb_strtolower($s));
            if (preg_match('~^/deny\s*(\{.*\})?~i', $t, $m)) {
                if (!empty($m[1])) {
                    try {
                        $j = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
                        if (isset($j['confirm_topic']) && strtolower($j['confirm_topic']) === 'referral') {
                            return true;
                        }
                    } catch (\Throwable $e) {}
                }
                return true;
            }
            return (bool) preg_match('/\b(not now|no thanks|maybe later|pass for now)\b/u', $t);
        })($analysisText);

        if ($declinedReferral) {
            $ventKey = 'vent_turns_for_session_' . $sessionId;
            session([$ventKey => 0]);
            session()->forget('crisis_prompted_for_session_' . $sessionId);

            $closing1 = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => \Illuminate\Support\Facades\Crypt::encryptString(
                    "No problem. Thank you for sharing with me today."
                ),
                'sent_at'         => now(),
            ]);

            $closing2 = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => \Illuminate\Support\Facades\Crypt::encryptString(
                    "If you ever feel like talking again, you can just send another message and we’ll start from there."
                ),
                'sent_at'         => now(),
            ]);

            return response()->json([
                'user_message' => [
                    'text'       => $storedText, // ✅ show label, not payload
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

        // ===== 5) Flow control (fix long-vent bypass + loops) =====
        $botReplies = [];
        $rasaUrl    = $this->rasaWebhookUrl();

        // Track emotional vent turns separately so non-mental topics don’t consume them
        $ventKey   = 'vent_turns_for_session_'.$sessionId;
        $ventTurns = (int) session($ventKey, 0);

        $lastIntent = $this->lastBotIntent($sessionId);

        // Appointment confirm via "yes" after an offer
        $askedForAppt =
            ($flags['wants_appointment'] ?? false)
            || (($flags['yes'] ?? false) && $lastIntent === 'offer_appointment')
            || $this->confirmedAfterOffer($analysisText, $sessionId);

        // Self-threat overrides to HIGH unless they’re explicitly just booking
        if ($selfThreat && !$askedForAppt) {
            $msgRisk = 'high';
        }

        // Bypass decision: protects long vents from skipping to Rasa
        $bypass           = $this->shouldBypassVenting_EN($norm, $msgRisk, $flags);
        $inEmotionalRange = ($msgRisk !== 'low') || !empty($labels);

        // Use ventTurns so non-mental messages don’t eat the 3-turn window
        $inVentWindow     = $inEmotionalRange && ($ventTurns < 3) && !$bypass;

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
        } elseif ($nonMental) {
            $messageType = 'non_mental';
        } elseif ($selfThreat || $msgRisk === 'high') {
            $messageType = 'crisis';
        } elseif ($askedForAppt) {
            $messageType = 'appointment_request';
        } elseif ($flags['wants_coping'] ?? false) {
            $messageType = 'coping_request';
        } elseif ($inVentWindow && !empty($labels)) {
            $messageType = 'emotional_vent';
        } elseif ($flags['is_question'] ?? false) {
            $messageType = 'question';
        }

        // Session-level stats for smarter context
        $sessionEmotionCounts = $this->emotionsAsCounts($session->emotions ?? []);
        $sessionUserMsgCount  = Chat::where('chat_session_id', $sessionId)
            ->where('sender', 'user')
            ->count();

        // Conversation stage for policies / smarter replies
        $conversationStage = 'opening';
        if ($sessionUserMsgCount <= 1) {
            $conversationStage = 'opening';
        } elseif ($msgRisk === 'high' || ($session->risk_level === 'high')) {
            $conversationStage = 'crisis';
        } elseif ($askedForAppt) {
            $conversationStage = 'appointment_flow';
        } elseif ($flags['wants_coping'] ?? false) {
            $conversationStage = 'coping';
        } elseif ($inVentWindow && !$nonMental) {
            $conversationStage = 'venting';
        } elseif ($nonMental) {
            $conversationStage = 'out_of_scope';
        }

        // Build metadata (rich for Rasa policies)
        $metadata = [
            'lumichat' => [
                'session_id' => $sessionId,
                'lang'       => 'en',              // controller is EN-only NLU
                'risk'       => $msgRisk,         // message-level risk
                'app'        => 'lumichat-web',

                // Current message analysis
                'message'    => [
                    'type'        => $messageType,
                    'risk'        => $msgRisk,
                    'self_threat' => $selfThreat,
                    'labels'      => $labels,        // emotion labels for this message
                    'flags'       => $flags,         // wants_appointment, wants_coping, is_question, refused_to_share, yes/no, done
                    'scores'      => $scores,        // length + confidence-ish
                ],

                // Session-wide context for smarter flows
                'session'    => [
                    'stage'             => $conversationStage,   // opening / venting / coping / appointment_flow / crisis / out_of_scope
                    'overall_risk'      => $session->risk_level ?: 'low',
                    'topic_summary'     => $session->topic_summary,
                    'emotion_counts'    => $sessionEmotionCounts, // map: label => count
                    'user_message_count'=> $sessionUserMsgCount,
                    'vent_turns'        => $ventTurns,
                    'in_vent_window'    => $inVentWindow,
                    'non_mental_topic'  => $nonMental,
                    'asked_for_appt'    => $askedForAppt,
                ],

                // For Rasa policies that want last bot intent tag
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
        $rasaMessage = $text; // default: send the actual user message

        if ($flags['done'] ?? false) {
            // Reset vent counter so next time they talk, stage 1 again
            $ventKey = 'vent_turns_for_session_'.$sessionId;
            session([$ventKey => 0]);

            // 1) Closing / appreciation line
            $botReplies[] = [
                'text'    => "Thank you for sharing with me today, {USER_FIRST}. If you ever want to talk again or try more coping tools, you can just send another message and we’ll continue from there.",
                'buttons' => [],
            ];

            // 2) Optional counselor booking offer
            $botReplies[] = [
                'text'    => "And if at any point you’d like to talk to a school counselor in person, you can book an appointment here:",
                'buttons' => [
                    ['title' => 'Book counselor', 'payload' => '{APPOINTMENT_LINK}'],
                ],
            ];
        } elseif ($unreadable) {
            // 🔹 Default response for unclear / not understandable input
            $botReplies[] = [
                'text'    => "I’m not sure I understood that, {USER_FIRST}. Could you say it in a simpler or clearer way so I can support you better?",
                'buttons' => [],
            ];
        } elseif ($nonMental) {
            // Separate response for topics outside LumiCHAT’s scope
            $botReplies[] = [
                'text'    => "I might not be the best fit for that topic, {USER_FIRST}. LumiCHAT is focused on supporting your mental health and well-being—things like stress, emotions, and what you’re going through. If this situation is affecting how you feel, you can tell me more about that and we can talk about it.",
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
                    ['title' => 'Not now',        'payload' => '/deny{"confirm_topic":"referral"}'],
                ],
            ];
        } elseif ($inVentWindow && !$askedForAppt && $msgRisk !== 'high') {
            // Stage is based on emotional vent turns, not total user messages
            $stage     = max(1, min(3, $ventTurns + 1));
            $replyText = $this->empathicPrompt($first, $labels, $stage);
            $botReplies[] = ['text' => $replyText, 'buttons' => []];

            // Mark that we’ve used one venting turn for this session
            session([$ventKey => $ventTurns + 1]);
        } elseif (
            (
                ($flags['wants_coping'] ?? false)
                || (($flags['yes'] ?? false) && $lastIntent === 'offer_coping')
            )
            && $canOfferCoping
        ) {
            // ✅ Coping request or "Yes, show tips"
            session([$copingThrottleKey => $nowEpoch]);
            $callRasa    = true;
        } else {
            // Normal path (including questions, appointments, or risk/high cases): call Rasa
            $callRasa    = true;
            $rasaMessage = $text;
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
                    'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more?"
                ]];
            }

            if (empty($botReplies)) {
                $botReplies = [[
                    'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more?"
                ]];
            }
        }

        // ===== 7) Risk elevation logging =====
        $current = $session->risk_level ?: 'low';
        $order   = ['low' => 0, 'moderate' => 1, 'high' => 2];
        $new     = ($order[$msgRisk] > $order[$current]) ? $msgRisk : $current;
        if ($new !== $current && !$nonMental) {
            $session->update(['risk_level' => $new]);
        }

        // Only log risk events for mental-health-related messages
        if (!$nonMental) {
            $this->logActivity('risk_detected', "Risk level: {$msgRisk}", $sessionId, [
                'risk_level'      => $msgRisk,
                'message_preview' => Str::limit($text, 120),
            ]);
        }

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

            // English only + inline link replace
            if (str_contains($replyText, '{APPOINTMENT_LINK}')) {
                $replyText = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $replyText);
            }

            // personalization
            $safeName  = e($name);
            $safeFirst = e($first);
            $replyText = str_replace(
                ['{USER_NAME}', '{USER_FIRST}', '{USER}', '{NAME}'],
                [$safeName,     $safeFirst,     $safeFirst, $safeName],
                $replyText
            );

            // buttons: turn payload "{APPOINTMENT_LINK}" into url $link
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

            // save bot message (encrypted)
            $bot = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => Crypt::encryptString($replyText),
                'sent_at'         => now(),
            ]);

            // respond with id + buttons so the UI can render & rehydrate
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
                'text'       => $storedText, // ✅ label / plain text
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

    /** English-only reflective prompt (no Rasa) for early vent window. */
    private function empathicPrompt(string $first, array $labels, int $stage): string
    {
        $first = trim($first) !== '' ? $first : 'there';

        $top = array_values(array_unique(array_slice($labels, 0, 2)));
        $mirror = '';
        if (!empty($top)) $mirror = ' — ' . implode(' & ', array_map(fn($x)=>strtolower($x), $top));

        $primary = strtolower($top[0] ?? '');
        $validate = [
            'sad'          => "That sounds really heavy",
            'anxious'      => "That sounds tense and overwhelming",
            'stressed'     => "That’s a lot to carry at once",
            'tired'        => "You must be exhausted",
            'angry'        => "I can hear the frustration",
            'lonely'       => "Feeling alone can hurt a lot",
            'hopeless'     => "When things feel hopeless, it can be scary",
            'not_ok'       => "It makes sense that you’re not feeling okay",
            'disappointed' => "It’s tough when expectations fall through",
            'hurt'         => "Being hurt like that really stings",
            'confused'     => "It’s okay not to have it all figured out",
            'bored'        => "Feeling stuck can be draining",
            'jealous'      => "Those feelings can be hard to sit with",
            'ashamed'      => "Shame is a heavy feeling to carry",
            'guilty'       => "Guilt can take up a lot of space",
            'overwhelmed'  => "Everything piling up can feel like too much",
        ];
        $v = $validate[$primary] ?? "Thank you for trusting me with this";

        switch ($stage) {
            case 1:
                return "{$v}{$mirror}, {USER_FIRST}. I’m really glad you told me. You don’t have to hold anything back here. If you feel okay to share, what’s been weighing on you the most right now?";

            case 2:
                return "I’m here with you, no rush at all. When you’re ready, could you tell me what was happening before these feelings started? Even a little detail is okay.";

            default:
                return "Thank you for opening up, {USER_FIRST}. We can stay with what you’re feeling, or—only if you want—we can try a small next step. Would you rather keep talking, take a moment to slow your breathing together, or think of one small thing you wish felt a bit better this week?";
        }
    }

    /** Heuristic: should we bypass vent window and go straight to Rasa? */
    private function shouldBypassVenting(string $text, bool $selfThreat, string $risk, bool $askedForAppt): bool
    {
        // Safety and explicit action always bypass
        if ($risk === 'high' || $selfThreat) return true;
        if ($askedForAppt) return true;

        // Normalize
        $t = trim(mb_strtolower($text));

        // Real question if it ends with "?" OR clearly starts as a question
        $hasQmark       = str_contains($t, '?');
        $startsWithWh   = (bool) preg_match('/^\s*(how|what|why|can|should|where|when|who|which)\b/u', $t);

        // False-positives we should NOT treat as questions (e.g., "i don't know why ...")
        $dontKnowClause = (bool) preg_match('/\bi\s*(don\'?t|do\s+not)\s+know\s+(why|what|how)\b/u', $t);

        // If it’s a real question AND not the “don’t know why/what/how” clause → bypass
        $isRealQuestion = ($hasQmark || $startsWithWh) && !$dontKnowClause;

        return $isRealQuestion;
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

    /** Detects when the student prefers not to share right now. */
    private function refusedToShare(string $raw): bool
    {
        $t = mb_strtolower(trim($raw));

        // Explicit quick-action / Rasa-style
        if (preg_match('~^/(deny|refuse|skip)\s*(\{.*\})?~i', $t, $m)) {
            if (!empty($m[2])) {
                try {
                    $j = json_decode($m[2], true, 512, JSON_THROW_ON_ERROR);
                    $topic = strtolower((string) ($j['confirm_topic'] ?? $j['topic'] ?? ''));
                    if (in_array($topic, ['vent', 'venting', 'share', 'sharing'], true)) return true;
                } catch (\Throwable $e) { /* ignore */ }
            }
            // plain /deny also counts as a refusal to share right now
            return true;
        }

        // Natural-language refusals (keep broad, but non-judgmental)
        $rules = [
            '/\b(i\s*(don\'?t|do\s*not)\s*(want|feel like)\s*(to\s*)?(talk|share|say|open up))\b/u',
            '/\b(prefer\s*not\s*to\s*(say|share|talk))\b/u',
            '/\b(not\s*now|maybe\s*later|not\s*ready)\b/u',
            '/\b(nothing\s*(to\s*)?say|i\'?m\s*fine\s*for\s*now)\b/u',
            '/\b(skip|pass)\b/u',
        ];
        foreach ($rules as $r) if (preg_match($r, $t)) return true;

        return false;
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

    /** Soft matcher: exact word OR edit distance <=1 for short terms (<=10 chars). */
    private function hasAnyWord(string $text, array $terms): bool
    {
        $toks = $this->tokens($text);
        if (empty($toks)) return false;
        foreach ($terms as $term) {
            $term = mb_strtolower($term);
            $len  = mb_strlen($term);
            foreach ($toks as $tok) {
                if ($tok === $term) return true;
                if ($len <= 10 && abs($len - mb_strlen($tok)) <= 1) {
                    if (levenshtein($tok, $term) <= 1) return true; // tolerate a single typo
                }
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

    /** Rich risk assessment with typo/slang coverage. */
    private function assessRisk(string $raw): array
    {
        $t = $this->nluNormalize($raw);

        // Acronyms/slang to expand matching (kms = kill myself, etc.)
        $slangHigh = [
            'kms','end it','end it all','unalive','i can\'t go on','can\'t go on','no reason to live',
            'life is pointless','nothing to live for'
        ];
        foreach ($slangHigh as $s) if (preg_match('/\b'.$this->flex($s).'\b/u',$t)) return ['level'=>'high','hits'=>[$s]];

        // High (explicit self-harm/suicide) + common misspellings
        $highPhrases = [
            'kill myself','kill my self','commit suicide','sui cide','suicide','sucide','suicde','suiside',
            'end my life','end myl ife','i want to die','i wanna die','wish i was dead','wish i were dead',
            'overdose','hang myself','jump off','cut myself','self harm','self-harm','hurt myself on purpose'
        ];
        foreach ($highPhrases as $p) if (preg_match('/\b'.$this->flex($p).'\b/u',$t)) return ['level'=>'high','hits'=>[$p]];

        // Proximity: intent ~ act within 8 tokens, tolerant to typos
        $intent = '(wanna|want|plan|planning|think|thinking|feel like|should|will|might|need|tryna|trying)';
        $act    = '(suic(?:ide|de|e)|die|unalive|kill\s*my\s*self|end\s*my\s*life|end\s*it|overdose|hang|jump|cut|self[- ]?harm|hurt\s*my\s*self)';
        if (
            preg_match('/\b'.$intent.'\b(?:\W+\w+){0,8}\b'.$act.'\b/iu',$t) ||
            preg_match('/\b'.$act.'\b(?:\W+\w+){0,8}\b'.$intent.'\b/iu',$t)
        ) {
            return ['level'=>'high','hits'=>['intent+act']];
        }

        // Moderate (expanded + typos)
        $moderate = [
            'i hate myself','i hat myself','i hte myself',
            'i want to disappear','i wanna disappear','i don\'t want to exist','i dont want to exist',
            'feel like dying','feel lik dyin','i am worthless','worthles','i am a burden','burdn',
            'empty inside','i\'m empty','numb all the time','tired of everything','done with everything',
            'overwhelmed','overwhelm','burnout','panic','anxiety','depressed','depressd','depresed',
            'i am not okay','im not ok','not okey'
        ];
        foreach ($moderate as $p) if (preg_match('/\b'.$this->flex($p).'\b/u',$t)) return ['level'=>'moderate','hits'=>[$p]];

        return ['level'=>'low','hits'=>[]];
    }

 /** Broad emotion tagging (with misspellings & synonyms). */
private function labelEmotions(string $raw): array
{
    $t      = $this->nluNormalize($raw);
    $labels = [];

    $map = [
        'sad' => [
            'sad','down','blue','low','tearful','cry','crying','grief','heartbroken',
            'depressed','depressd','depresed','deprssd','deprsd',
            // disappointment also feels like sadness
            'disappoint','disappointed','dissapointed','dissappointed','disapointed',
        ],
        'disappointed' => [
            // explicit disappointed label so empathicPrompt("disappointed") can fire
            'disappoint','disappointed','dissapointed','dissappointed','disapointed',
            'let down','letdown',
        ],
        'anxious' => [
            'anxious','anxiety','anxety','anxios','panicky','panic','afraid','scared',
            'nervous','on edge','worried',
        ],
        'stressed' => [
            'stress','stressed','pressure','overwhelm','overwhelmed','burnout','burn out',
        ],
        'tired' => [
            'tired','exhausted','fatigued','fatigue','drained','worn out','burned out',
        ],
        'angry' => [
            'angry','mad','furious','rage','irritated','annoyed','frustrated',
        ],
        'lonely' => [
            'lonely','alone','isolated','left out','no one understands',
        ],
        'hopeless' => [
            'hopeless','pointless','no hope','give up','giving up','worthless',
        ],
        'not_ok' => [
            'not ok','not okay','not fine','not okey','i am not okay','i\'m not ok',
        ],
        'overwhelmed' => [
            'overwhelmed','can\'t cope','cannot cope','too much',
        ],
        'guilty' => ['guilty','guilt'],
        'ashamed' => ['ashamed','shame','embarrassed'],
        'confused' => ['confused','lost','unsure','uncertain'],
        'bored' => ['bored','boredom','meh','indifferent'],
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

        // ---------------- Appointment intent ----------------
        // Primary rule: action + counselor/therapist/advisor
        $action  = '(appoint(?:ment)?|apointment|schedule|schedual|book(?:ing)?|bok|reserve|set\s*up)';
        $role    = '(counsel(?:or|ler)|counsellor|councelor|counslor|therap(?:ist|y)|advisor|someone to talk)';

        $wantsAppt = (bool) (
            (preg_match('/\b'.$action.'\b/iu', $t) && preg_match('/\b'.$role.'\b/iu', $t))
            || preg_match('/\bsee\s+(?:a\s+)?'.$role.'\b/iu', $t)
        );

        // Fallback for LumiCHAT context:
        // Things like "i want to book", "can I book", "i want to schedule"
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

        // Rasa-style payloads, e.g. /coping_done or /done_coping
        if (preg_match('~/(coping_done|done_coping|finish_coping)~i', $t)) {
            $done = true;
        }

        // Natural language: "done", "done for now", "I'm okay now", "that's enough", etc.
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

        // Extra: treat very short "done" messages as done
        if (!$done) {
            $short = trim($t);
            if (in_array($short, ['done'], true)) {
                $done = true;
            }
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

        // 1.a) GREETINGS: "hi", "hello", "hey", etc. should NOT be treated as non-mental.
        //     This is exactly what was blocking your greeting.
        if ($this->hasAnyWord($norm, [
            'hi','hello','hey','helo','hii',
            'good morning','good afternoon','good evening'
        ])) {
            // short, non-question greeting → let Rasa / venting handle it
            if (mb_strlen($norm) <= 40 && !($flags['is_question'] ?? false)) {
                return false;
            }
        }

        // If the text already obviously talks about problems / struggle, don't treat as non-MH
        if ($this->hasAnyWord($norm, [
            'stress','stressed','anxiety','anxious','depress','sad','overwhelmed',
            'lonely','tired','burnout','panic','problem','problems','struggle','struggling',
            'suicide','selfharm','self-harm',
            // disappointment and "feel" = clearly emotional
            'disappoint','disappointed','dissapointed','dissappointed','disapointed',
            'feel','feeling','feels',
        ])) {
            return false;
        }

        // Keywords that usually indicate general / non-mental-health / factual topics
        $nonMentalKeywords = [
            // games / entertainment
            'game','games','gaming','steam','valorant','dota','gta','minecraft','roblox', 'music', 'answer',
            'ml','mobile','legends','cod','call of duty',
            'movie','movies','film','kdrama','anime','series','netflix','tiktok','youtube',

            // food, recipes
            'food','recipe','cook','cooking','restaurant','milk tea','coffee shop',

            // school / academic but not emotional
            'math','algebra','calculus','physics','chemistry','biology','science',
            'assignment','homework','module','report','thesis','definition','define',
            'meaning','meaning of','explain','explanation','what is','who is','where is',

            // tech / coding
            'programming','coding','code','javascript','python','php','laravel',
            'html','css','react','website','computer',

            // random factual / how-to
            'capital','history of','tutorial','how to make','steps to','requirements',
        ];

        // Normal non-mental topics (e.g., "I want to learn to code")
        if ($this->hasAnyWord($norm, $nonMentalKeywords)) {
            return true;
        }

        // Fallback 1: no alphabetic tokens at all (e.g., "12345", "😅😅😅")
        $tokens = $this->tokens($norm);
        if (empty($tokens)) {
            // no emotional signal + no mental-health words + unreadable → treat as out-of-scope
            return true;
        }

        // Fallback 2: very short / random text with no obvious meaning
        // e.g., "wasdwada", "asd", "qwe qwe"
        if (count($tokens) <= 3 && mb_strlen($norm) <= 40) {
            // If it doesn't even mention basic help/feelings words, treat as non-mental
            if (!$this->hasAnyWord($norm, [
                'help','support','counselor','counselling','counseling',
                'problem','problems','sad','anxious','anxiety','depress','stress','worried',
                // treat disappointment + "feel" as mental
                'disappoint','disappointed','dissapointed','dissappointed','disapointed',
                'feel','feeling','feels',
            ])) {
                return true;
            }
        }
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
    $whitelistTokens = ['hi','hey','hello','ok','okay','yes','no','hmm','lol','sad','help','me','you','i'];
    foreach ($tokens as $tk) {
        if (in_array(mb_strtolower($tk), $whitelistTokens, true)) {
            return false;
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

}
