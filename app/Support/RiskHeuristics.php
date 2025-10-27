<?php
namespace App\Support;

class RiskHeuristics
{
    /** Canonical targets for fuzzy matching */
    private static array $HR_WORDS = [
        'suicide','unalive','die','dead','kill myself','end my life','end it',
        'jump','overdose','poison','hang myself','cut myself','self harm','self hurt',
        // local
        'mamatay','maghikog','wala na koy paglaum','mawala','taposon na nako tanan',
        // shortforms/slang
        'kys','kms'
    ];

    /** Common intent words to co-occur with */
    private static array $INTENT = [
        'wanna','want','plan','planning','intend','need','will','gonna','going to',
        'thinking','feel like','i should','i might','really want','wish',
        // local
        'gusto','buot','tingali','murag'
    ];

    /** Mini dictionary for normalizing frequent misspellings */
    private static array $MISSPELL = [
        'sui cide' => 'suicide', 'suicde' => 'suicide', 'suiside' => 'suicide', 'suiiside' => 'suicide',
        'unal ive' => 'unalive', 'unal1ve' => 'unalive',
        'kil myself' => 'kill myself', 'kll myself' => 'kill myself', 'k1ll myself' => 'kill myself',
        'end my lyf' => 'end my life', 'end my lif' => 'end my life',
        'hang my self' => 'hang myself', 'cutt myself' => 'cut myself',
        'dy' => 'die', 'diie' => 'die', 'dee' => 'die',
        // local
        'mag hikog' => 'maghikog', 'mam atay' => 'mamatay', 'tapuson' => 'taposon',
    ];

    /** Normalize a free-text message for robust pattern matching. */
    public static function normalizeMsg(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');

        // Strip zero-width/control
        $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\p{Cf}\p{Cc}]+/u', '', $s) ?? $s;

        // Remove accents
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_D) ?? $s;
            $s = preg_replace('/\p{Mn}+/u', '', $s) ?? $s;
        }

        // Leetspeak
        $s = strtr($s, [
            '0'=>'o','1'=>'i','!'=>'i','|'=>'i','3'=>'e','4'=>'a','@'=>'a','5'=>'s','$'=>'s','7'=>'t','8'=>'b','9'=>'g'
        ]);

        // Remove junk between letters: d.i.e -> die
        $s = preg_replace('/(?<=\p{L})[^\p{L}\s]+(?=\p{L})/u', '', $s) ?? $s;

        // Collapse non-alnum
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;

        // Collapse 3+ repeats to double: diiieeee -> diie
        $s = preg_replace('/([a-z])\1{2,}/u', '$1$1', $s) ?? $s;

        // Specific fix-ups
        $s = preg_replace('/\bdiee+\b/u', 'die', $s) ?? $s;

        // Apply common misspellings (space-padded to avoid partials)
        $pad = ' '.$s.' ';
        foreach (self::$MISSPELL as $bad => $good) {
            $pad = str_replace(' '.$bad.' ', ' '.$good.' ', $pad);
        }
        $s = trim(preg_replace('/\s+/u', ' ', $pad) ?? $pad);

        return $s;
    }

    /** High-risk patterns (post-normalization). */
    public static function patterns(): array
    {
        return [
            // direct / intent
            '\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b',
            '\bi\s*(?:wanna|want(?:\s*to)?|plan|planning|intend|need|will|gonna)\s*(?:to\s*)?(?:die|kill myself|end (?:it|my life)|commit suicide|unalive|disappear|be gone)\b',
            '\b(?:kill myself|commit suicide|end it all|no reason to live|life is pointless)\b',
            '\bi\s*(?:wish|want)\s*(?:i\s*)?(?:were|was)\s*dead\b',
            '\bi\s*(?:can\'?t|cannot)\s*go on\b',
            '\b(?:jump off|overdose|poison myself|hang myself)\b',
            '\b(?:self[- ]?harm|cut(?:ting)? myself|self[- ]?hurt)\b',

            // local language cues
            '\bgusto\s*na\s*ko\s*mamatay\b',
            '\bmaghikog\b',
            '\bwala\s*na\s*ko(?:y)?\s*paglaum\b',
            '\bgusto\s*ko\s*mawala\b',
            '\btapuson\s*na\s*nako\s*tanan\b',

            // shortforms/slang
            '\bkys\b', '\bkms\b', '\bunalive\b',
        ];
    }

    /** Quick Levenshtein check against a list (distance <= 1 or 2 for short tokens). */
    private static function fuzzyHit(string $text, array $needles): bool
    {
        // Tokenize once
        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($needles as $needle) {
            $parts = explode(' ', $needle);
            if (count($parts) === 1) {
                // single-word
                foreach ($tokens as $tok) {
                    $d = levenshtein($tok, $needle);
                    if ($d <= (mb_strlen($needle) <= 4 ? 1 : 2)) return true;
                }
            } else {
                // multi-word: compare sliding windows
                for ($i = 0; $i <= count($tokens) - count($parts); $i++) {
                    $win = array_slice($tokens, $i, count($parts));
                    $sum = 0; $ok = true;
                    foreach ($win as $k => $tok) {
                        $d = levenshtein($tok, $parts[$k]);
                        $sum += $d;
                        if ($d > 2) { $ok = false; break; }
                    }
                    if ($ok && $sum <= 3) return true;
                }
            }
        }
        return false;
    }

    /** Co-occurrence heuristic (intent + act) after normalization, with fuzzy. */
    private static function cooccurLikelyHighRisk(string $t): bool
    {
        $hasActFuzzy   = self::fuzzyHit($t, self::$HR_WORDS);
        $hasIntentFuzzy= self::fuzzyHit($t, self::$INTENT);
        return $hasActFuzzy && $hasIntentFuzzy;
    }

    /** Main boolean check used across controllers. */
    public static function containsHighRisk(string $raw): bool
    {
        $t = self::normalizeMsg($raw);

        // Regex pass
        foreach (self::patterns() as $p) {
            if (preg_match('/'.$p.'/iu', $t)) return true;
        }

        // Fuzzy co-occurrence pass (catches “i wna kil my slef”, “gsto ko mmtay”)
        if (self::cooccurLikelyHighRisk($t)) return true;

        // Bare "i die" but not negated
        $neg = (bool) preg_match('/\b(?:don\'?t|do\s+not)\s+i\s+[^.?!]*\bdie\b/iu', $t);
        if (!$neg && preg_match('/\bi\s*(?:just\s*)?(?:(?:will|would|could|can|might|gonna|going\s+to)\s*)?die\b/iu', $t)) {
            return true;
        }

        return false;
    }
}
