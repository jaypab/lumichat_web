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
 private function detectEmotions(string $text): array
    {
        $rules = [
            // Core “big six”
            'happy|joy|glad|content|cheerful|pleased|relieved|grateful|gratitude|satisfied|proud|optimistic|hopeful|excited|thrilled|ecstatic|elated|euphoric|stoked|nalipay' => 'happy',
            'sad|down|blue|unhappy|depress(ed)?|depression|cry(ing)?|tearful|heartbroken|grief|grieving|mourning|nagool' => 'sad',
            'angry|mad|furious|rage|irate|annoy(ed)?|irritat(ed)?|frustrat(ed)?|resentful|outraged|cross' => 'angry',
            'anxious|anxiety|panic|panicky|afraid|fear|scared|terrified|nervous|uneasy|worried|apprehensive|kulba' => 'anxious',
            'disgust|disgusted|gross(ed)? out|revolted|nauseated|repulsed' => 'disgust',
            'surprise(d)?|shocked|astonished|amazed|startled|stunned' => 'surprised',

            // Common nuanced states
            'stress|stressed|pressure|overwhelm(ed)?|burnout|overloaded' => 'stressed',
            'tired|exhausted|fatigue|fatigued|drained|worn out|kapoy' => 'tired',
            'lonely|loneliness|alone|isolated|isolat(ed)?|left out' => 'lonely',
            'bored|boredom|apathetic|meh|indifferent|listless' => 'bored',
            'confus(ed)?|confusing|unsure|uncertain|lost|perplexed' => 'confused',
            'ashamed|shame|embarrass(ed)?|mortified|humiliated' => 'ashamed',
            'guilt(y)?|guilty' => 'guilty',
            'jealous|jealousy|envy|envious' => 'jealous',
            'hurt|pained|pangs|wounded feelings' => 'hurt',
            'disappoint(ed)?|let down' => 'disappointed',
            'hopeless|no hope|give up|pointless|worthless' => 'hopeless',
            'insecure|not enough|inferior|self-conscious' => 'insecure',
            'calm|peaceful|serene|at ease|relaxed|okay|fine|ok(ay)?' => 'calm',
            'determined|motivated|driven|resolute|committed' => 'determined',
            'regret|regretful|remorse' => 'regret',
            'love|loved|loving|affection|caring|fond' => 'love',
            'homesick|miss home|miss my family' => 'homesick',
            'nervous breakdown|can’t cope|cannot cope' => 'overwhelmed',
            'not ok(ay)?|not fine|not okey|not okay' => 'not_ok',

            // local language cues (Cebuano/Bisaya commonly heard)
            'kulba' => 'anxious',
            'kapoy' => 'tired',
            'nalipay' => 'happy',
            'nagool' => 'sad',
        ];

        $labels = [];
        foreach ($rules as $pattern => $label) {
            if (preg_match('/\b(?:' . $pattern . ')\b/iu', $text)) {
                $labels[] = $label;
            }
        }

        // De-dup + stable order
        $labels = array_values(array_unique($labels));

        // Optional: ensure at least one label for UX (comment out if you prefer empty)
        if (empty($labels)) {
            // Try coarse bucketing:
            if (preg_match('/\b(help|problem|struggle|issue|hard|difficult)\b/i', $text)) {
                $labels[] = 'stressed';
            }
        }

        return $labels;
    }

    /**
     * Updated store(): saves first message as usual, but:
     * - detects emotions from the message
     * - stores them as JSON in chat_sessions.emotions
     * - DOES NOT write "Starting conversation..." anymore
     */

public function store(Request $request)
{
    // 1) Validation (+ idempotency) — unchanged
    $validated = $request->validate([
        'message' => ['required','string','max:2000', function ($attr, $val, $fail) {
            $s = is_string($val) ? preg_replace('/\s+/u', ' ', $val) : '';
            $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s ?? '');
            if (trim($s) === '') return $fail('Message cannot be empty.');
            if ($s !== strip_tags($s)) return $fail('HTML is not allowed in messages.');
        }],
        '_idem'        => ['required','uuid','unique:chats,idempotency_key'],
        'display_text' => ['nullable','string','max:2000'],   // <-- NEW
    ]);


    $rawInput = (string) $validated['message'];       // payload (could be /intent...)
    $text = preg_replace('/\s+/u', ' ', $rawInput);
    $text = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $text ?? '');
    $text = trim($text);

    // Optional human label for storage/rendering
    $rawDisplay = (string)($request->input('display_text', ''));
    $display = preg_replace('/\s+/u', ' ', $rawDisplay);
    $display = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $display ?? '');
    $display = trim($display);

