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
    // Reuse the richer assessRisk() logic so we only maintain one source of truth.
    $risk = $this->assessRisk($text);
    return $risk['level'] ?? 'low';
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
    $userId  = Auth::id();

    // 👇 Only use whatever is explicitly in the session.
    // Do NOT auto-attach the latest session anymore.
    $activeId = session('chat_session_id');

    // If there's no active session, we show the greeting/blank state.
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
    $thread  = null;
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
    // What kind of non-mental topic is this? (games / tech / food / school / etc.)
    $category   = $this->classifyNonMentalCategory($norm);
    $isQuestion = $flags['is_question'] ?? false;

    $replyText = '';

    switch ($category) {
        case 'games':
            if ($isQuestion) {
                $replyText =
                    "Playing and talking about games is totally normal, {$first}. "
                    ."I’m mainly focused on your mental health and well-being, so I might not be the best at game guides or meta strategies. "
                    ."If gaming, ranks, or pressure from your teammates has been affecting your mood or stress, we can talk about that side of it.";
            } else {
                $replyText =
                    "Playing video games can be a fun break, {$first}. "
                    ."Lumi is focused on your mental health, so I might not give detailed game tips. "
                    ."But if you’re using games to escape, relax, or if losing/rank pressure is stressing you out, you can tell me more about how it feels.";
            }
            break;

        case 'entertainment':
            $replyText =
                "Movies, series, and shows can be a big part of how we unwind, {$first}. "
                ."I’m not a full review or recommendation bot, but if something you watched is making you overthink, feel triggered, or reminding you of your own situation, we can explore those feelings together.";
            break;

        case 'food':
            $replyText =
                "Food, cravings, and what we eat are all normal to talk about, {$first}. "
                ."I’m more focused on emotions than recipes, but if appetite, body image, or guilt around eating has been bothering you, I’m here to listen to that part.";
            break;

        case 'travel':
            $replyText =
                "Planning trips or thinking about travel can be exciting, {$first}. "
                ."I can’t really help with booking or itineraries, but if you’re hoping this trip will help you rest from stress—or you’re anxious about going—I can support you with what you’re feeling about it.";
            break;

        case 'shopping':
            $replyText =
                "Shopping and online orders can be fun, and sometimes stressful too, {$first}. "
                ."I can’t track parcels or manage payments, but if money, spending, or waiting for things is making you worried or pressured, we can talk about that.";
            break;

        case 'sports':
            $replyText =
                "Sports and exercise can really affect confidence, pressure, and energy, {$first}. "
                ."I’m not a performance coach, but if games, training, or expectations from others are affecting how you feel, I’m here to support you with that side of it.";
            break;

        case 'tech':
            if ($isQuestion) {
                $replyText =
                    "It sounds like you’re asking something technical (coding, apps, devices), {$first}. "
                    ."Lumi is focused on your mental health, so I might not be able to walk through full tech fixes here. "
                    ."If tech issues, deadlines, or school projects around this are stressing you out, we can talk about that pressure and how it’s been affecting you.";
            } else {
                $replyText =
                    "Tech, coding, and devices can be really frustrating or tiring sometimes, {$first}. "
                    ."I’m not a full technical support bot, but if errors, requirements, or expectations around this are stressing you or making you doubt yourself, you can share more about that and I’ll focus on how you’re feeling.";
            }
            break;

        case 'creative':
            $replyText =
                "Creative work like drawing, editing, or design can be a big outlet—and also a source of pressure, {$first}. "
                ."I might not be able to give full tutorials, but if you’re dealing with burnout, self-doubt, or pressure to be “good enough” with your work, we can talk about that.";
            break;

        case 'academics':
            if ($isQuestion) {
                $replyText =
                    "It sounds like an academic or homework question, {$first}. "
                    ."Lumi is here mainly to support your mental and emotional well-being, so I might not give a full solution or full lecture-style explanation. "
                    ."If this subject, exam, or requirement is stressing you, making you feel overwhelmed, or hurting your confidence, we can talk about that part so you’re not carrying it alone.";
            } else {
                $replyText =
                    "School topics and requirements can be heavy, {$first}. "
                    ."I’m not a full homework or exam-solver, but if you’re feeling pressured, burned out, or discouraged about your studies, you can tell me more about that and we’ll focus on how it’s affecting you.";
            }
            break;

        case 'social_media':
            $replyText =
                "Social media and online stuff can really influence how we feel about ourselves, {$first}. "
                ."I might not be updated on every trend or drama, but if posts, comments, or comparisons online are affecting your mood, self-esteem, or stress, we can talk about that impact on you.";
            break;

        case 'money_career':
            $replyText =
                "Money, work, and future plans can be stressful topics, {$first}. "
                ."I can’t give official financial or career decisions, but I can support you if you’re feeling pressured, worried about your future, or overwhelmed by expectations around these things.";
            break;

        case 'health_body':
            $replyText =
                "Physical health and body concerns are important, {$first}. "
                ."I’m not a medical bot and can’t give medical advice—seeing a health professional is still important. "
                ."But if what you’re going through physically is affecting your mood, energy, or self-esteem, I’m here to listen to that side and support you emotionally.";
            break;

        case 'admin_school':
            $replyText =
                "For official school processes (accounts, forms, requirements, enrollment, and similar things), it’s usually best to contact the registrar, IT, or your teacher/adviser, {$first}. "
                ."What I can do is support you if these processes are stressing you out, confusing you, or making you feel pressured or stuck.";
            break;

        default:
            // Generic non-mental content: keep it gentle and clear about Lumi’s role
            if ($isQuestion) {
                $replyText =
                    "Thank you for your question, {$first}. "
                    ."Lumi is focused on your mental and emotional well-being, so I might not always be able to answer detailed questions about this topic. "
                    ."If this situation is affecting your mood, stress, or how you see yourself, you can tell me more about that part and we’ll stay there.";
            } else {
                $replyText =
                    "Thank you for sharing that, {$first}. "
                    ."Lumi is focused on your mental and emotional well-being, so I might not always go deep into this kind of topic. "
                    ."But if it’s affecting your stress, mood, or confidence in any way, you can talk to me about how it feels for you.";
            }
            break;
    }

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
    } elseif ($flags['wants_coping'] ?? false) {
        $messageType = 'coping_request';
    } elseif ($inVentWindow) {
        $messageType = 'emotional_vent';
    } elseif ($flags['is_question'] ?? false) {
        $messageType = 'question';
    }

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
    } elseif ($inVentWindow) {
        $conversationStage = 'venting';
    }

    // Build metadata (rich for Rasa policies)
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
                'stage'              => $conversationStage,
                'overall_risk'       => $session->risk_level ?: 'low',
                'topic_summary'      => $session->topic_summary,
                'emotion_counts'     => $sessionEmotionCounts,
                'user_message_count' => $sessionUserMsgCount,
                'vent_turns'         => $ventTurns,
                'in_vent_window'     => $inVentWindow,
                'non_mental_topic'   => false,   // only mental messages reach here
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
                ['title' => 'Not now',        'payload' => '/deny{"confirm_topic":"referral"}'],
            ],
        ];
    } elseif ($inVentWindow && !$askedForAppt && $msgRisk !== 'high') {
        $stage     = max(1, min(3, $ventTurns + 1));
        $replyText = $this->empathicPrompt($first, $labels, $stage);
        $botReplies[] = ['text' => $replyText, 'buttons' => []];
        session([$ventKey => $ventTurns + 1]);
    } elseif (
        (
            ($flags['wants_coping'] ?? false)
            || (($flags['yes'] ?? false) && $lastIntent === 'offer_coping')
        )
        && $canOfferCoping
    ) {
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
                'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more?"
            ]];
        }

        if (empty($botReplies)) {
            $botReplies = [[
                'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more?"
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
        return "Thank you for opening up, {USER_FIRST}. I want to understand this the way *you* feel it. If you had to put it into your own words, how would you describe what you’re feeling right now? It’s also okay if it feels mixed or hard to name—we can figure it out together.";
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
                'sad','down','blue','low','tearful','cry','crying','cried','grief','heartbroken',
                'broken','brokenhearted','hurt inside',
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
        'wifi','router','modem','internet','signal','lag','ping','fps','ms',
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

        // If we already have a decent title and the conversation is long,
        // stop aggressively renaming it.
        if (
            $currentTitle
            && $msgCount > 6
            && !str_starts_with($currentTitle, 'Starting conversation')
        ) {
            return $currentTitle;
        }

        // Primary emotion (if any)
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

        $prefix = '';

        // Crisis always wins
        if ($risk === 'high') {
            $prefix = 'Crisis thoughts';
        } elseif ($primary && isset($moodPhrases[$primary])) {
            $prefix = $moodPhrases[$primary];
        } elseif ($flags['is_question'] ?? false) {
            $prefix = 'Question';
        } else {
            $prefix = 'Conversation';
        }

        // High-level topic domain (school, family, relationships, self, future, etc.)
        $topicSuffix = '';

        if ($this->hasAnyWord($norm, [
            'school','class','classes','subject','subjects','assignment','assignments',
            'homework','module','modules','quiz','exam','exams','test','projects',
            'grades','grade','teacher','professor','course','courses',
        ])) {
            $topicSuffix = 'about school';
        } elseif ($this->hasAnyWord($norm, [
            'friend','friends','bestfriend','best friend','bff','classmate','classmates',
            'crush','partner','boyfriend','girlfriend','relationship','relationships',
            'breakup','break up','ex','trust','betray','betrayed',
        ])) {
            $topicSuffix = 'about relationships';
        } elseif ($this->hasAnyWord($norm, [
            'family','parents','mother','father','mama','papa','mom','dad',
            'lola','lolo','siblings','brother','sister',
            'home','house',
        ])) {
            $topicSuffix = 'about family';
        } elseif ($this->hasAnyWord($norm, [
            'future','career','job','work','working','income','money',
            'choice','decisions','decision','path','course shift','shift course',
        ])) {
            $topicSuffix = 'about the future';
        } elseif ($this->hasAnyWord($norm, [
            'myself','self','identity','who i am','purpose','meaning',
            'worth','worthless','useless','burden',
        ])) {
            $topicSuffix = 'about yourself';
        }

        // Combine mood + topic
        $title = $prefix;
        if ($topicSuffix !== '') {
            // e.g. "Feeling overwhelmed about school"
            $title = trim($prefix . ' ' . $topicSuffix);
        } elseif ($flags['is_question'] ?? false) {
            // For questions with no clear topic, show a short snippet.
            $snippet = \Illuminate\Support\Str::limit($analysisText, 40, '…');
            $title   = 'Question: ' . $snippet;
        }

        // Fallback if somehow we got nothing useful
        if ($title === '' || mb_strlen($title) < 4) {
            $title = \Illuminate\Support\Str::limit($analysisText, 40, '…');
        }

        // Capitalize first letter just to be safe
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
        'javascript','typescript','node','nodejs','php','laravel',
        'python','java','csharp','c#','cpp','c++','html','css',
        'react','vue','angular','svelte','tailwind','bootstrap',
        'mysql','postgres','database','databases','sql','query','queries','migration','seeder',
        'api','rest api','endpoint','request','response','json','jwt',
        'github','gitlab','bitbucket','git','branch','merge','commit','pull','push',
        'vscode','visual studio','ide','editor',
        'server','hosting','hostinger','domain','dns','nginx','apache','iis',
        'wifi','router','modem','internet','signal','lag','ping','fps','ms',
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



}
