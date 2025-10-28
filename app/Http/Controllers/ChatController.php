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
        if (!preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|sige|go|go ahead|proceed|please|yes please)\b/u', $t)) {
            return false;
        }
        $lastBot = \App\Models\Chat::where('chat_session_id', $sessionId)->where('sender', 'bot')->latest('sent_at')->first();
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
        $email = (string) ($u->email ?? '');
        if (str_contains($email, '@')) return strtok($email, '@');
        return 'there';
    }

    private function inferLanguage(string $t): string
    {
        $x = mb_strtolower($t);
        $cebWords = [
            'nag', 'ko', 'kaayo', 'unsa', 'karon', 'gani', 'balaka', 'kulba', 'kapoy', 'nalipay',
            'gusto', 'pa-schedule', 'magpa-iskedyul', 'pwede', 'palihug', 'bug-at', 'dili',
            'maayong', 'kumusta', 'mohilak', 'hikog', 'paglaum', 'jud', 'lagi', 'bitaw'
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
        $t = RiskHeuristics::normalizeMsg($text);
        $t = preg_replace('/\s+/u', ' ', $t ?? '');

        // HIGH
        $high = [
            '\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b',
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
        $negatedDie = (bool) preg_match('/\b(?:don\'?t|do\s+not)\s+i\s+[^.?!]*\bdie\b/iu', $t);
        foreach ($high as $p) {
            if ($p === '\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b') {
                if ($negatedDie) { /* skip */ }
                elseif (preg_match('/'.$p.'/iu', $t)) return 'high';
                continue;
            }
            if (preg_match('/'.$p.'/iu', $t)) return 'high';
        }

        // Co-occurrence heuristic
        $acts   = ['suicide', 'die', 'unalive', 'kill myself', 'end my life', 'end it', 'jump', 'overdose', 'poison', 'cut', 'disappear', 'be gone', 'mamatay', 'hikog', 'wala na koy paglaum', 'mawala'];
        $intent = ['wanna', 'want', 'plan', 'planning', 'thinking', 'feel like', 'i should', 'i will', 'i might', 'really want', 'gonna', 'gusto', 'buot', 'tingali', 'murag'];
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
        return '';
    }

    private function wantsAppointment(string $text): bool
    {
        $t = mb_strtolower($text);

        $strong = [
            '/\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b[\s\S]{0,80}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b[\s\S]{0,80}\b(appoint(?:ment)?|schedule|book|booking|reserve|set\s*an?\s*appointment)\b/iu',
            '/\b(i\s+want|i\'?d\s+like|can\s+i|please)\b[\s\S]{0,40}\b(schedule|book|appointment)\b[\s\S]{0,40}\b(counsel(?:or|ling)|therap(?:ist|y)|advisor)\b/iu',
            '/\bsee\s+(?:a\s+)?counselor\b/iu',
            '/\b(pa-?schedule|magpa-?iskedyul|mo-?book)\b[\s\S]{0,80}\b(counsel(?:or|ing)?|konselor|tambag|makig[- ]?istorya)\b/iu',
        ];
        foreach ($strong as $r) if (preg_match($r, $t)) return true;

        if (preg_match('/\b(appoint(?:ment)?|schedule|book(?:ing)?|reserve|set\s*(?:an?|up)?\s*appointment)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(talk to|speak with|see|meet)\b[\s\S]{0,40}\b(someone|somebody|counsel(?:or)?|advisor|therap(?:ist)?)\b/iu', $t)) {
            return true;
        }
        return false;
    }

    private function rasaWebhookUrl(): string
    {
        $direct = (string) config('services.rasa.url', env('RASA_URL', ''));
        if (!empty($direct)) return $direct;

        $base  = rtrim((string) env('RASA_BASE_URL', 'http://127.0.0.1:5005'), '/');
        $path  = '/' . ltrim((string) env('RASA_WEBHOOK_PATH', '/webhooks/rest/webhook'), '/');
        $token = trim((string) env('RASA_TOKEN', ''), "\"'");

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
        $t = mb_strtolower($text);

        $rules = [
            '(?:sad|down(?:\s*bad)?|blue|unhappy|low\s*(?:mood|energy)|depress(?:ion|ed|ing)?|cry(?:ing)?|teary|tearful|heartbroken|grief|grieving|mourning|empty|numb|broken|nagool|hilak|masubo)' => 'sad',
            '(?:angry|mad|furious|rage|irate|pissed(?:\s*off)?|annoy(?:ed|ing)?|irritat(?:ed|ing)?|frustrat(?:ed|ing)?|resentful|outraged|cross|nasuko|suko|kasuko|sapot|gikalagot|galit)' => 'angry',
            '(?:anxious|anxiety|panic|panicky|afraid|fear(?:ful)?|scared|terrified|nervous|uneasy|on\s*edge|restless|worried|worry(?:ing)?|apprehensive|paranoid|kabado|balisa|nabalaka|kulba(?:an)?|kulbaan)' => 'anxious',
            '(?:disgust|disgusted|gross(?:ed)?\s*out|revolted|nauseat(?:ed|ing)|repulsed|yuck|eww|icky|kasuka|luod|suka)' => 'disgust',
            '(?:surpris(?:e|ed)|shocked|astonish(?:ed|ing)|amazed|wow|whoa|startled|stunned|hala\!?)' => 'surprised',
            '(?:stress|stressed|stressing|pressure|under\s*pressure|overwhelm(?:ed|ing)?|burn(?:out|t\s*out)|overloaded|swamped|cramming|cram|dagsang\s*trabaho|daghan\s*kaayong\s*buhaton)' => 'stressed',
            '(?:tired|sleepy|exhaust(?:ed|ion)|fatigue(?:d)?|drained|burnt\s*out|worn\s*out|low\s*energy|hapo|kapoy|kapuy|gikapoy|hutdan\s*ug\s*kusog)' => 'tired',
            '(?:lonely|loneliness|alone|isolat(?:ed|ion)?|left\s*out|no\s*one\s*(?:cares|to\s*talk)|mingaw|mingawon|walay\s*kuyog|walang\s*kausap)' => 'lonely',
            '(?:bored|boredom|apathetic|meh|indifferent|listless|nothing\s*to\s*do|dull|bored\s*af|saputon\s*sa\s*kakapoy)' => 'bored',
            '(?:confus(?:e|ed|ing)|unsure|uncertain|mixed\s*up|lost|perplexed|dilemma|libog|nalilito)' => 'confused',
            '(?:ashamed|shame|embarrass(?:ed|ing)?|mortified|humiliat(?:ed|ing)?|hiya|ulaw)' => 'ashamed',
            '(?:guilt(?:y)?|my\s*fault|to\s*blame|kasalanan\s*ko|sala\s*nako)' => 'guilty',
            '(?:jealous|jealousy|envy|envious|inggit|suya|nasuya)' => 'jealous',
            '(?:hurt|pained|painful\s*inside|wounded\s*feelings|nasakitan|masakit\s*ang\s*loob)' => 'hurt',
            '(?:disappoint(?:ed|ing)|let\s*down|nadismaya|na\-?disappoint)' => 'disappointed',
            '(?:hopeless|no\s*hope|give\s*up|giving\s*up|pointless|meaningless|worthless|stuck|wala[y]?\s*paglaum|surrender)' => 'hopeless',
            '(?:insecure|not\s*(?:good\s*enough|enough)|inferior|self\-?conscious|ugly|fat|dumb|stupid|failure)' => 'insecure',
            '(?:calm|peaceful|serene|at\s*ease|relax(?:ed|ing)?|okay|ok(?:ay)?|fine|chill|better\s*now)' => 'calm',
            '(?:determin(?:ed|ation)|motivated|driven|resolute|committed|focused|game\s*plan)' => 'determined',
            '(?:regret|regretful|remorse|shouldn\'?t\s*have|my\s*mistake|sayop\s*nako)' => 'regret',
            '(?:love|loved|loving|affection|care(?:s|d)?|fond|❤️|<3)' => 'love',
            '(?:homesick|miss\s*home|miss\s*my\s*family|mingaw\s*sa\s*balay|mingaw\s*ko\s*sa\s*pamilya)' => 'homesick',
            '(?:can(?:not|\'?t)\s*cope|nervous\s*breakdown|too\s*much|overwhelming|dilikado\s*na|di\s*kaya|di\s*ko\s*kaya)' => 'overwhelmed',
            '(?:not\s*(?:ok|okay|fine|okey)|hindi\s*ok(?:ay)?|dili\s*okay)' => 'not_ok',
            '[\x{1F62D}\x{1F622}\x{1F614}\x{1F625}\x{1F641}]' => 'sad',
            '[\x{1F620}\x{1F621}]'                              => 'angry',
            '[\x{1F630}\x{1F628}\x{1F627}]'                    => 'anxious',
            '[\x{1F62E}\x{1F632}]'                              => 'surprised',
            '[\x{1F4A4}]'                                       => 'tired',
            '[\x{1F922}\x{1F92E}]'                              => 'disgust',
            '[\x{1F612}\x{1F611}]'                              => 'bored',
            '[\x{1F615}\x{1F641}\x{1F914}]'                    => 'confused',
            '[\x{2764}\x{1F496}\x{1F495}]'                      => 'love',
        ];

        $emotions = [];
        foreach ($rules as $pattern => $label) {
            if (preg_match('/(?:^|\b|[_\-\#\(])(?:' . $pattern . ')(?:\b|$|[!\.\?,\)])/u', $t)) {
                $emotions[] = $label;
            }
        }

        $selfThreat = false;
        $selfThreatPatterns = [
            '\bkill myself\b',
            '\bend my life\b',
            '\bcommit suicide\b',
            '\bno reason to live\b',
            '\blife is pointless\b',
            '\bi(?:\s|\'m| am)? (?:tired of living|want to die|wish i (?:were|was) dead|should just die)\b',
            '\bi can\'?t go on\b',
            '\b(?:overdose|hang myself|jump off|cut myself|poison myself)\b',
            '\bgusto (?:na\s)?ko mamatay\b',
            '\bmaghikog\b',
            '\bwala na koy paglaum\b',
            '\bgusto ko mawala\b',
            '\btapuson na nako tanan\b',
            '\bdi(?:li)?\s*na\s*ko\s*gusto\s*mabuhi\b',
        ];
        foreach ($selfThreatPatterns as $regex) {
            if (preg_match('/' . $regex . '/iu', $t)) { $selfThreat = true; break; }
        }

        $emotions = array_values(array_unique($emotions));
        if (empty($emotions)) {
            if (preg_match('/\b(help|problem|struggle|issue|hard|difficult|challenge|can\'?t\s*cope)\b/u', $t)) {
                $emotions[] = 'stressed';
            }
        }

        return [
            'emotions'    => $emotions,
            'self_threat' => $selfThreat,
        ];
    }

public function store(Request $request)
{
    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

    // 1) Validate (+ idempotency)
    $request->validate([
        'message'      => ['required', 'string', 'max:2000', function ($attr, $val, $fail) {
            $s = is_string($val) ? preg_replace('/\s+/u', ' ', $val) : '';
            $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s ?? '');
            if (trim($s) === '') return $fail('Message cannot be empty.');
            if ($s !== strip_tags($s)) return $fail('HTML is not allowed in messages.');
        }],
        'display_text' => ['nullable', 'string', 'max:2000'],
    ]);

    $rawInput = (string) $request->input('message', '');
    $text     = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawInput) ?? ''));

    // Normalize display text
    $rawDisplay = (string) $request->input('display_text', '');
    $display    = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawDisplay) ?? ''));

    // Prefer “human” text for heuristics
    $analysisText = $display !== '' ? $display : $text;

    // Idempotency key (tolerate missing/invalid)
    $idem = (string) $request->input('_idem', '');
    if (!\Illuminate\Support\Str::isUuid($idem)) {
        $idem = (string) \Illuminate\Support\Str::uuid();
    }

    // --- Detect emotions ONCE (labels + selfThreat)
    $emoResult  = $this->detectEmotions($analysisText);
    $labels     = (array)($emoResult['emotions'] ?? []);
    $selfThreat = (bool)($emoResult['self_threat'] ?? false);
    if ($selfThreat && !in_array('hopeless', $labels, true)) {
        $labels[] = 'hopeless';
    }

    $userId    = Auth::id();
    $sessionId = session('chat_session_id');

    // 2) Ensure session
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

        // best-effort emotion seed
        try {
            if (!empty($labels)) {
                $session->emotions = $this->incrementEmotionCounts([], $labels);
                $session->save();
            }
        } catch (\Throwable $e) { /* noop */ }

        $this->logActivity('chat_session_created', 'New chat session auto-created', $session->id, [
            'is_anonymous' => false,
            'reused'       => false,
        ]);
    }
    $sessionId = $session->id;

    // 3) Language + risk
    $lang    = $this->inferLanguage($text);
    $msgRisk = $this->evaluateRiskLevel($analysisText);
    if ($selfThreat) $msgRisk = 'high';

    // 4) Persist user message (idempotent)
    try {
        $userMsg = Chat::firstOrCreate(
            ['idempotency_key' => $idem],
            [
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'user',
                'message'         => Crypt::encryptString($text),
                'sent_at'         => now(),
            ]
        );
    } catch (\Illuminate\Database\QueryException $e) {
        $userMsg = Chat::where('idempotency_key', $idem)->first();
        if (!$userMsg) throw $e;
    }

    // Stamp first high-risk trigger
    try {
        if ($msgRisk === 'high') {
            $session->refresh();
            if (empty($session->high_risk_chat_id)) {
                $excerpt = \Illuminate\Support\Str::limit($text, 180, '…');

                $sessTable = app(\App\Http\Controllers\Admin\ChatbotSessionController::class)->sessionsTable();
                if ($sessTable && \Illuminate\Support\Facades\Schema::hasColumn($sessTable, 'high_risk_chat_id')) {
                    \Illuminate\Support\Facades\DB::table($sessTable)
                        ->where('id', $session->id)
                        ->update([
                            'high_risk_chat_id' => $userMsg->id,
                            'high_risk_excerpt' => $excerpt,
                            'high_risk_at'      => now(),
                            'updated_at'        => now(),
                        ]);
                } else {
                    $session->high_risk_chat_id = $userMsg->id;
                    $session->high_risk_excerpt = $excerpt;
                    $session->high_risk_at      = now();
                    $session->save();
                }
            }
        }
    } catch (\Throwable $e) { /* noop */ }

    // Topic summary for first user line
    $count = Chat::where('chat_session_id', $sessionId)->where('sender', 'user')->count();
    if ($count === 1) {
        preg_match('/\b(sad|depress|help|anxious|angry|lonely|stress|tired|happy|excited|not okay|nagool|kapoy|kulba|nalipay)\b/i', $text, $m);
        $summary = $m[0] ?? \Illuminate\Support\Str::limit($text, 40, '…');
        $session->update(['topic_summary' => ucfirst($summary)]);
    }

    // Update emotion tallies (best-effort)
    try {
        if (!empty($labels)) {
            $current = $this->emotionsAsCounts($session->emotions ?? []);
            $updated = $this->incrementEmotionCounts($current, $labels);
            if ($updated !== $current) {
                $session->emotions = $updated;
                $session->save();
            }
        }
    } catch (\Throwable $e) { /* noop */ }

    // ---- NEW turn/door flags
    $skipOnceKey      = 'skip_rasa_once_'.$sessionId;    // next user turn will skip Rasa once
    $skipRasaThisTurn = (bool) session($skipOnceKey, false);

    // 5) Call Rasa (guarded by skip flag)
    $rasaUrl  = $this->rasaWebhookUrl();
    $metadata = $this->buildRasaMetadata($sessionId, $lang, $msgRisk) + [
        'user' => ['id' => auth()->id(), 'name' => $name, 'first' => $first],
    ];

    $botReplies = [];
    if (!$skipRasaThisTurn) {
        $timeout = (int) config('services.rasa.timeout', (int) env('RASA_TIMEOUT', 8));
        $verify  = filter_var(env('RASA_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

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
            $botReplies = [[
                'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more? / Sige ra na, {USER_FIRST}. Ania ko maminaw. Gusto nimo mo-share pa?",
                'buttons' => [],
            ]];
        }

        // ---- Door right AFTER Rasa on Turn 2 (ask a question, then next turn → coping)
        if ($count === 2 && !empty($botReplies)) {
            $botReplies[] = [
                'text' =>
                    "Did any part of that help even a little, {USER_FIRST}? What feels most pressing right now? / " .
                    "Nakatabang ba bisag gamay to, {USER_FIRST}? Asa ang pinakalisod karon?",
                'buttons' => [],
            ];
            session([$skipOnceKey => true]);  // next user message => skip Rasa and offer coping
        }
    }

    // Fallback if nothing came back
    if (empty($botReplies)) {
        $botReplies = [[
            'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more? / Sige ra na, {USER_FIRST}. Ania ko maminaw. Gusto nimo mo-share pa?",
            'buttons' => [],
        ]];
    }

    // 6) Risk elevation log
    $current = $session->risk_level ?: 'low';
    $order   = ['low' => 0, 'moderate' => 1, 'high' => 2];
    $new     = ($order[$msgRisk] > $order[$current]) ? $msgRisk : $current;
    if ($new !== $current) $session->update(['risk_level' => $new]);

    $this->logActivity('risk_detected', "Risk level: {$msgRisk}", $sessionId, [
        'risk_level'      => $msgRisk,
        'message_preview' => \Illuminate\Support\Str::limit($text, 120),
    ]);

    $crisisAlreadyShown = session('crisis_prompted_for_session_' . $sessionId, false);
    if (!$crisisAlreadyShown && $msgRisk === 'high') {
        session(['crisis_prompted_for_session_' . $sessionId => true]);
        $this->logActivity('crisis_prompt', 'Crisis context sent to Rasa', $sessionId, null);
    }

    // 6.5) Appointment CTA when user asks
    $askedForAppt = $this->wantsAppointment($text) || $this->confirmedAfterOffer($text, $sessionId);
    $hasApptPlaceholder = false;
    foreach ($botReplies as $rpl) {
        if (is_array($rpl) && isset($rpl['text']) && is_string($rpl['text']) && str_contains($rpl['text'], '{APPOINTMENT_LINK}')) {
            $hasApptPlaceholder = true; break;
        }
    }
    if ($askedForAppt && !$hasApptPlaceholder) {
        $ctaReply = "You can book a time with a school counselor here: {APPOINTMENT_LINK} / Pwede ka magpa-book sa school counselor dinhi: {APPOINTMENT_LINK}";
        if ($msgRisk === 'high') {
            $botReplies[] = ['text' => $ctaReply, 'buttons' => []];
        } else {
            array_unshift($botReplies, ['text' => $ctaReply, 'buttons' => []]);
        }
        $this->logActivity('appointment_detected', 'User asked to schedule; CTA injected', $sessionId, [
            'preview' => \Illuminate\Support\Str::limit($text, 120),
        ]);
    }

    // 6.7) Empathy preface (Turn 1 only replaces; Turn ≥2 only prepends)
    $primaryEmotion = $this->choosePrimaryEmotion($labels);
    if ($this->shouldPreface($sessionId, $labels, $msgRisk)) {
        $preface = $this->empathyTemplate($primaryEmotion, $msgRisk);

        if ($count === 1) {
            // Turn 1: empathy ONLY (defer Rasa fully)
            $botReplies = [['text' => $preface, 'buttons' => []]];
        } else {
            // Turn ≥2: put empathy first, keep Rasa + door question intact
            array_unshift($botReplies, ['text' => $preface, 'buttons' => []]);
            // (if the next item has buttons, you can merge buttons if desired)
        }
    }

    // 7) Appointment link + personalization + (maybe) skip-turn coping
    $link = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
        ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
        : (\Illuminate\Support\Facades\Route::has('appointment.index')
            ? route('appointment.index')
            : url('/appointment'));
    $ctaHtml = '<a href="' . e($link) . '">Book an appointment</a>';

    // ===== Turn 3 (or whenever the once-flag is set): Skip Rasa and OFFER COPING
    if ($skipRasaThisTurn) {
        $botReplies = [[
            'text' =>
                "If you want, I can share coping tips based on how you're feeling. Want them now? / " .
                "Kung gusto nimo, makashare ko og coping tips base sa imong gibati. Gusto nimo karon?",
            'buttons' => [
                ['title' => 'Yes, show tips', 'payload' => '/show_coping'],
                ['title' => 'No, thanks',     'payload' => '/skip_coping'],
            ],
        ]];
        session()->forget($skipOnceKey);
    }

    // Final safety: only strip coping-looking replies on early turns IF we are not in the skip-coping turn
    if (!$skipRasaThisTurn && $count <= 3) {
        $botReplies = array_values(array_filter($botReplies, fn($piece) => !$this->looksLikeCoping((array)$piece)));
    }

    // Build response payload + persist bot lines
    $botPayload = [];
    foreach ($botReplies as $replyObj) {
        $replyText = (string) ($replyObj['text'] ?? '');
        $replyBtns = (isset($replyObj['buttons']) && is_array($replyObj['buttons'])) ? $replyObj['buttons'] : [];

        // language pick + inline link replace
        $replyText = $this->pickLanguageVariant($replyText, $lang);
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

        // normalize buttons (support {APPOINTMENT_LINK})
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

        // persist bot message
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

    return response()->json([
        'user_message' => [
            'text'       => $text,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => now()->toIso8601String(),
        ],
        'bot_reply'  => $botPayload,
        'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}

    // Normalize any stored shape into label=>count map.
    private function emotionsAsCounts(null|array|string $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) return [];

        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            $out = [];
            foreach ($value as $k => $v) {
                if (!is_string($k)) continue;
                $out[strtolower($k)] = max(0, (int) $v);
            }
            return $out;
        }

        $out = [];
        foreach ($value as $label) {
            if (!is_string($label) || $label === '') continue;
            $k = strtolower($label);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    private function incrementEmotionCounts(array $counts, array $labels): array
    {
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

    /* =========================================================================
     | Empathic preface helpers (updated)
     * =========================================================================*/

    private const EMOTION_PRIORITY = [
        'hopeless'=>3, 'sad'=>2, 'anxious'=>2, 'overwhelmed'=>2,
        'stressed'=>1, 'tired'=>1, 'lonely'=>1, 'confused'=>1, 'angry'=>1, 'not_ok'=>1,
        'disappointed'=>1, 'hurt'=>1, 'ashamed'=>1, 'guilty'=>1, 'insecure'=>1,
        'jealous'=>1, 'regret'=>1, 'bored'=>1, 'disgust'=>1, 'surprised'=>1,
        'homesick'=>1, 'love'=>0, 'determined'=>0, 'calm'=>0,
    ];

    private function choosePrimaryEmotion(array $labels): ?string
    {
        $labels = array_map('strtolower', $labels ?? []);
        $best = null; $bestScore = -1;
        foreach ($labels as $e) {
            $score = self::EMOTION_PRIORITY[$e] ?? 0;
            if ($score > $bestScore) { $bestScore = $score; $best = $e; }
        }
        return $best ?? ($labels[0] ?? null);
    }

    private function empathyTemplate(?string $emotion, string $risk): string
    {
        $generic = [
            "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more? / Sige ra na, {USER_FIRST}. Ania ko maminaw. Gusto nimo mo-share pa?",
            "{USER_FIRST}, thanks for telling me. You’re not alone—I’m with you. What feels toughest right now? / Salamat sa pag-sulti, {USER_FIRST}. Dili ka nag-inusara—ania ko. Unsay pinakalisod karon?",
            "I hear you, {USER_FIRST}. Let’s take this one step at a time. / Nadungog tika, {USER_FIRST}. Hinay-hinay lang ta.",
        ];

        $byEmotion = [
            'sad' => [
                "I’m sorry this is heavy, {USER_FIRST}. What do you think is adding to it lately? / Gikasubo nako, {USER_FIRST}. Unsay nakapabug-at ani karon?",
            ],
            'anxious' => [
                "That sounds tense, {USER_FIRST}. Want a 60-second grounding tip before we talk it through? / Kuyaw paminawon, {USER_FIRST}. Gusto ka og 60-second pa-calm sa wala pa ta mo-istorya?",
            ],
            'stressed' => [
                "You’ve been carrying a lot, {USER_FIRST}. What’s the first thing stressing you today? / Daghan ka’g gidala, {USER_FIRST}. Unsay una nga naka-stress nimo?",
            ],
            'tired' => [
                "You sound drained, {USER_FIRST}. What’s been taking most of your energy? / Murag gikapoy kaayo ka, {USER_FIRST}. Unsay pinakadako nga gaka-kuha sa imong kusog?",
            ],
            'lonely' => [
                "Feeling alone can hurt, {USER_FIRST}. I’m here with you now. / Masakit kung mag-inusa, {USER_FIRST}. Ania ko karon.",
            ],
            'confused' => [
                "It’s okay to be unsure, {USER_FIRST}. Want to sort it out together step by step? / Okay ra malibog, {USER_FIRST}. Atong himay-himayun?",
            ],
            'angry' => [
                "Your feelings are valid, {USER_FIRST}. Want to unpack what crossed the line? / Balido imong gibati, {USER_FIRST}. Atong hisgutan unsay nakalapas?",
            ],
            'overwhelmed' => [
                "When everything piles up, it’s hard to breathe, {USER_FIRST}. Let’s pick one small next step. / Kung nagtapok tanan, lisod gyud. Mangita ta’g usa ka gamay nga lakang.",
            ],
            'not_ok' => [
                "Thanks for being honest, {USER_FIRST}. Not feeling okay is okay. Where is it hardest right now? / Salamat sa pagkamatinuoron, {USER_FIRST}. Okay ra nga dili okay. Asa pinakalisod karon?",
            ],
            'disappointed' => [
                "It hurts to be let down, {USER_FIRST}. Want to tell me what happened? / Sakit ma-let down, {USER_FIRST}. Pwede nimo isulti unsay nahitabo?",
            ],
            'hurt' => [
                "That sounded painful, {USER_FIRST}. I’m here—what part stung the most? / Murag masakit, {USER_FIRST}. Asa bahin ang pinaka-sakit?",
            ],
            'ashamed' => [
                "Shame can feel heavy, {USER_FIRST}. You’re still worthy. Want to talk it through? / Bug-at ang ulaw, {USER_FIRST}. Bililhon gihapon ka. Istorya ta?",
            ],
            'guilty' => [
                "Guilt can be tough, {USER_FIRST}. We can sort facts from feelings together. / Lisod ang kasubo/kasala, {USER_FIRST}. Atong buwagon ang facts ug feelings.",
            ],
            'insecure' => [
                "That self-doubt is loud sometimes, {USER_FIRST}. Want to check the evidence together? / Kusog usahay ang pagduha-duha, {USER_FIRST}. Ato tan-awon ang ebidensya?",
            ],
            'jealous' => [
                "Jealousy happens to everyone, {USER_FIRST}. Want to explore what it’s telling you? / Masinahon usahay tanan, {USER_FIRST}. Ato sabton unsay pasabot ani?",
            ],
            'regret' => [
                "Regret stings, {USER_FIRST}. What would ‘making it a bit better’ look like now? / Masakit ang pagmahay, {USER_FIRST}. Unsa’y gamay nga pa-ayo karon?",
            ],
            'bored' => [
                "Sounds dull and empty, {USER_FIRST}. Want a tiny activity to break the loop? / Murag way kalipay, {USER_FIRST}. Gusto kag gamay nga buhat aron maputol ang loop?",
            ],
            'disgust' => [
                "That really turned you off, {USER_FIRST}. Want to vent a bit about it? / Grabe ka luod paminawon, {USER_FIRST}. Ganahan ka mo-vent gamay?",
            ],
            'surprised' => [
                "That was unexpected, {USER_FIRST}. How did it hit you? / Wala damha, {USER_FIRST}. Unsa’y imong nabati?",
            ],
            'homesick' => [
                "Missing home is tough, {USER_FIRST}. What do you miss most right now? / Lisod ang mingaw sa balay, {USER_FIRST}. Unsa’y pinaka-gimingaw nimo?",
            ],
            'love' => [
                "That warmth matters, {USER_FIRST}. Want to build on what’s helping? / Maayo nang kainit, {USER_FIRST}. Ato palambuon ang nakatabang?",
            ],
            'determined' => [
                "I see your drive, {USER_FIRST}. What’s a clear next step? / Kita nako imong determinasyon, {USER_FIRST}. Unsay klarong sunod nga lakang?",
            ],
            'calm' => [
                "Glad you’re finding some calm, {USER_FIRST}. Would you like gentle tips to keep it? / Maayo nga nakapahuway ka, {USER_FIRST}. Gusto ka’g tips para mapadayon?",
            ],
        ];

        $highRisk = [
            "I’m really glad you told me this, {USER_FIRST}. You matter. I’m here with you—let’s keep talking. If you want, I can also help you connect to a counselor. / Salamat gyud sa pagsulti, {USER_FIRST}. Importante ka. Ania ko—padayon ta. Pwede tika tabangan makig-connect sa counselor.",
        ];
        $moderate = [
            "That sounds heavy, {USER_FIRST}. You’re not a burden. We’ll face this together—can you tell me a bit more? / Bug-at paminawon, {USER_FIRST}. Dili ka pabigat. Atubangon nato ni—pila ka detalye pa?",
        ];

        if ($risk === 'high')    return $highRisk[array_rand($highRisk)];
        if ($risk === 'moderate')return $moderate[array_rand($moderate)];
        if ($emotion && isset($byEmotion[$emotion])) {
            return $byEmotion[$emotion][array_rand($byEmotion[$emotion])];
        }
        return $generic[array_rand($generic)];
    }

    private function shouldPreface(int $sessionId, array $labels, string $risk): bool
    {
        $key  = 'preface_meta_'.$sessionId;
        $meta = session($key, ['last_emotion'=>null, 'turns'=>0, 'last_time'=>0]);

        $primary = $this->choosePrimaryEmotion($labels);
        $meta['turns'] = (int)$meta['turns'] + 1;

        $emotionChanged = $primary && $primary !== ($meta['last_emotion'] ?? null);
        $firstTurn      = ($meta['turns'] === 1);
        $timeOk         = (time() - (int)$meta['last_time']) >= 40; // cooldown ~40s
        $everyThird     = ($meta['turns'] % 3 === 1);

        $prioScore = self::EMOTION_PRIORITY[$primary ?? ''] ?? 0;
        $strongEmotion = ($prioScore >= 2);

        $should = $firstTurn || $risk !== 'low' || $emotionChanged || $strongEmotion || $everyThird;
        $should = $should && $timeOk;

        $meta['last_emotion'] = $primary ?: ($meta['last_emotion'] ?? null);
        if ($should) $meta['last_time'] = time();
        session([$key => $meta]);

        return $should;
    }

    private function looksLikeCoping(array $piece): bool
    {
        $t = trim((string)($piece['text'] ?? ''));
        $btns = (array)($piece['buttons'] ?? []);

        if ($t !== '' && preg_match('/\b(coping|coping tips|tips|grounding|breathing|relaxation|self[- ]care|techniques?)\b/i', $t)) {
            return true;
        }
        foreach ($btns as $b) {
            $title = (string)($b['title'] ?? '');
            if ($title !== '' && preg_match('/\b(tips|coping|show|grounding|breathing)\b/i', $title)) {
                return true;
            }
        }
        return false;
    }

private function bridgeFollowup(?string $emotion, string $nameFirst): string
{
    // EN / CEB — question-forward, neutral (no “tips/grounding/breathing” words here)
    $generic = [
        "Did any part of that help a little, {$nameFirst}? What feels most pressing now? / Nakatabang ba gamay to, {$nameFirst}? Asa ang pinakalisod karon?",
        "Thanks for sharing, {$nameFirst}. Where should we start first? / Salamat sa pag-share, {$nameFirst}. Asa ta magsugod?",
        "I hear you, {$nameFirst}. What’s one small detail I should understand better? / Nadungog tika, {$nameFirst}. Unsay usa ka detalye nga angay nakong masabtan?",
    ];

    $byEmotion = [
        'stressed'    => "Makes sense, {$nameFirst}. Which task or thought piles up fastest? / Masabtan ra. Asa nga buluhaton o huna-huna ang dali kaayong magtapok?",
        'anxious'     => "Got it, {$nameFirst}. What worry pops up first? / Sige. Unsay unang kabalaka nga mosulod?",
        'sad'         => "I’m with you, {$nameFirst}. What adds most to the heaviness? / Ania ko. Unsa’y pinakadugang sa kabug-at?",
        'overwhelmed' => "Let’s take it piece by piece, {$nameFirst}. Where should we start? / Hinay-hinay lang. Asa ta magsugod?",
    ];

    return $byEmotion[strtolower((string)$emotion)] ?? $generic[array_rand($generic)];
}
}