// For risk/lang heuristics, prefer the human text when present
$analysisText = $display !== '' ? $display : $text;


    $userId    = Auth::id();
    $sessionId = session('chat_session_id');

    // NEW: detect emotions (never allowed to break flow)
    $emotions = [];
    try {
        $emotions = $this->detectEmotions($text);
    } catch (\Throwable $e) {
        $emotions = [];
    }

    // 2) Session ownership check — unchanged (plus safe init of emotions)
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
        // init emotions (best-effort)
        try {
            if (!empty($emotions)) {
                $session->emotions = $this->incrementEmotionCounts([], $emotions);
                $session->save();
            }
        } catch (\Throwable $e) {
            // swallow
        }
        $this->logActivity('chat_session_created', 'New chat session auto-created', $session->id, [
            'is_anonymous' => false,
            'reused'       => false,
        ]);
    }
    $sessionId = $session->id;

    // 3) Language + risk — unchanged
    $lang    = $this->inferLanguage($text);
    $msgRisk = $this->evaluateRiskLevel($text);

    // 4) Save user message — unchanged
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

    // NEW: accumulate emotion counts (best-effort, never fatal)
    try {
        if (!empty($emotions)) {
            $current = $this->emotionsAsCounts($session->emotions ?? []);
            $updated = $this->incrementEmotionCounts($current, $emotions);
            if ($updated !== $current) {
                $session->emotions = $updated;
                $session->save();
            }
        }
    } catch (\Throwable $e) {
        // swallow
    }

 // 5) Call Rasa — PRESERVE buttons
$rasaUrl  = $this->rasaWebhookUrl();
$metadata = $this->buildRasaMetadata($sessionId, $lang, $msgRisk);
$botReplies = []; // each item: ['text'=>string, 'buttons'=>array]

$timeout = (int) config('services.rasa.timeout', (int) env('RASA_TIMEOUT', 8));
$verify  = filter_var(env('RASA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

$r = null;
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
            if (is_array($piece)) {
                $txt = isset($piece['text']) ? (string) $piece['text'] : '';
                $btn = (isset($piece['buttons']) && is_array($piece['buttons'])) ? $piece['buttons'] : [];
                if ($txt !== '' || !empty($btn)) {
                    $botReplies[] = ['text' => $txt, 'buttons' => $btn];
                }
            } else {
                $txt = trim((string) $piece);
                if ($txt !== '') $botReplies[] = ['text' => $txt, 'buttons' => []];
            }
        }
    }
} catch (\Throwable $e) {
    $botReplies = [
        ['text' => "It’s okay to feel that way. I’m here to listen. Would you like to share more? / Sige ra na, ania ko maminaw. Gusto nimo isulti pa ug dugang?", 'buttons' => []]
    ];
}

if (empty($botReplies)) {
    $botReplies = [
        ['text' => "I’m here to support you. Would you like to share more about how you’re feeling? / Ania ko para motabang. Gusto nimo isulti pa ug dugang kung unsa imong gibati?", 'buttons' => []]
    ];
}

    // 6) Risk elevation + crisis prompt — unchanged
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
        $this->logActivity('crisis_prompt', 'Crisis context sent to Rasa', $sessionId, null);
        // No message injected here — rely on Rasa using metadata.risk === 'high'
    }

    // 6.5) Appointment CTA — unchanged
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
// 7) Build appointment link + response payload (no schema change)
$link = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
    ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
    : (\Illuminate\Support\Facades\Route::has('appointment.index')
        ? route('appointment.index')
        : url('/appointment'));

$ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

$botPayload = [];
foreach ($botReplies as $replyObj) {
    $replyText = (string) ($replyObj['text'] ?? '');
    $replyBtns = (isset($replyObj['buttons']) && is_array($replyObj['buttons'])) ? $replyObj['buttons'] : [];

    // language pick + inline link replace
    $replyText = $this->pickLanguageVariant($replyText, $lang);
    if (str_contains($replyText, '{APPOINTMENT_LINK}')) {
        $replyText = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $replyText);
    }

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

    // save bot message (encrypted) like before
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
        'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('H:i'),
        'sent_at'    => $bot->sent_at->toIso8601String(),
    ];
}

// return JSON with structured replies
return response()->json([
    'user_message' => [
        'text'       => $text,
        'time_human' => now()->timezone(config('app.timezone'))->format('H:i'),
        'sent_at'    => now()->toIso8601String(),
    ],
    'bot_reply'  => $botPayload,
    'time_human' => now()->timezone(config('app.timezone'))->format('H:i'),
]);
}


    // Normalize any stored shape (null | list | map) into a simple list of labels.
// Decode stored JSON into a label=>count map.
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