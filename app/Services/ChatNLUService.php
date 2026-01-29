<?php

namespace App\Services;

/**
 * ChatNLUService - Natural Language Understanding for mental health chatbot
 * 
 * Handles all text analysis, emotion detection, intent classification, and risk assessment.
 */
class ChatNLUService
{
    /**
     * Core normalization + typo softening (English only).
     */
    public function normalize(string $raw): string
    {
        $s = preg_replace('/[\p{Cf}\p{Cc}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $raw) ?? '';
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        
        // Remove apostrophes first (don't → dont, can't → cant, I'm → im)
        $s = str_replace("'", '', $s);
        
        // Common typo corrections for emotion/mental health words
        $typoMap = [
            // Emotion words
            'depresed' => 'depressed', 'depressd' => 'depressed', 'deprsd' => 'depressed',
            'anxios' => 'anxious', 'anxeos' => 'anxious', 'anxety' => 'anxiety',
            'overwelmed' => 'overwhelmed', 'overwelm' => 'overwhelm',
            'stresed' => 'stressed', 'stressd' => 'stressed',
            'disapointed' => 'disappointed', 'dissapointed' => 'disappointed',
            'fustrated' => 'frustrated', 'frustr8ed' => 'frustrated',
            'lonly' => 'lonely', 'lonley' => 'lonely',
            'tierd' => 'tired', 'exausted' => 'exhausted',
            'scarred' => 'scared', 'scred' => 'scared',
            'confuzed' => 'confused', 'confusd' => 'confused',
            
            // Actions
            'faild' => 'failed', 'failld' => 'failed', 'faled' => 'failed',
            'strugle' => 'struggle', 'strugling' => 'struggling',
            'wory' => 'worry', 'woried' => 'worried', 'woryng' => 'worrying',
            
            // Common words
            'becuase' => 'because', 'becuz' => 'because', 'cuz' => 'because',
            'realy' => 'really', 'rly' => 'really',
            'definately' => 'definitely', 'definetly' => 'definitely',
            'seperate' => 'separate',
        ];
        
        $s = mb_strtolower($s);
        foreach ($typoMap as $typo => $correct) {
            $s = str_replace($typo, $correct, $s);
        }
        
        // leetspeak & common swaps
        $s = strtr($s, [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i'
        ]);
        
        // collapse 3+ repeats -> 2 (e.g., "soooo"→"soo")
        $s = preg_replace('/([a-z])\1{2,}/iu', '$1$1', $s);
        
        // spaces around punctuation to help tokenization
        $s = preg_replace('/([^\w\s])/u', ' $1 ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        
        return trim($s);
    }

    /**
     * Token list (a–z only)
     */
    public function tokens(string $t): array
    {
        preg_match_all('/[a-z]+/u', $t, $m);
        return $m[0] ?? [];
    }

    /**
     * Check if any word from terms appears in text (fuzzy match with typo tolerance)
     */
    public function hasAnyWord(string $text, array $terms): bool
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
                        continue; // prevents "sad" matching "mad"
                    }
                    if (levenshtein($tok, $term) <= 1) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if any word from terms appears in text (exact match only)
     */
    public function hasAnyWordExact(string $text, array $terms): bool
    {
        $tokens = $this->tokens($text);
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

    /**
     * Regex builder allowing spaces/hyphens between words and minor letter swaps.
     */
    private function flex(string $phrase): string
    {
        $letters = [
            'a' => '[a@4]', 'e' => '[e3]', 'i' => '[i1!]', 'o' => '[o0]', 's' => '[s5$]', 't' => '[t7]', 'l' => '[l1]',
        ];
        $out = preg_replace_callback('/[a-z]/i', fn($m) => ($letters[strtolower($m[0])] ?? $m[0]), $phrase);
        $out = preg_replace('/\s+/', '\s*[- ]?\s*', $out);
        return $out;
    }

    /**
     * Rich risk assessment with typo/slang + EN / Taglish coverage.
     * 
     * @return array ['level' => 'low'|'moderate'|'high', 'hits' => array]
     */
    public function assessRisk(string $raw): array
    {
        $t = $this->normalize($raw);

        // Negation shield – "I don't want to die / hurt myself"
        $negationShield = false;

        // English
        if (preg_match(
            '/\bi\s*(don\'?t|do\s+not)\s*(want|plan|intend)\s*to\s*'
            . '(die|kill myself|hurt myself|end my life|harm myself)\b/u',
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

        // Slang / shorthand that directly implies self-harm/suicide
        $slangHigh = [
            'kms', 'kys', 'end it', 'end it all',
            'unalive',
            'i can\'t go on', 'cant go on', 'can\'t go on',
            'no reason to live', 'nothing to live for',
            'life is pointless', 'life is meaningless',
        ];

        foreach ($slangHigh as $s) {
            if (preg_match('/\b' . $this->flex($s) . '\b/u', $t)) {
                return [
                    'level' => $negationShield ? 'moderate' : 'high',
                    'hits'  => [$s],
                ];
            }
        }

        // Explicit HIGH phrases (methods, direct self-harm)
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

            // "Kill me" variants
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
            if (preg_match('/\b' . $this->flex($p) . '\b/u', $t)) {
                return [
                    'level' => $negationShield ? 'moderate' : 'high',
                    'hits'  => [$p],
                ];
            }
        }

        // Standalone "die" / "mamatay" etc. in emotional/personal context
        $criticalActs = ['die', 'mamatay', 'magpakamatay'];

        if (!$negationShield && $this->hasAnyWordExact($t, $criticalActs)) {
            $hasSelf = (bool)preg_match(
                '/\b(i|im|i\'m|ive|i\'ve|me|my|mine|myself|ako|ko|akin)\b/u',
                $t
            );

            $hasEmotionContext = $this->hasAnyWord($t, [
                'sad', 'down', 'tired', 'hopeless', 'lonely', 'empty', 'numb',
                'pointless', 'meaningless', 'worthless', 'useless', 'burden',
                'hurt', 'hurting', 'pain', 'scared', 'afraid',
                'smile', 'happy', 'peaceful', 'okay', 'ok',
            ]);

            $isShort = mb_strlen($t) <= 30;

            if ($hasSelf || $hasEmotionContext || $isShort) {
                return [
                    'level' => $negationShield ? 'moderate' : 'high',
                    'hits'  => ['die_direct'],
                ];
            }
        }

        // Intent + act within proximity (~8 tokens), both orders
        $intent = '(wanna|want|plan|planning|think|thinking|feel like|should|will|might|need|tryna|trying|balak|plano|gusto ko)';
        $act    = '('
            . 'suic(?:ide|de|e)'
            . '|die'
            . '|unalive'
            . '|kill\s*my\s*self'
            . '|end\s*my\s*life'
            . '|end\s*it'
            . '|overdose'
            . '|hang'
            . '|jump'
            . '|cut'
            . '|self[- ]?harm'
            . '|hurt\s*my\s*self'
            . '|magpakamatay'
            . '|mamatay'
            . '|saktan\s+ang\s+sarili'
            . '|maglaslas'
            . ')';

        if (
            preg_match('/\b' . $intent . '\b(?:\W+\w+){0,8}\b' . $act . '\b/iu', $t)
            || preg_match('/\b' . $act . '\b(?:\W+\w+){0,8}\b' . $intent . '\b/iu', $t)
        ) {
            return [
                'level' => $negationShield ? 'moderate' : 'high',
                'hits'  => ['intent+act'],
            ];
        }

        // MODERATE – expanded hopeless / self-hatred set
        $moderate = [
            'i hate myself', 'i hat myself', 'i hte myself',
            'i am worthless', 'worthles', 'worthless', 'useless', 'i am a burden', 'burdn',
            'i\'m a burden', 'im a burden', 'feeling like a burden',

            'i want to disappear', 'i wanna disappear',
            'i dont want to exist', 'i don\'t want to exist',
            'i wish i never existed', 'i wish i wasn\'t here',

            'feel like dying', 'feel lik dyin',
            'give up on life', 'i give up on life', 'i want to give up',
            'tired of everything', 'done with everything',
            'pagod na ako sa lahat', 'pagod na ako', 'sobra na pagod ko',
            'ayoko na', 'sawa na ako', 'sawang sawa na ako',

            'overwhelmed', 'overwhelm',
            'burnout', 'burn out', 'burned out',
            'panic', 'anxiety', 'anxious',
            'depressed', 'depressd', 'depresed', 'deprssd', 'deprsd',

            'i am not okay', 'im not ok', 'i\'m not ok', 'im not okay', 'i\'m not okay',
            'not okey', 'not ok',
            'i am not okey', 'i am not fine', 'im not fine', 'i\'m not fine',
        ];

        foreach ($moderate as $p) {
            if (preg_match('/\b' . $this->flex($p) . '\b/u', $t)) {
                return ['level' => 'moderate', 'hits' => [$p]];
            }
        }

        // Default
        return ['level' => 'low', 'hits' => []];
    }

    /**
     * Broad emotion tagging (with misspellings, synonyms, and key phrases).
     * 
     * @return array List of detected emotion labels
     */
    public function labelEmotions(string $raw): array
    {
        $t      = $this->normalize($raw);
        $labels = [];

        $map = [
            'sad' => [
                'sad', 'down', 'blue', 'low', 'tearful', 'cry', 'crying', 'cried', 'grief',
                'heartbroken', 'brokenhearted', 'broken inside', 'hurt inside',
                'depressed', 'depressd', 'depresed', 'deprssd', 'deprsd',
                'empty inside', 'numb inside', 'numb',
            ],

            'disappointed' => [
                'disappoint', 'disappointed', 'dissapointed', 'dissappointed', 'disapointed',
                'let down', 'letdown', 'let me down',
                'failed', 'fail', 'failing', 'failure',
                'didnt make it', 'didn\'t make it', 'cant do it', 'can\'t do it',
                'not good enough', 'not enough',
            ],
            
            'anxious' => [
                'anxious', 'anxiety', 'anxety', 'anxios',
                'panicky', 'panic', 'panicking',
                'afraid', 'scared', 'terrified', 'fearful', 'worried', 'worrying',
                'nervous', 'on edge', 'uneasy',
                'grade', 'grades', 'gpa', 'score', 'scores',
                'performance', 'mess up', 'messed up', 'screwed up', 'screw up',
            ],

            'stressed' => [
                'stress', 'stressed', 'stressing',
                'pressure', 'pressured', 'under pressure',
                'overwhelm', 'overwhelmed', 'too much',
                'burnout', 'burn out', 'burned out',
                'academic pressure', 'school pressure',
                'deadline', 'deadlines', 'requirements',
            ],

            'tired' => [
                'tired', 'tiring', 'exhausted', 'exhausting', 'fatigued', 'fatigue',
                'drained', 'draining', 'worn out', 'wornout',
                'no energy', 'low energy', 'so tired', 'very tired',
            ],

            'angry' => [
                'angry', 'mad', 'furious', 'rage', 'raging',
                'irritated', 'annoyed', 'annoying', 'pissed', 'pissed off',
                'frustrated', 'frustrating', 'fed up', 'sick of this', 'sick of it',
            ],

            'lonely' => [
                'lonely', 'alone', 'isolated', 'left out', 'leftout',
                'no one understands', 'nobody understands', 'no one cares', 'nobody cares',
            ],

            'hopeless' => [
                'hopeless', 'no hope', 'pointless', 'meaningless',
                'no reason to live', 'nothing to live for', 'give up', 'giving up',
                'worthless', 'useless',
            ],

            'not_ok' => [
                'not ok', 'not okay', 'not fine', 'not okey',
                'i am not okay', 'im not ok', 'i\'m not ok', 'i\'m not okay', 'im not okay',
                'i am not fine', 'im not fine', 'i\'m not fine',
            ],

            'overwhelmed' => [
                'overwhelmed', 'cant cope', 'can\'t cope', 'cannot cope',
                'too much', 'too many things', 'everything piling up',
            ],

            'guilty' => [
                'guilty', 'guilt', 'my fault', 'all my fault', 'blame myself', 'blaming myself',
            ],

            'ashamed' => [
                'ashamed', 'shame', 'embarrassed', 'embarrassing', 'humiliated',
            ],

            'confused' => [
                'confused', 'confusing', 'lost', 'dont know what to do', 'don\'t know what to do',
                'unsure', 'uncertain', 'mixed up',
                'dont know', 'don\'t know', 'idk', 'no idea',
                'what do i do', 'what should i do', 'what can i do',
            ],

            'bored' => [
                'bored', 'boredom', 'meh', 'nothing to do', 'tired of this routine',
            ],

            'overthinking' => [
                'overthink', 'overthinking', 'can\'t stop thinking', 'cant stop thinking',
                'thoughts won\'t stop', 'thoughts wont stop', 'in my head a lot', 'in my head too much',
            ],
        ];

        foreach ($map as $label => $terms) {
            if ($this->hasAnyWord($t, $terms)) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * Intent classification (English only) with typo coverage + question guard.
     * 
     * @return array ['flags' => [...], 'score' => [...]]
     */
    public function classifyIntents(string $raw): array
    {
        $t = $this->normalize($raw);

        // Goodbye / closing detection
        $goodbye = false;

        $closingPattern = '/^\s*('
            . '((ok|okay|k)\s*)?bye'
            . '|bye\s+bye'
            . '|good\s*bye'
            . '|goodbye'
            . '|good\s*night'
            . '|goodnight'
            . '|see\s+you(?:\s+soon)?'
            . '|talk\s+to\s+you\s+later'
            . '|ttyl'
            . '|take\s*care'
            . '|thanks\s*,?\s*bye'
            . '|thank\s+you\s*,?\s*bye'
            . ')\s*[!.…]*\s*$/iu';

        if (preg_match($closingPattern, $t)) {
            $goodbye = true;
        }

        // Bot intro / capabilities
        $asksCapabilities = false;

        if (preg_match(
            '/\b('
                . 'what\s+can\s+(you|u)\s+do'
                . '|what\s+do\s+you\s+do'
                . '|what\s+can\s+this\s+(bot|chatbot|app)\s+do'
                . '|what\s+is\s+(lumichat|this\s+bot|this\s+chatbot)'
                . '|who\s+are\s+you'
                . '|what\s+are\s+you'
                . '|how\s+can\s+you\s+help\s+me'
                . '|what\s+can\s+you\s+help\s+me\s+with'
                . ')\b/iu',
            $t
        )) {
            $asksCapabilities = true;
        }

        // Appointment intent
        $action  = '(appoint(?:ment)?|apointment|schedule|schedual|book(?:ing)?|bok|reserve|set\s*up)';
        $role    = '(counsel(?:or|ler)|counsellor|councelor|counslor|therap(?:ist|y)|advisor|someone to talk)';

        $wantsAppt = (bool)(
            (preg_match('/\b' . $action . '\b/iu', $t) && preg_match('/\b' . $role . '\b/iu', $t))
            || preg_match('/\bsee\s+(?:a\s+)?' . $role . '\b/iu', $t)
        );

        if (!$wantsAppt) {
            $hasBookingVerb = (bool)preg_match(
                '/\b(appoint(?:ment)?|apointment|schedule|schedual|book(?:ing)?|bok|reserve)\b/iu',
                $t
            );

            $hasRequestPhrase = (bool)preg_match(
                '/\b(i\s+want|i\'d\s+like|i\s+like\s+to|can\s+i|could\s+i|please)\b/iu',
                $t
            );

            $shortBookingOnly = (bool)preg_match('/\bi\s+want\s+to\s+book\b/iu', $t)
                || (preg_match('/\bbook\b/iu', $t) && mb_strlen($t) <= 40);

            if (($hasBookingVerb && $hasRequestPhrase) || $shortBookingOnly) {
                $wantsAppt = true;
            }
        }

        // Coping tips/help
        $coping = [
            'coping tips', 'cope tips', 'help me cope', 'ways to cope',
            'how to deal', 'how do i deal', 'how to handle', 'how do i handle',
            'advice for this', 'what can i do', 'give me tips', 'share tips', 'show tips',
            'grounding', 'breathing exercise', 'breath exercise'
        ];
        $wantsCoping = false;
        foreach ($coping as $p) {
            if (preg_match('/\b' . $this->flex($p) . '\b/u', $t)) {
                $wantsCoping = true;
                break;
            }
        }

        // Question detection
        $hasQmark = str_contains($t, '?');
        $startsWh = (bool)preg_match('/^\s*(how|what|why|can|should|where|when|who|which)\b/u', $t);
        $dontKnow = (bool)preg_match('/\bi\s*(don\'?t|do\s+not)\s+know\s+(why|what|how)\b/u', $t);
        $isQuestion = ($hasQmark || $startsWh) && !$dontKnow;

        // Refuse to share
        $refuseShare = (bool)preg_match(
            '/\b(i\s*(?:don\'?t|do\s*not)\s*(?:want|feel like)\s*(?:to\s*)?(talk|share|say)'
                . '|prefer\s*not\s*to\s*(say|share|talk)'
                . '|not\s*now|maybe\s*later|not\s*ready|skip|pass)\b/u',
            $t
        );

        // Yes / No
        $yes = (bool)preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|go ahead|proceed|please)\b/u', $t);
        $no  = (bool)preg_match('/\b(no|nope|not now|later|pass)\b/u', $t);

        // Done (finished coping / conversation for now)
        $done = false;

        if (preg_match('~/(coping_done|done_coping|finish_coping)~i', $t)) {
            $done = true;
        }

        if (!$done) {
            $done = (bool)preg_match(
                '/\b('
                    . 'done for now'
                    . '|i\'?m done'
                    . '|im done'
                    . '|that\'?s enough'
                    . '|that is enough'
                    . '|that\'?s all'
                    . '|that is all'
                    . '|i\'?m okay now'
                    . '|im okay now'
                    . '|i am okay now'
                    . '|i\'?m fine now'
                    . '|im fine now'
                    . '|i am fine now'
                    . ')\b/u',
                $t
            );
        }

        if (!$done) {
            $short = trim($t);
            if (in_array($short, ['done'], true)) {
                $done = true;
            }
        }

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
                'goodbye'           => $goodbye,
            ],
            'score' => [
                'length'            => mb_strlen($t),
                'wants_appointment' => $wantsAppt ? 1.0 : 0.0,
                'wants_coping'      => $wantsCoping ? 1.0 : 0.0,
                'is_question'       => $isQuestion ? 1.0 : 0.0,
            ],
        ];
    }

    /**
     * Detect clearly non–mental-health topics (games, homework, general info, etc.).
     */
    public function isNonMentalTopic(string $norm, array $labels, array $riskStruct, array $flags): bool
    {
        // Capability / intro questions should always be answered by Lumi
        if ($flags['asks_capabilities'] ?? false) {
            return false;
        }
        
        $risk = $riskStruct['level'] ?? 'low';

        // YES/NO/DONE replies are always contextual → NEVER treat as non-mental
        if (($flags['yes'] ?? false) || ($flags['no'] ?? false) || ($flags['done'] ?? false)) {
            return false;
        }

        // If there is ANY emotional or risk signal → treat as mental-health-related
        if ($risk !== 'low') return false;
        if (!empty($labels)) return false;
        if ($flags['wants_appointment'] ?? false) return false;
        if ($flags['wants_coping'] ?? false) return false;

        // GREETINGS: "hi", "hello", etc. should NOT be treated as non-mental
        if ($this->hasAnyWord($norm, [
            'hi', 'hello', 'hey', 'helo', 'hii',
            'good morning', 'good afternoon', 'good evening',
        ])) {
            if (mb_strlen($norm) <= 40 && !($flags['is_question'] ?? false)) {
                return false;
            }
        }

        // If it already talks about problems / struggle / thoughts / hard time → keep as mental
        if ($this->hasAnyWord($norm, [
            'stress', 'stressed', 'anxiety', 'anxious', 'depress', 'depressed', 'sad',
            'overwhelmed', 'lonely', 'tired', 'burnout', 'panic',
            'problem', 'problems', 'struggle', 'struggling', 'struggles',
            'worry', 'worried', 'scared', 'scary', 'afraid',
            'hurt', 'hurting', 'pain', 'painful',
            'feel', 'feeling', 'feels', 'felt',
            'mind', 'thought', 'thoughts', 'overthink', 'overthinking', 'in my head',
            'empty', 'numb', 'lost', 'drained', 'hard', 'difficult',
            'okay', 'not ok', 'not okay', 'not fine', 'not okey',
            'wrong', 'weird', 'off',
        ])) {
            return false;
        }

        // Self-disclosure / "opening up" gets treated as mental even if there are school/game words
        $selfDisclosure = $this->looksLikeSelfDisclosure($norm);
        if ($selfDisclosure) {
            return false;
        }

        // Keywords that usually indicate general / non-mental-health / factual topics
        $nonMentalKeywords = [
            // Games / entertainment / pop culture
            'game', 'games', 'gaming', 'gamer', 'rank', 'mmr',
            'steam', 'valorant', 'valo', 'dota', 'gta', 'minecraft', 'roblox',
            'ml', 'mobile legends', 'mlbb', 'league of legends', 'lol', 'wild rift',
            'cod', 'call of duty', 'pubg', 'genshin', 'honkai', 'fortnite',
            'ps4', 'ps5', 'playstation', 'xbox', 'nintendo', 'switch', 'console', 'skin', 'skins', 'battle pass',
            'music', 'song', 'songs', 'album', 'playlist', 'lyrics',
            'movie', 'movies', 'film', 'films', 'cinema', 'series', 'episode', 'episodes',
            'kdrama', 'anime', 'manga', 'netflix', 'disney', 'disney+', 'spotify', 'tiktok', 'youtube',
            'idol', 'kpop', 'bts', 'blackpink', 'twice', 'enhypen', 'newjeans',

            // Food, recipes, places to eat
            'food', 'foods', 'recipe', 'recipes', 'cook', 'cooking', 'bake', 'baking',
            'restaurant', 'restaurants', 'cafe', 'cafes', 'milk tea', 'milktea', 'coffee shop', 'coffee',

            // Tech / coding / computer support
            'programming', 'coding', 'code', 'javascript', 'php', 'laravel', 'python',
            'github', 'git', 'bug', 'bugs', 'error', 'errors',
            'wifi', 'router', 'laptop', 'pc', 'phone', 'android', 'iphone',

            // Academics / subject-only content
            'math', 'algebra', 'physics', 'chemistry', 'biology', 'science',
            'module', 'modules', 'assignment', 'assignments', 'homework', 'seatwork',
            'quiz', 'quizzes', 'exam', 'exams', 'test', 'tests', 'midterm', 'finals',
        ];

        $nonMentalHits = $this->countKeywordHits($norm, $nonMentalKeywords);

        // Strong non-mental signal: at least 2 separate hits
        if ($nonMentalHits >= 2 && !$selfDisclosure) {
            return true;
        }

        // Single-keyword case: Only treat as non-mental if short & factual-sounding
        if ($nonMentalHits === 1 && !$selfDisclosure) {
            $tokens = $this->tokens($norm);
            if (mb_strlen($norm) <= 60 && count($tokens) <= 8) {
                return true;
            }
        }

        // Fallback: token/length-based garbage detection
        $tokens = $this->tokens($norm);
        if (empty($tokens)) {
            return true;
        }

        // Short random text with no basic meaning and not self-disclosure → non-mental
        if (count($tokens) <= 3 && mb_strlen($norm) <= 40) {
            if (
                !$this->hasAnyWord($norm, [
                    'help', 'support', 'counselor', 'counselling', 'counseling',
                    'problem', 'problems', 'sad', 'anxious', 'anxiety', 'depress', 'stress', 'worried',
                    'feel', 'feeling', 'feels', 'mind', 'thought', 'thoughts',
                ])
                && !$selfDisclosure
            ) {
                return true;
            }
        }

        // Default: treat as mental-health-related (safer)
        return false;
    }

    /**
     * Detect when the input is basically not understandable (random chars, no clear words).
     */
    public function isUnreadableInput(string $norm): bool
    {
        $norm = trim($norm);

        // Completely empty after normalization
        if ($norm === '') {
            return true;
        }

        // No letters at all (pure emojis, numbers, symbols)
        if (!preg_match('/[a-z]/i', $norm)) {
            return true;
        }

        // Tokenize – if nothing looks like a word at all
        $tokens = $this->tokens($norm);
        if (empty($tokens)) {
            return true;
        }

        // If ANY token is a clear, normal short word, treat as readable
        $whitelistTokens = [
            'hi', 'hey', 'hello',
            'ok', 'okay',
            'yes', 'no',
            'hmm', 'lol',
            'sad', 'help',
            'me', 'you', 'i',
            'bye', 'goodbye', 'night', 'goodnight', 'gn', 'tc', 'thanks', 'thank'
        ];

        foreach ($tokens as $tk) {
            if (in_array(mb_strtolower($tk), $whitelistTokens, true)) {
                return false; // definitely readable
            }
        }

        // Short mixed "garbage": few tokens, small length, has digits/symbols
        if (count($tokens) <= 2 && mb_strlen($norm) <= 8) {
            $hasDigitOrSymbol = (bool)preg_match('/[\d\W_]/u', $norm);

            $basicMeaningful = [
                'feel', 'feeling', 'tired', 'stressed', 'anxious', 'sad', 'angry', 'lonely',
                'fine', 'not', 'okay', 'ok', 'help', 'support'
            ];

            if ($hasDigitOrSymbol && !$this->hasAnyWord($norm, $basicMeaningful)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heuristic: does this look like the student is opening up / talking about themselves?
     */
    public function looksLikeSelfDisclosure(string $norm): bool
    {
        $norm = trim($norm);

        // First-person pronouns (EN + a bit of Filipino)
        $hasPronoun = (bool)preg_match(
            '/\b(i|im|i\'m|ive|i\'ve|me|my|mine|myself|ako|ko|akin)\b/u',
            $norm
        );

        if (!$hasPronoun) {
            return false;
        }

        // Words that usually appear when someone is talking about what they're going through
        if ($this->hasAnyWord($norm, [
            'feel', 'feeling', 'felt',
            'struggle', 'struggling', 'struggles',
            'hard', 'harder', 'difficult',
            'tired', 'exhausted', 'drained',
            'lost', 'empty', 'numb',
            'worried', 'scared', 'anxious', 'stressed', 'sad', 'lonely', 'hopeless', 'confused',
            'bother', 'bothering', 'hurt', 'hurting',
            'overthink', 'overthinking',
            'cant', 'cannot',
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
     * Count how many distinct non-mental keywords appear in the text (with typo tolerance).
     */
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

    /**
     * Extract the main topic/theme from user input
     * 
     * @param string $norm Normalized user input
     * @param string|null $previousTopic The topic from the previous turn (if any)
     * @param array $memory Conversation memory for context
     * @return string|null Topic category or null if no clear topic
     */
    public function extractTopic(string $norm, ?string $previousTopic = null, array $memory = []): ?string
    {
        // ===== NEW: Context-aware topic persistence =====
        
        // If user gives a short/vague response and we have a previous topic, continue with it
        $isShortVague = $this->isShortOrVagueResponse($norm);
        
        if ($isShortVague && $previousTopic) {
            // Check if this is a continuation response (not a new topic)
            $hasNewTopicSignal = $this->hasAnyWord($norm, [
                // Academic keywords
                'exam', 'test', 'quiz', 'grade', 'school', 'class', 'homework',
                // Family keywords
                'parent', 'parents', 'mom', 'dad', 'family',
                // Relationship keywords
                'boyfriend', 'girlfriend', 'friend', 'friends',
                // Other specific topics
                'game', 'gaming', 'social media', 'work', 'job'
            ]);
            
            if (!$hasNewTopicSignal) {
                // This is likely a continuation of the previous topic
                return $previousTopic;
            }
        }
        
        // ===== END NEW SECTION =====
        // Academic performance & pressure
        if ($this->hasAnyWord($norm, [
            'exam', 'test', 'quiz', 'midterm', 'finals', 'grade', 'grades', 'failed', 'failing',
            'assignment', 'homework', 'project', 'thesis', 'gpa', 'score', 'marks'
        ])) {
            return 'academic_performance';
        }

        // Family pressure & conflicts
        if ($this->hasAnyWord($norm, [
            'parent', 'parents', 'mom', 'dad', 'mother', 'father', 'mama', 'papa',
            'family', 'siblings', 'brother', 'sister', 'lola', 'lolo'
        ])) {
            // Check if it's about pressure/conflict vs just mentioning them
            if ($this->hasAnyWord($norm, [
                'disappointed', 'disappoint', 'angry', 'mad', 'fight', 'fighting', 'argue',
                'pressure', 'expect', 'expectations', 'demand', 'strict'
            ])) {
                return 'family_pressure';
            }
            return 'family_general';
        }

        // Romantic relationships
        if ($this->hasAnyWord($norm, [
            'boyfriend', 'girlfriend', 'partner', 'crush', 'dating', 'relationship',
            'broke up', 'breakup', 'break up', 'cheated', 'cheating', 'ex'
        ])) {
            return 'romantic_relationship';
        }

        // Friendships
        if ($this->hasAnyWord($norm, [
            'friend', 'friends', 'best friend', 'bff', 'classmate', 'classmates',
            'barkada', 'tropa'
        ])) {
            if ($this->hasAnyWord($norm, [
                'ghosted', 'ghost', 'betrayed', 'betray', 'left out', 'excluded',
                'fake', 'backstab', 'talked behind'
            ])) {
                return 'friendship_conflict';
            }
            return 'friendship_general';
        }

        // Bullying
        if ($this->hasAnyWord($norm, [
            'bully', 'bullying', 'bullied', 'picked on', 'harassed', 'teased', 'mocked'
        ])) {
            return 'bullying';
        }

        // Self-worth & identity
        if ($this->hasAnyWord($norm, [
            'worthless', 'useless', 'failure', 'loser', 'ugly', 'fat', 'stupid',
            'not good enough', 'never good enough', 'insecure', 'confidence'
        ])) {
            return 'self_worth';
        }

        // Future & career anxiety
        if ($this->hasAnyWord($norm, [
            'future', 'career', 'job', 'work', 'college', 'university', 'course',
            'what to do', 'don\'t know what', 'path', 'direction'
        ])) {
            return 'future_anxiety';
        }

        // Social anxiety / isolation
        if ($this->hasAnyWord($norm, [
            'alone', 'lonely', 'isolated', 'no one', 'nobody', 'no friends',
            'don\'t fit in', 'outcast', 'loner',
            // Taglish
            'nag iisa', 'mag isa', 'walang kaibigan', 'walang kasama'
        ])) {
            return 'loneliness';
        }

        // ========== NEW TOPICS BELOW ==========

        // Academic pressure (general stress, not failure)
        if ($this->hasAnyWord($norm, [
            'school stress', 'academic stress', 'school pressure', 'academic pressure',
            'too many requirements', 'workload', 'modules', 'deadlines',
            'can\'t keep up', 'falling behind', 'catching up',
            // Taglish
            'dami ng requirements', 'sobrang dami', 'di ko na kaya', 'grabe ang workload',
            'deadline', 'madaming gawain', 'puyat', 'walang tulog'
        ])) {
            // Check if it's specifically about failure
            if ($this->hasAnyWord($norm, ['failed', 'fail', 'failing', 'flunked'])) {
                return 'academic_performance'; // Redirect to existing category
            }
            return 'academic_pressure';
        }

        // Peer pressure (drugs, alcohol, sex, fitting in)
        if ($this->hasAnyWord($norm, [
            'peer pressure', 'pressured by friends', 'forced to', 'everyone is doing it',
            'fit in', 'belong', 'left out if i don\'t',
            'drink', 'drinking', 'alcohol', 'smoke', 'smoking', 'vape', 'vaping',
            'drugs', 'weed', 'shabu',
            // Taglish
            'pinipilit ako', 'ayaw ko pero', 'lahat ng tao', 'para lang maka fit in',
            'inumin', 'yosi', 'alak'
        ])) {
            return 'peer_pressure';
        }

        // Body image & appearance anxiety
        if ($this->hasAnyWord($norm, [
            'body image', 'appearance', 'how i look', 'looks', 'ugly', 'fat', 'skinny',
            'overweight', 'underweight', 'weight', 'diet', 'lose weight', 'gain weight',
            'acne', 'pimples', 'skin', 'height', 'short', 'tall',
            'not attractive', 'unattractive', 'beautiful', 'handsome', 'pretty',
            // Taglish
            'pangit', 'mataba', 'payat', 'itsura ko', 'hitsura', 'taba',
            'pimple', 'tigyawat', 'tangkad', 'pandak'
        ])) {
            return 'body_image';
        }

        // Sleep issues & exhaustion
        if ($this->hasAnyWord($norm, [
            'can\'t sleep', 'cannot sleep', 'insomnia', 'sleepless', 'no sleep',
            'sleep schedule', 'sleeping problems', 'trouble sleeping',
            'nightmares', 'bad dreams', 'sleep deprivation',
            'always tired', 'so tired', 'exhausted all the time',
            // Taglish
            'di makatulog', 'hindi makatulog', 'walang tulog', 'puyat',
            'bangungot', 'masamang panaginip', 'laging pagod', 'sobrang pagod'
        ])) {
            return 'sleep_issues';
        }

        // Social media stress (comparison, FOMO, cyberbullying)
        if ($this->hasAnyWord($norm, [
            'social media', 'facebook', 'instagram', 'tiktok', 'twitter', 'fb', 'ig',
            'posts', 'posting', 'likes', 'followers', 'comments',
            'compare myself', 'comparing', 'everyone seems happy', 'everyone looks perfect',
            'fomo', 'fear of missing out', 'left out online',
            'online bullying', 'cyberbully', 'hate comments', 'bashers',
            // Taglish
            'lahat sila masaya', 'parang perfect', 'ako lang yata',
            'daming hate', 'ang sakit ng comments', 'bashers', 'mga basher'
        ])) {
            return 'social_media_stress';
        }

        // Financial stress (money, poverty, family finances)
        if ($this->hasAnyWord($norm, [
            'money', 'afford', 'cannot afford', 'can\'t afford', 'expensive', 'poor',
            'poverty', 'broke', 'no money', 'financial', 'finances',
            'tuition', 'allowance', 'baon', 'can\'t pay', 'bills',
            'family is poor', 'we\'re poor', 'we are poor',
            // Taglish
            'pera', 'walang pera', 'mahirap', 'hirap', 'wala kaming pera',
            'di makabili', 'di mabayaran', 'baon', 'kulang', 'walang budget',
            'sobrang hirap', 'mahirap lang kami'
        ])) {
            return 'financial_stress';
        }

        // Identity crisis (sexuality, gender, religion, culture)
        if ($this->hasAnyWord($norm, [
            'who am i', 'identity', 'don\'t know who i am', 'finding myself',
            'sexuality', 'sexual orientation', 'gay', 'lesbian', 'bisexual', 'lgbtq',
            'gender', 'trans', 'transgender', 'confused about gender',
            'religion', 'religious', 'faith', 'beliefs', 'questioning my faith',
            'culture', 'cultural', 'tradition', 'values', 'clash',
            // Taglish
            'kung sino ako', 'di ko alam kung sino ako', 'bakla', 'tomboy',
            'relihiyon', 'kultura', 'tradisyon'
        ])) {
            return 'identity_crisis';
        }

        // Grief & loss (death, breakup recovery, pet loss)
        if ($this->hasAnyWord($norm, [
            'died', 'death', 'passed away', 'dead', 'funeral', 'grieve', 'grieving', 'grief',
            'loss', 'lost someone', 'lost my', 'losing',
            'mourning', 'mourn', 'miss them', 'miss her', 'miss him',
            'grandma died', 'grandpa died', 'lola died', 'lolo died',
            'pet died', 'dog died', 'cat died',
            // Taglish
            'namatay', 'pumanaw', 'patay', 'libing', 'lamay',
            'namimiss ko', 'miss na miss', 'wala na siya'
        ])) {
            return 'grief_loss';
        }

        // Performance anxiety (not academic - sports, talent, public speaking)
        if ($this->hasAnyWord($norm, [
            'performance', 'perform', 'performing', 'stage fright', 'public speaking',
            'presentation', 'recitation', 'nervous to perform', 'scared to perform',
            'competition', 'compete', 'tournament', 'game', 'match',
            'audition', 'tryout', 'talent show',
            // Taglish
            'kinakabahan', 'kaba', 'takot mag present', 'takot mag report',
            'kompetisyon', 'laban', 'paligsahan'
        ])) {
            // Avoid confusion with academic tests
            if (!$this->hasAnyWord($norm, ['exam', 'test', 'quiz', 'grade'])) {
                return 'performance_anxiety';
            }
        }

        // Toxic relationships (abuse, manipulation, not just breakup)
        if ($this->hasAnyWord($norm, [
            'toxic', 'abusive', 'abuse', 'controlling', 'manipulative', 'manipulation',
            'gaslighting', 'gaslight', 'red flags', 'unhealthy relationship',
            'hits me', 'hurts me', 'threatens me', 'scares me',
            'won\'t let me', 'controls me', 'checks my phone',
            // Taglish
            'sinaktan ako', 'sinasaktan', 'kontrolado', 'takot ako sa kanya',
            'pinapalo', 'sinasaktan ako', 'hindi healthy', 'hindi maganda',
            'toxic talaga', 'red flag'
        ])) {
            return 'toxic_relationship';
        }

        return null;
    }

    /**
     * Extract key phrases that should be referenced in responses
     * 
     * @return array Array of important keywords/phrases
     */
    public function extractKeyPhrases(string $norm): array
    {
        $keywords = [];

        // Academic-related keywords
        if (preg_match('/\b(failed|failing|flunked)\s+(my|the|an?)\s+(exam|test|quiz|class|subject|course)/u', $norm, $m)) {
            $keywords[] = 'failed ' . $m[3];
        }

        // Family-related keywords
        if (preg_match('/\b(parent|parents|mom|dad|mother|father)s?\s+(is|are|was|were)\s+(disappointed|angry|mad|upset)/u', $norm, $m)) {
            $keywords[] = $m[1] . ' ' . $m[3];
        }

        // Relationship keywords
        if (preg_match('/\b(broke up|breakup|break up|cheated|ghosted|left me)/u', $norm, $m)) {
            $keywords[] = $m[1];
        }

        // Emotion + reason patterns
        if (preg_match('/\b(sad|depressed|anxious|stressed|overwhelmed)\s+(?:about|because|over)\s+([\w\s]{3,20})/u', $norm, $m)) {
            $keywords[] = $m[2];
        }
        
        // ===== NEW: Enhanced keyword extraction =====
        
        // Capture "don't know what to do" context
        if (preg_match('/don\'?t\s+know\s+(?:what|how)\s+to\s+(?:do|handle|deal)/u', $norm)) {
            $keywords[] = 'needs guidance';
        }
        
        // Capture parent/family actions
        if (preg_match('/\b(parent|parents|mom|dad|mother|father)s?\s+(?:is|are|was|were)?\s*(?:mad|angry|disappointed|upset|yelling|shouting)/u', $norm)) {
            $keywords[] = 'parents upset';
        }
        
        // Capture specific subjects/activities
        if (preg_match('/\b(math|science|english|physics|chemistry|biology|history)\s+(?:exam|test|quiz|class)/u', $norm, $m)) {
            $keywords[] = $m[1] . ' ' . $m[2];
        }

        return array_unique($keywords);
    }

    /**
     * Analyze the sentiment/tone around a topic
     * 
     * @return string 'negative', 'neutral', or 'positive'
     */
    public function analyzeTopicSentiment(string $norm, string $topic): string
    {
        // Check for strongly negative words
        if ($this->hasAnyWord($norm, [
            'hate', 'hated', 'horrible', 'terrible', 'worst', 'awful',
            'disappointing', 'disappointed', 'failed', 'failure', 'lost', 'broke'
        ])) {
            return 'negative';
        }

        // Check for positive words
        if ($this->hasAnyWord($norm, [
            'better', 'good', 'happy', 'glad', 'relieved', 'okay'
        ])) {
            return 'positive';
        }

        return 'neutral';
    }

    /**
     * Check if user input is a short/vague response that likely continues previous topic
     * 
     * Examples: "I don't know what to do", "idk", "what should I do", "yeah", "i guess"
     */
    public function isShortOrVagueResponse(string $norm): bool
    {
        $norm = trim($norm);
        
        // Very short responses (under 30 chars, less than 6 words)
        $tokens = $this->tokens($norm);
        if (mb_strlen($norm) <= 30 && count($tokens) <= 6) {
            // Check for vague/continuation phrases
            $vaguePatterns = [
                // Confusion/uncertainty
                'i don\'t know', 'i dont know', 'idk', 'dunno', 'not sure',
                'unsure', 'confused', 'what should i', 'what do i', 'what can i',
                
                // Contextual questions
                'what should i do', 'what do i do', 'what can i do',
                'how do i', 'how can i', 'what now',
                
                // Affirmative/agreement
                'yeah', 'yes', 'yup', 'uh huh', 'i guess', 'maybe', 'kinda', 'sort of',
                
                // Minimal responses
                'ok', 'okay', 'hmm', 'oh', 'i see',
            ];
            
            foreach ($vaguePatterns as $pattern) {
                if (str_contains($norm, $pattern)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
