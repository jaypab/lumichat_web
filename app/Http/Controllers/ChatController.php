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
    // --- Personalization
    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

    // 1) Validate + normalize (with idempotency)
    $request->validate([
        'message'      => ['required', 'string', 'max:2000', function ($attr, $val, $fail) {
            $s = is_string($val) ? preg_replace('/\s+/u', ' ', $val) : '';
            $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $s ?? '');
            if (trim($s) === '') return $fail('Message cannot be empty.');
            if ($s !== strip_tags($s)) return $fail('HTML is not allowed in messages.');
        }],
        'display_text' => ['nullable', 'string', 'max:2000'],
    ]);

    $rawInput     = (string) $request->input('message', '');
    $text         = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawInput) ?? '')) ;
    $rawDisp      = (string) $request->input('display_text', '');
    $display      = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $rawDisp) ?? ''));
    $analysisText = $display !== '' ? $display : $text;

    $idem = (string) $request->input('_idem', '');
    if (!Str::isUuid($idem)) $idem = (string) Str::uuid();

    // --- Greeting guard (pure greetings get one friendly reply, no Rasa)
$isUserGreeting = $this->isUserGreeting($analysisText);
$suppressEmpathyForGreeting = $isUserGreeting
    || (bool)preg_match('/^(hi+|hello+|hey+|yo+)\b[^\w]*$/iu', $this->normalizeText($analysisText));

if ($isUserGreeting) {
    $userId    = Auth::id();
    $sessionId = session('chat_session_id');
    $session   = $sessionId
        ? ChatSession::where('id', $sessionId)->where('user_id', $userId)->first()
        : null;

    if (!$session) {
        $session = ChatSession::create([
            'user_id'       => $userId,
            'topic_summary' => 'Starting conversation...',
            'is_anonymous'  => 0,
            'risk_level'    => 'low',
        ]);
        session(['chat_session_id' => $session->id]);
    }

    $lang  = $this->inferLanguage($analysisText);
    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

    $greetReply = "Hello! How are you feeling today? / Kumusta! Kumusta imong gibati karon?";
    $greetReply = $this->pickLanguageVariant($greetReply, $lang);
    $greetReply = str_replace(
        ['{USER_FIRST}','{USER}','{NAME}','{USER_NAME}'],
        [e($first), e($first), e($name), e($name)],
        $greetReply
    );

    $bot = Chat::create([
        'user_id'         => $userId,
        'chat_session_id' => $session->id,
        'sender'          => 'bot',
        'message'         => \Illuminate\Support\Facades\Crypt::encryptString($greetReply),
        'sent_at'         => now(),
    ]);

    return response()->json([
        'user_message' => [
            'text'       => $analysisText,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => now()->toIso8601String(),
        ],
        'bot_reply' => [[
            'id'         => $bot->id,
            'text'       => $greetReply,
            'buttons'    => [],
            'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot->sent_at->toIso8601String(),
        ]],
        'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}

    // 2) Detect emotions + self-threat
    $emoResult  = $this->detectEmotions($analysisText);
    $labels     = (array)($emoResult['emotions'] ?? []);
    $selfThreat = (bool)($emoResult['self_threat'] ?? false);
    if ($selfThreat && !in_array('hopeless', $labels, true)) {
        $labels[] = 'hopeless';
    }

    $userId    = Auth::id();
    $sessionId = session('chat_session_id');

    // 3) Ensure session
    $session = null;
    if ($sessionId) {
        $session = ChatSession::where('id', $sessionId)->where('user_id', $userId)->first();
    }
    if (!$session) {
        $session = ChatSession::create([
            'user_id'       => $userId,
            'topic_summary' => 'Starting conversation...',
            'is_anonymous'  => 0,
            'risk_level'    => 'low',
        ]);
        session(['chat_session_id' => $session->id]);
        $sessionId = $session->id;

        try {
            if (!empty($labels)) {
                $session->emotions = $this->incrementEmotionCounts([], $labels);
                $session->save();
            }
        } catch (\Throwable $e) {}
        $this->logActivity('chat_session_created', 'New chat session auto-created', $session->id, [
            'is_anonymous' => false,
            'reused'       => false,
        ]);
    } else {
        $sessionId = $session->id;
    }

    // 4) Lang + risk
    $lang    = $this->inferLanguage($text);
    $msgRisk = $this->evaluateRiskLevel($analysisText);
    if ($selfThreat) $msgRisk = 'high';

    // 5) Save user message idempotently
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
    } catch (QueryException $e) {
        $userMsg = Chat::where('idempotency_key', $idem)->first();
        if (!$userMsg) throw $e;
    }

    // Persist FIRST high-risk (unchanged gist)
    try {
        if ($msgRisk === 'high') {
            $session->refresh();
            if (empty($session->high_risk_chat_id)) {
                $excerpt   = \Illuminate\Support\Str::limit($text, 180, '…');
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
    } catch (\Throwable $e) {}

    // Topic summary (unchanged gist)
    $count = Chat::where('chat_session_id', $sessionId)->where('sender', 'user')->count();
    // --- NEW: Defer Rasa on the user's first content turn
$deferKey       = 'defer_rasa_until_reply_' . $sessionId;
$helpcheckKey   = 'helpcheck_at_' . $sessionId;      // you already use this later
$pendingApptKey = 'pending_appt_cta_' . $sessionId;  // you already use this later

// If this is the first user message in this session, send empathy + open question only.
// Arm the defer flag so the NEXT user message will trigger Rasa + help-check.
if ($count === 1 && !session($deferKey, false)) {
    $primaryEmotion = $this->choosePrimaryEmotion($labels);
    $preface = $this->empathyTemplate($primaryEmotion, $msgRisk);
    $preface = $this->pickLanguageVariant($preface, $lang);

    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;
    $preface = str_replace(['{USER_NAME}','{USER_FIRST}','{USER}','{NAME}'], [e($name), e($first), e($first), e($name)], $preface);

    // End with one gentle open question (no help-check yet)
    $followQ = $this->pickLanguageVariant(
        "What’s been weighing on you most today? / Asa ang pinakabug-at karon?",
        $lang
    );

    $bot1 = Chat::create([
        'user_id'         => $userId,
        'chat_session_id' => $sessionId,
        'sender'          => 'bot',
        'message'         => Crypt::encryptString($preface),
        'sent_at'         => now(),
    ]);
    $bot2 = Chat::create([
        'user_id'         => $userId,
        'chat_session_id' => $sessionId,
        'sender'          => 'bot',
        'message'         => Crypt::encryptString($followQ),
        'sent_at'         => now(),
    ]);

    session([$deferKey => true]); // mark that next user reply should trigger Rasa + help-check

    return response()->json([
        'user_message' => [
            'text'       => $text,
            'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => now()->toIso8601String(),
        ],
        'bot_reply' => [[
            'id' => $bot1->id, 'text' => $preface, 'buttons' => [],
            'time_human' => $bot1->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot1->sent_at->toIso8601String(),
        ],[
            'id' => $bot2->id, 'text' => $followQ, 'buttons' => [],
            'time_human' => $bot2->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot2->sent_at->toIso8601String(),
        ]],
        'time_human' => now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}

    if ($count === 1) {
        preg_match('/\b(sad|depress|help|anxious|angry|lonely|stress|tired|happy|excited|not okay|nagool|kapoy|kulba|nalipay)\b/i', $text, $m);
        $summary = $m[0] ?? Str::limit($text, 40, '…');
        $session->update(['topic_summary' => ucfirst($summary)]);
    }

    // Accumulate emotion counts
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

    // --- Coping consent / flow flags (UPDATED)
    $doorKey         = 'door_after_rasa_' . $sessionId;
    $helpcheckKey    = 'helpcheck_at_' . $sessionId;
    $pendingApptKey  = 'pending_appt_cta_' . $sessionId;
    $ctaAfterHelpKey = 'cta_after_help_'  . $sessionId;

    // Contextual parse for yes/no/done
    $copingCmd = $this->parseCopingCommand($analysisText, $sessionId);  // NOTE: pass $sessionId

    $skipRasaThisTurn = session($doorKey, false);

    $forceCopingNow = ($copingCmd === 'yes') || (bool) preg_match('/^\/show_coping\b/i', $analysisText);
    $declineCoping  = ($copingCmd === 'no')  || (bool) preg_match('/^\/skip_coping\b/i', $analysisText);

    // If user tapped "Done" on the coping card → ask help-check now; show CTA on NEXT user message
    $botReplies = [];
    if ($copingCmd === 'done') {
        $botReplies[] = [
            'text'    => "Did that help even a little, {USER_FIRST}? / Nakatabang ba to bisag gamay, {USER_FIRST}?",
            'buttons' => [],
        ];
        session([$ctaAfterHelpKey => true]);   // arm CTA for next turn
        $skipRasaThisTurn = true;              // tiny transition; no Rasa this turn
    }

    $builtConsent           = false;
    $strippedCopingThisTurn = false;
    $copingShownThisTurn    = $forceCopingNow;

    if ($copingCmd === 'yes' || $copingCmd === 'no') {
        session()->forget($doorKey);
        session()->forget($helpcheckKey);
    }
    if ($forceCopingNow || $declineCoping) {
        $skipRasaThisTurn = false;
    }
    // If user declines the coping offer explicitly → validation + CTA (no loop)
if ($copingCmd === 'no' && $this->lastBotWasCopingOffer($sessionId)) {
    $name  = $this->preferredName();
    $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

    $validation = $this->pickLanguageVariant(
        "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more?",
        $lang
    );
    $validation = str_replace(['{USER_FIRST}','{USER}','{NAME}','{USER_NAME}'], [e($first), e($first), e($name), e($name)], $validation);

    $link   = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
        ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
        : (\Illuminate\Support\Facades\Route::has('appointment.index') ? route('appointment.index') : url('/appointment'));
    $ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

    $cta = $this->pickLanguageVariant(
        "If at any point you’d like more support, you can book time with the counselor. {APPOINTMENT_LINK} / ".
        "Kung gusto ka og dugang tabang, pwede ka mag-book og oras sa counselor. {APPOINTMENT_LINK}",
        $lang
    );
    $cta = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $cta);

    $b1 = Chat::create(['user_id'=>$userId,'chat_session_id'=>$sessionId,'sender'=>'bot','message'=>Crypt::encryptString($validation),'sent_at'=>now()]);
    $b2 = Chat::create(['user_id'=>$userId,'chat_session_id'=>$sessionId,'sender'=>'bot','message'=>Crypt::encryptString($cta),'sent_at'=>now()]);

    // clear all flow flags to avoid looping
    session()->forget($helpcheckKey);
    session()->forget($pendingApptKey);
    session()->forget('coping_offered_'.$sessionId);
    session()->forget('coping_shown_'.$sessionId);

    return response()->json([
        'user_message' => ['text'=>$text,'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),'sent_at'=>now()->toIso8601String()],
        'bot_reply' => [[
            'id'=>$b1->id,'text'=>$validation,'buttons'=>[],
            'time_human'=>$b1->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'=>$b1->sent_at->toIso8601String(),
        ],[
            'id'=>$b2->id,'text'=>$cta,'buttons'=>[],
            'time_human'=>$b2->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'=>$b2->sent_at->toIso8601String(),
        ]],
        'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),
    ]);
}

// If user accepts coping (copingCmd === 'yes'), you already show tips.
// After showing tips, ask a SINGLE help-check again (then rely on the handler above)


// --- NEW: If the last bot line was the help-check, interpret yes/no now.
if ($this->lastBotWasHelpCheck($sessionId)) {
    $yn = $this->parseYesNoGeneric($analysisText);

    // If user said YES: be glad + show appointment CTA. Clear flags; do NOT offer coping.
    if ($yn === 'yes') {
        $name  = $this->preferredName();
        $first = preg_split('/\s+/', $name, 2)[0] ?? $name;

        $glad = $this->pickLanguageVariant(
            "I’m glad to hear that, {USER_FIRST}. / Maayo nga nakatabang bisag gamay, {USER_FIRST}.",
            $lang
        );
        $glad = str_replace(['{USER_FIRST}','{USER}','{NAME}','{USER_NAME}'], [e($first), e($first), e($name), e($name)], $glad);

        $link   = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
            ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
            : (\Illuminate\Support\Facades\Route::has('appointment.index') ? route('appointment.index') : url('/appointment'));
        $ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

        $cta = $this->pickLanguageVariant(
            "It’s okay if this still feels heavy. You can book time with the counselor so you don’t have to handle this alone. {APPOINTMENT_LINK} / ".
            "Okay ra kung bug-at gihapon paminawon. Pwede ka mag-book og oras sa counselor aron dili nimo ni atubangon nga ikaw ra. {APPOINTMENT_LINK}",
            $lang
        );
        $cta = str_replace('{APPOINTMENT_LINK}', $ctaHtml, $cta);

        // write both messages
        $b1 = Chat::create([
            'user_id'=>$userId,'chat_session_id'=>$sessionId,'sender'=>'bot',
            'message'=>Crypt::encryptString($glad),'sent_at'=>now(),
        ]);
        $b2 = Chat::create([
            'user_id'=>$userId,'chat_session_id'=>$sessionId,'sender'=>'bot',
            'message'=>Crypt::encryptString($cta),'sent_at'=>now(),
        ]);

        // clear all “flow” flags
        session()->forget($helpcheckKey);
        session()->forget($pendingApptKey);
        session()->forget('coping_offered_'.$sessionId);
        session()->forget('coping_shown_'.$sessionId);
        session()->forget('cta_after_help_'.$sessionId);
        session()->forget('door_after_rasa_'.$sessionId);
        session()->forget('defer_rasa_until_reply_'.$sessionId);

        return response()->json([
            'user_message' => [
                'text'=>$text,
                'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'=>now()->toIso8601String(),
            ],
            'bot_reply' => [[
                'id'=>$b1->id,'text'=>$glad,'buttons'=>[],
                'time_human'=>$b1->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'=>$b1->sent_at->toIso8601String(),
            ],[
                'id'=>$b2->id,'text'=>$cta,'buttons'=>[],
                'time_human'=>$b2->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'=>$b2->sent_at->toIso8601String(),
            ]],
            'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),
        ]);
    }

    // If user said NO: offer coping consent now (single offer; no loop)
    if ($yn === 'no') {
        session(['coping_offered_'.$sessionId => true]);

        $offer = $this->pickLanguageVariant(
            "If you want, I can also share a few coping tips based on how you’re feeling. Want them now? / ".
            "Kung gusto nimo, makashare ko og pipila ka coping tips base sa imong gibati. Gusto nimo karon?",
            $lang
        );
        $buttons = [
            ['title' => 'Yes, show tips', 'payload' => '/show_coping'],
            ['title' => 'No, thanks',     'payload' => '/skip_coping'],
        ];

        $b = Chat::create([
            'user_id'=>$userId,'chat_session_id'=>$sessionId,'sender'=>'bot',
            'message'=>Crypt::encryptString($offer),'sent_at'=>now(),
        ]);

        // keep help-check “armed” for later (after tips)
        return response()->json([
            'user_message' => [
                'text'=>$text,
                'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'=>now()->toIso8601String(),
            ],
            'bot_reply' => [[
                'id'=>$b->id,'text'=>$offer,'buttons'=>$buttons,
                'time_human'=>$b->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'=>$b->sent_at->toIso8601String(),
            ]],
            'time_human'=>now()->timezone(config('app.timezone'))->format('g:i:s A'),
        ]);
    }

    // If ambiguous answer, fall through to normal handling (don’t loop)
}


    // 6) Call Rasa unless skipping
    if (!$skipRasaThisTurn) {
        $rasaUrl  = $this->rasaWebhookUrl();
        $metadata = $this->buildRasaMetadata($sessionId, $lang, $msgRisk) + [
            'user' => ['id' => $userId, 'name' => $name, 'first' => $first],
        ];
        // If Rasa was deferred last turn, allow it now (and then drop the flag)
        if (session($deferKey, false)) {
            session()->forget($deferKey);
        }

        try {
            $r = Http::timeout(8)->withOptions(['verify' => true])
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
                        $txt = isset($piece['text']) ? (string)$piece['text'] : '';
                        $btn = (isset($piece['buttons']) && is_array($piece['buttons'])) ? $piece['buttons'] : [];
                        if ($txt !== '' || !empty($btn)) $botReplies[] = ['text' => $txt, 'buttons' => $btn];
                    } else {
                        $txt = trim((string)$piece);
                        if ($txt !== '') $botReplies[] = ['text' => $txt, 'buttons' => []];
                    }
                }
            }
        } catch (\Throwable $e) {
            $botReplies = [[
                'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more? / Sige ra na, {USER_FIRST}. Ania ko maminaw. Gusto nimo mo-share pa?"
            ]];
        }
    }
    if (empty($botReplies)) {
        $botReplies = [[
            'text' => "It’s okay to feel that way, {USER_FIRST}. I’m here to listen. Would you like to share more? / Sige ra na, {USER_FIRST}. Ania ko maminaw. Gusto nimo mo-share pa?"
        ]];
    }

    // Strip coping for this turn (offer first; tips later)
    if (!$forceCopingNow && !$declineCoping) {
        $foundCoping = false;
        foreach ($botReplies as $i => $piece) {
            if ($this->looksLikeCoping((array)$piece)) {
                unset($botReplies[$i]);
                $foundCoping = true;
            }
        }
        if ($foundCoping) {
            $botReplies = array_values($botReplies);
            $strippedCopingThisTurn = true;
        }
    }

    // 7) Risk elevation + logging
    $current = $session->risk_level ?: 'low';
    $order   = ['low'=>0, 'moderate'=>1, 'high'=>2];
    $new     = ($order[$msgRisk] > $order[$current]) ? $msgRisk : $current;
    if ($new !== $current) $session->update(['risk_level' => $new]);
    $this->logActivity('risk_detected', "Risk level: {$msgRisk}", $sessionId, [
        'risk_level' => $msgRisk,
        'message_preview' => Str::limit($text, 120),
    ]);

    // 8) Appointment CTA policy:
    //    - Never show the old "You can book a time..." line.
    //    - Arm a pending flag if user asked for appointment or risk is high.
    $askedForAppt = $this->wantsAppointment($text) || $this->confirmedAfterOffer($text, $sessionId);
    if ($askedForAppt || $msgRisk === 'high') {
        session([$pendingApptKey => true]);  // remember to include CTA after help-check
    }

    // 9) After-Rasa: ALWAYS ask the quick help-check (same turn), then arm consent for next turn
if (!$skipRasaThisTurn && !empty($botReplies)) {
    $botReplies[] = [
        'text'    => "Did that help even a little, {USER_FIRST}? / Nakatabang ba to bisag gamay, {USER_FIRST}?",
        'buttons' => [],
    ];
    // Arm the “consent to coping tips” for the NEXT user message
    session([$doorKey => $count]);     // mark which user-turn we’ll expect the answer on
    session([$helpcheckKey => $count]); 
}


    // 10) If consent was armed earlier and the user has now replied, APPEND coping Yes/No
    $armedAt     = session($doorKey, null);
    $helpAskedAt = session($helpcheckKey, null);
    if ($armedAt !== null && $count > (int)$armedAt && !$forceCopingNow && !$declineCoping) {
        $botReplies[] = [
            'text' =>
                "If you want, I can also share a few coping tips based on how you’re feeling. Want them now? / " .
                "Kung gusto nimo, makashare ko og pipila ka coping tips base sa imong gibati. Gusto nimo karon?",
            'buttons' => [
                ['title' => 'Yes, show tips', 'payload' => '/show_coping'],
                ['title' => 'No, thanks',     'payload' => '/skip_coping'],
            ],
        ];
        $builtConsent = true;
        session()->forget($doorKey);
        if ($helpAskedAt !== null) session()->forget($helpcheckKey);
    }

    // 11) Empathy preface (unchanged selection logic)
    $primaryEmotion = $this->choosePrimaryEmotion($labels);
    if (!$forceCopingNow && !$declineCoping && !$suppressEmpathyForGreeting && $this->shouldPreface($sessionId, $labels, $msgRisk)) {
        $preface = $this->empathyTemplate($primaryEmotion, $msgRisk);
        array_unshift($botReplies, ['text' => $preface, 'buttons' => []]);
    }

   // 12) Acknowledge decline gently AND (if relevant) show appointment CTA immediately
if ($declineCoping) {
    array_unshift($botReplies, [
        'text'    => "No worries, {USER_FIRST}. I’m here to listen—what would you like to talk through next? / Sige, {USER_FIRST}. Naa ra ko maminaw—unsa imong gusto hisgutan sunod?",
        'buttons' => [],
    ]);

    // If user previously asked for appointment OR risk is high, show the CTA now (not later)
    if (session($pendingApptKey, false)) {
        $link   = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
            ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
            : (\Illuminate\Support\Facades\Route::has('appointment.index') ? route('appointment.index') : url('/appointment'));
        $ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

        $botReplies[] = [
            'text' =>
                "It’s okay to feel that way. If you prefer to talk with a counselor, you can book time here: {APPOINTMENT_LINK} / ".
                "Okay ra nga ingon ana imong paminaw. Kung gusto ka makig-istorya sa counselor, pwede ka mag-book dinhi: {APPOINTMENT_LINK}",
            'buttons' => [],
        ];

        // clear the pending flag so we don’t repeat the CTA
        session()->forget($pendingApptKey);
        // flag so the replacer below can swap the link token
        $builtConsent = $builtConsent ?? false; // keep var defined
        // (no need to set ctaAfterHelpKey; we are adding CTA now)
    }
}

    // 13) Early-turn coping strip (safety for very early turns)
    if (!$forceCopingNow && $count <= 3 && !$builtConsent) {
        $botReplies = array_values(array_filter(
            $botReplies,
            fn($piece) => !$this->looksLikeCoping((array)$piece)
        ));
    }

    // --- NEW: If we armed CTA-after-help-check previously, append it now and clear the flag
    $link   = \Illuminate\Support\Facades\Route::has('features.enable_appointment')
        ? \Illuminate\Support\Facades\URL::signedRoute('features.enable_appointment')
        : (\Illuminate\Support\Facades\Route::has('appointment.index') ? route('appointment.index') : url('/appointment'));
    $ctaHtml = '<a href="'.e($link).'">Book an appointment</a>';

    if (session($ctaAfterHelpKey, false)) {
        session()->forget($ctaAfterHelpKey);
        // Only show the CTA if it was relevant (pending due to high risk or explicit ask)
        if (session($pendingApptKey, false)) {
            $ctaReply =
                "It’s okay if this still feels heavy. You can book time with the counselor so you don’t have to handle this alone. {APPOINTMENT_LINK} / ".
                "Okay ra kung bug-at gihapon paminawon. Pwede ka mag-book og oras sa counselor aron dili nimo ni atubangon nga ikaw ra. {APPOINTMENT_LINK}";
            $botReplies[] = ['text' => $ctaReply, 'buttons' => []];
            session()->forget($pendingApptKey);
        }
    }

    // 14) Bridge follow-up turn-3 (guarded)
    if ($count === 3) {
        $lastTxt = trim((string)($botReplies[count($botReplies)-1]['text'] ?? ''));
        $alreadyQ = str_ends_with($lastTxt, '?');
        if (!$alreadyQ) {
            $botReplies[] = [
                'text'    => $this->bridgeFollowup($primaryEmotion, $first),
                'buttons' => [],
            ];
        }
    }

    // De-dup baseline
    $lastBot = Chat::where('chat_session_id', $sessionId)->where('sender','bot')->latest('sent_at')->first();
    $lastBotText = '';
    $lastBotTime = 0;
    if ($lastBot) {
        try { $lastBotText = $this->normalizeText(Crypt::decryptString($lastBot->message)); }
        catch (\Throwable $e) { $lastBotText = $this->normalizeText((string)$lastBot->message); }
        $lastBotTime = $lastBot->sent_at?->timestamp ?? 0;
    }

    // 15) Build final payload (with "orphan Done" suppression)
    $botPayload = [];
    foreach ($botReplies as $replyObj) {
        $replyText = (string) ($replyObj['text'] ?? '');
        $replyBtns = (isset($replyObj['buttons']) && is_array($replyObj['buttons'])) ? $replyObj['buttons'] : [];

        $replyText = $this->pickLanguageVariant($replyText, $lang);

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

        $norm = $this->normalizeText($replyText);
        if ($norm !== '' && $norm === $lastBotText && (time() - $lastBotTime) < 60 && !$this->isPureGreetingText($replyText)) {
            continue;
        }

        // Normalize buttons — drop "Done" if coping isn't actually shown this turn
        $normalizedBtns = [];
        foreach ($replyBtns as $b) {
            $title   = (string)($b['title'] ?? 'Open');
            $payload = $b['payload'] ?? null;
            $url     = $b['url'] ?? null;

            if (!$copingShownThisTurn && preg_match('/^\s*done\s*$/i', $title)) {
                continue;
            }

            if (is_string($payload) && trim($payload) === '{APPOINTMENT_LINK}') {
                $normalizedBtns[] = ['title' => $title, 'url' => $link];
            } else {
                $one = ['title' => $title];
                if ($url)     $one['url']     = $url;
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

        $lastBotText = $norm;
        $lastBotTime = time();

        $botPayload[] = [
            'id'         => $bot->id,
            'text'       => $replyText,
            'buttons'    => $normalizedBtns,
            'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
            'sent_at'    => $bot->sent_at->toIso8601String(),
        ];
    }

    // --- Guarantee we end with a question unless consent just fired
    if (!$builtConsent) {
        if (empty($botPayload)) {
            $pref = $this->empathyTemplate($primaryEmotion ?? null, $msgRisk);
            $pref = $this->pickLanguageVariant($pref, $lang);
            $pref = str_replace(['{USER_NAME}','{USER_FIRST}','{USER}','{NAME}'], [e($name), e($first), e($first), e($name)], $pref);

            $bot = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => Crypt::encryptString($pref),
                'sent_at'         => now(),
            ]);
            $botPayload[] = [
                'id'         => $bot->id,
                'text'       => $pref,
                'buttons'    => [],
                'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'    => $bot->sent_at->toIso8601String(),
            ];
        }

        $last    = $botPayload[count($botPayload)-1] ?? null;
        $lastTxt = (string)($last['text'] ?? '');
        $lastHasBtn = !empty($last['buttons']);

        if (!$lastHasBtn && !$this->endsWithQuestion($lastTxt)) {
            $q = $this->bridgeFollowup($primaryEmotion ?? null, $first);
            $q = $this->pickLanguageVariant($q, $lang);

            $bot = Chat::create([
                'user_id'         => $userId,
                'chat_session_id' => $sessionId,
                'sender'          => 'bot',
                'message'         => Crypt::encryptString($q),
                'sent_at'         => now(),
            ]);
            $botPayload[] = [
                'id'         => $bot->id,
                'text'       => $q,
                'buttons'    => [],
                'time_human' => $bot->sent_at->timezone(config('app.timezone'))->format('g:i:s A'),
                'sent_at'    => $bot->sent_at->toIso8601String(),
            ];
        }
    }

    // 16) Return
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
    $t    = trim((string)($piece['text'] ?? ''));
    $btns = (array)($piece['buttons'] ?? []);

    // Text contains coping-ish cues or "tap Done" phrasing
    if ($t !== '' && preg_match('/\b(coping|coping tips|tips|grounding|breathing|relaxation|self[- ]care|techniques?|try one tip|when you(?:’|\'|)re finished|tap done)\b/i', $t)) {
        return true;
    }

    // Buttons: treat "Done" and coping-related payloads as coping
    foreach ($btns as $b) {
        $title   = (string)($b['title'] ?? '');
        $payload = (string)($b['payload'] ?? '');

        if ($title !== '' && preg_match('/^\s*done\s*$/i', $title)) {
            return true;
        }
        if ($payload !== '' && preg_match('#/(?:show_)?coping|coping_(?:start|step|done)#i', $payload)) {
            return true;
        }
        if ($title !== '' && preg_match('/\b(tips|coping|show|grounding|breathing)\b/i', $title)) {
            return true;
        }
    }

    return false;
}


private function bridgeFollowup(?string $emotion, string $nameFirst): string
{
    // EN / CEB lines (split with " / " so pickLanguageVariant() still works)
    $variants = [
        // direct “helped a little” checks
        "Did that help even a little, {N}? / Nakatabang ba to bisag gamay, {N}?",
        "Did any part of that help a bit, {N}? / Nakatabang ba og gamay ang bisan usa ato, {N}?",
        "Do you feel even a tiny bit lighter after that, {N}? / Murag nibug-at gamay ang gibati nimo human ato, {N}?",

        // “eased/relieved” feeling checks
        "Did that ease things even a little, {N}? / Nikanubag ba gamay ang gibati nimo, {N}?",
        "Did it relieve a bit of the heaviness, {N}? / Nakuha-an ba gamay ang kabug-at, {N}?",
        "Is it slightly easier to breathe now, {N}? / Mas sayon ba gamay ginhawa karon, {N}?",

        // reflective + still checking relief
        "Do you feel even a bit more okay now, {N}? / Human ato, mas okay ba gamay ang paminaw nimo, {N}?",
        "Did that make things feel the tiniest bit more manageable, {N}? / Mas madala ba gamay karon, {N}?",
    ];

    // Rotate to avoid repetition
    $i    = (int) session('bridge_idx', -1);
    $next = ($i + 1) % count($variants);
    session(['bridge_idx' => $next]);

    return str_replace('{N}', $nameFirst, $variants[$next]);
}
// Detect if the last bot message was the "coping offer" prompt
private function lastBotWasCopingOffer(int $sessionId): bool
{
    $last = \App\Models\Chat::where('chat_session_id', $sessionId)
        ->where('sender','bot')->latest('sent_at')->first();
    if (!$last) return false;

    try { $txt = \Illuminate\Support\Facades\Crypt::decryptString($last->message); }
    catch (\Throwable $e) { $txt = (string)$last->message; }

    $t = mb_strtolower($txt ?? '');
    // keep this text check aligned with the offer you build later
    return str_contains($t, 'share a few coping tips') && str_contains($t, 'want them now');
}

// Make parseCopingCommand contextual: only honor yes/no/done if it’s answering the coping offer.
private function parseCopingCommand(string $text, int $sessionId): ?string
{
    $t = trim(mb_strtolower($text));
    $isOfferContext = $this->lastBotWasCopingOffer($sessionId);

    // Only accept yes/no when the last bot turn was *the* coping offer
    if ($isOfferContext) {
        if (in_array($t, ['yes, show tips','show tips','/show_coping','yes'], true)) return 'yes';
        if (in_array($t, ['no, thanks','no thanks','/skip_coping','no'], true))       return 'no';
    }

    // Accept "done" only when we are inside the coping card (button title/payload)
    if (in_array($t, ['done','/coping_done','/coping_step_done'], true)) return 'done';

    return null;
}

private function normalizeText(string $t): string
{
    $t = mb_strtolower(trim($t));
    // collapse whitespace + strip zero-width chars
    $t = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]+/u', '', $t);
    $t = preg_replace('/\s+/u', ' ', $t);
    return $t ?? '';
}

private function isUserGreeting(string $t): bool
{
    $t = $this->normalizeText($t);              // lower, trim, collapse spaces/ZW chars

    // 1) If there’s clear content, don’t treat as greeting
    if (preg_match('/\b('.
        // common “content” intents
        'i\s*feel|i\'?m|ako|nasuko|nagool|kapoy|problem|issue|need\s+help|please\s+help|help\s+me|'.
        'anxious|anxiety|panic|sad|depress|stress|lonely|scared|worried|suicid|die|'.
        'appointment|schedule|book|counsel(?:or|ling)?|therap(?:y|ist)?'.
    ')\b/u', $t)) {
        return false;
    }

    // Strip emoji variation selectors & skin tones so 👋🏻/👋🏾 etc. match
    $emojiStripped = preg_replace('/[\x{FE0F}\x{1F3FB}-\x{1F3FF}]/u', '', $t ?? '');

    // Pure emoji / waves
    if (preg_match('/^\p{Zs}*(👋|🤝|🙏|☺️|😊)+\p{Zs}*$/u', $emojiStripped)) {
        return true;
    }

    // 3) Canonical greeting tokens (allow stretched letters with +)
    $greetCore = '(?:hi+|hello+|helo+|hey+|hiya+|yo+|howdy|g\'?day|alo+h?a|bonjour|hola|mabuhay|oi+|oy+)';
    $greetSlang = '(?:sup+|wass?up+|whats?\s*up+)';
    $goodTime   = '(?:good\s*(?:morning|afternoon|evening|day|night)|gm+|ga+|ge+|gn+|gd\s*(?:am|pm))';

    // Tagalog / Cebuano
    $tagalogHi  = '(?:kam?usta+|kum?usta+|musta+h?)';
    $tagalogGM  = '(?:magandang\s*(?:umaga|tanghali|hapon|gabi|araw))';
    $cebGM      = '(?:maayong\s*(?:buntag|udto|hapon|gabii|adlaw))';
    $cebHi      = '(?:ayo+)';

    // 4) Allowed “tails” after a greeting (names, bot, etc.), typos & plurals allowed
    $tail = '(?:there|po|sir|ma\'?am|everyone|guys|team|class|all|'.
            'lumi|lumi\s*chat|lumichat+|lumichatt+|'.
            'chat+|chats?|bot|ai|assistant|kuya|ate)';

    // 5) Super-simple short form: ≤ 5 tokens, starts with a greeting, rest are tails
    $tokens = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    if (count($tokens) > 0 && count($tokens) <= 5) {
        $first = $tokens[0] ?? '';
        if (
            preg_match('/^(' . $greetCore . '|' . $greetSlang . '|' . $goodTime . '|' . $tagalogHi . '|' . $tagalogGM . '|' . $cebGM . '|' . $cebHi . ')$/u', $first)
        ) {
            // if every remaining token is an allowed tail (e.g., "hi chatt", "hello lumi chat", "gm po")
            $restOk = true;
            for ($i = 1; $i < count($tokens); $i++) {
                if (!preg_match('/^' . $tail . '$/u', $tokens[$i])) {
                    $restOk = false; break;
                }
            }
            if ($restOk) return true;
        }
    }

    // 6) Phrase-style greetings (e.g., "hello there", "hi, good evening", "magandang gabi po")
    if (preg_match('/^(' . $greetCore . '|' . $greetSlang . ')\s+(' . $tail . '|' . $goodTime . '|' . $tagalogHi . '|' . $tagalogGM . '|' . $cebGM . ')(\s+po)?$/u', $t)) {
        return true;
    }
    if (preg_match('/^(' . $tagalogGM . '|' . $cebGM . ')\s*(po|sir|ma\'?am)?$/u', $t)) {
        return true;
    }

    // 7) Ultra-short stretched greeting alone (e.g., "hiiiiii", "helloooo")
    if (preg_match('/^(' . $greetCore . ')$/u', $t)) return true;

    return false;
}



private function looksLikeGreeting(string $t): bool
{
    $t = $this->normalizeText($t);
    // Only the flat opener should ever be suppressed; keep supportive lines.
    return (bool) preg_match(
        '/\b(hi|hello|hey)\b.*\b(how (?:can|may) i (?:help|assist) (?:you )?today)\b/u',
        $t
    );
}
private function endsWithQuestion(string $s): bool
{
    $s = rtrim($s);
    return $s !== '' && preg_match('/[?？！]$/u', $s) === 1;
}
private function isPureGreetingText(string $s): bool
{
    $t = $this->normalizeText($s);
    $emojiStripped = preg_replace('/[\x{FE0F}\x{1F3FB}-\x{1F3FF}]/u', '', $t ?? '');
    return (bool) (
        preg_match('/^(hi+|hello+|hey+|good (morning|afternoon|evening)|kumusta|kam?usta|musta)[.! ]*$/u', $t)
        || preg_match('/^\p{Zs}*(👋|🤝|🙏|☺️|😊)+\p{Zs}*$/u', $emojiStripped)
    );
}

// Was the last bot line the "Did that help even a little?" check?
private function lastBotWasHelpCheck(int $sessionId): bool
{
    $last = \App\Models\Chat::where('chat_session_id', $sessionId)
        ->where('sender','bot')->latest('sent_at')->first();
    if (!$last) return false;

    try { $txt = \Illuminate\Support\Facades\Crypt::decryptString($last->message); }
    catch (\Throwable $e) { $txt = (string)$last->message; }

    $t = $this->normalizeText($txt ?? '');
    return (bool)preg_match('/\b(did (that|it) (help|ease|relieve)|feel .* (lighter|easier))\b/u', $t);
}

private function parseYesNoGeneric(string $text): ?string
{
    $t = $this->normalizeText($text);
    if (preg_match('/\b(yes|yep|yeah|yup|sure|ok|okay|sige|oo|opo)\b/u', $t)) return 'yes';
    if (preg_match('/\b(no|nope|not really|di(?:li)?|hindi|wag|pass)\b/u', $t)) return 'no';
    return null;
}

}
