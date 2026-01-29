<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Crypt;

/**
 * ChatResponseService - Response generation and tone application for mental health chatbot
 * 
 * Handles empathicprompts, tone application, non-mental topic responses,
 * session title building, and conversation analysis.
 */
class ChatResponseService
{
    public function __construct(
        private ChatNLUService $nluService
    ) {}

    /**
     * Build contextual response that references user input
     * NEW: This replaces the old empathicPrompt with context-aware responses
     */
    public function buildContextualResponse(
        string $first,
        string $norm,
        array $labels,
        ?string $topic,
        array $keywords,
        int $stage
    ): string {
        $first = trim($first) !== '' ? $first : 'there';

        // 1. Get emotion validation
        $validation = $this->getEmotionValidation($labels);
        
        // 2. Get topic-specific acknowledgment
        $topicRef = $this->getTopicReference($topic, $norm, $keywords);
        
        // 3. Get stage-appropriate question
        $question = $this->getContextualQuestion($stage, $topic, $labels);
        
        // 4. Combine into natural response
        return $this->combineContextualResponse($validation, $topicRef, $question, $first);
    }

    /**
     * Build response that uses conversation memory
     * NEW: References what user said in previous turns
     */
    public function buildMemoryAwareResponse(
        string $first,
        string $norm,
        array $labels,
        ?string $topic,
        array $keywords,
        int $stage,
        array $memory
    ): string {
        $first = trim($first) !== '' ? $first : 'there';
        
        // For turn 1 or 2, use regular contextual response (not enough memory yet)
        if (($memory['turn_count'] ?? 0) <= 1) {
            return $this->buildContextualResponse($first, $norm, $labels, $topic, $keywords, $stage);
        }
        
        // For later turns, build response with memory references
        $validation = $this->getEmotionValidation($labels);
        $memoryRef = $this->getMemoryReference($topic, $norm, $memory);
        $question = $this->getMemoryAwareQuestion($stage, $topic, $memory);
        
        return $this->combineMemoryAwareResponse($validation, $memoryRef, $question, $first);
    }

    /**
     * Create reference to previous conversation context
     */
    private function getMemoryReference(?string $topic, string $norm, array $memory): string
    {
        $prevTopics = array_keys($memory['discussed_topics'] ?? []);
        $people = array_keys($memory['mentioned_people'] ?? []);
        $phrases = $memory['key_phrases'] ?? [];
        
        // Check if user is continuing the same topic from before
        if ($topic && in_array($topic, $prevTopics)) {
            // Reference people mentioned earlier
            if (!empty($people)) {
                $person = $people[count($people) - 1]; // Most recent person
                $contexts = $memory['mentioned_people'][$person] ?? [];
                
                if (!empty($contexts)) {
                    $context = $contexts[0]; // First context mentioned
                    return "with your {$person} {$context}";
                }
                return "with your {$person}";
            }
            
            // Reference key phrases if no people
            if (!empty($phrases)) {
                $phrase = $phrases[count($phrases) - 1]; // Most recent phrase
                return "after what happened with {$phrase}";
            }
        }
        
        // Check if switching topics but can link them
        if ($topic && !empty($prevTopics) && !in_array($topic, $prevTopics)) {
            $prevTopic = $prevTopics[count($prevTopics) - 1];
            $link = $this->linkTopics($prevTopic, $topic, $people);
            if ($link) {
                return $link;
            }
        }
        
        return "";
    }

    /**
     * Link current topic to previous topic
     */
    private function linkTopics(string $prevTopic, string $currentTopic, array $people): string
    {
        $links = [
            'academic_performance' => [
                'family_pressure' => "on top of the school stress you mentioned",
                'sleep_issues' => "with the academic pressure you're dealing with",
                'self_worth' => "with how school has been affecting you",
            ],
            'academic_pressure' => [
                'sleep_issues' => "with all the deadlines weighing on you",
                'self_worth' => "with everything piling up at school",
            ],
            'family_pressure' => [
                'academic_performance' => "along with the family situation you shared",
                'sleep_issues' => "with how things are at home",
                'self_worth' => "with what your family has been saying",
            ],
            'romantic_relationship' => [
                'self_worth' => "after what happened in your relationship",
                'sleep_issues' => "with the breakup weighing on you",
                'loneliness' => "since your relationship ended",
            ],
            'friendship_conflict' => [
                'self_worth' => "after being hurt by your friend",
                'loneliness' => "with how your friend treated you",
            ],
            'bullying' => [
                'self_worth' => "with what they've been saying to you",
                'loneliness' => "with how they've been treating you",
                'sleep_issues' => "with the bullying affecting you",
            ],
            'grief_loss' => [
                'sleep_issues' => "with your loss weighing so heavily",
                'loneliness' => "missing them so much",
            ],
        ];
        
        // Add person context if available
        if (!empty($people) && isset($links[$prevTopic][$currentTopic])) {
            $person = $people[count($people) - 1];
            $baseLink = $links[$prevTopic][$currentTopic];
            
            // Try to incorporate person into link
            if ($prevTopic === 'family_pressure' && in_array($person, ['parents', 'mom', 'dad'])) {
                return str_replace('family', $person, $baseLink);
            }
        }
        
        return $links[$prevTopic][$currentTopic] ?? "";
    }

    /**
     * Get question that references conversation memory
     */
    private function getMemoryAwareQuestion(int $stage, ?string $topic, array $memory): string
    {
        $people = array_keys($memory['mentioned_people'] ?? []);
        
        // For Stage 2+, reference people mentioned earlier
        if ($stage >= 2 && !empty($people)) {
            $person = $people[count($people) - 1];
            
            if ($topic === 'academic_performance') {
                return "How are your {$person} responding to all of this?";
            }
            if ($topic === 'family_pressure') {
                return "What do you wish your {$person} understood?";
            }
            if ($topic === 'romantic_relationship' && in_array($person, ['girlfriend', 'boyfriend'])) {
                return "What hurts most when you think about your {$person}?";
            }
        }
        
        // Fallback to regular contextual question
        return $this->getContextualQuestion($stage, $topic, []);
    }

    /**
     * Combine validation, memory reference, and question
     */
    private function combineMemoryAwareResponse(
        string $validation,
        string $memoryRef,
        string $question,
        string $first
    ): string {
        // Pattern: [Validation] [Memory Reference]. [Question]
        
        if ($memoryRef) {
            // Include memory reference
            if ($validation !== "I hear you") {
                return "{$validation} {$memoryRef}. {$question}";
            }
            return "I hear you, {$first} — {$memoryRef}. {$question}";
        }
        
        // No memory reference, use validation + question
        if ($validation !== "I hear you") {
            return "{$validation}. {$question}";
        }
        
        return "I hear you, {$first}. {$question}";
    }

    /**
     * Get emotion validation phrase based on detected emotions
     */
    private function getEmotionValidation(array $labels): string
    {
        if (empty($labels)) {
            return "I hear you";
        }

        $primary = strtolower($labels[0]);
        
        $validations = [
            'sad'          => "That sounds really painful",
            'anxious'      => "That must feel so overwhelming",
            'stressed'     => "That's a lot to carry",
            'tired'        => "You must be absolutely exhausted",
            'angry'        => "That frustration makes complete sense",
            'lonely'       => "Feeling alone in this must be so hard",
            'hopeless'     => "When everything feels hopeless, it can be scary",
            'not_ok'       => "It makes sense that you're not feeling okay",
            'disappointed' => "That disappointment must really sting",
            'confused'     => "It's okay not to have it all figured out",
            'overwhelmed'  => "Everything piling up like this must feel like too much",
            'guilty'       => "Carrying that guilt must be heavy",
            'ashamed'      => "Shame is such a painful thing to feel",
        ];

        return $validations[$primary] ?? "I hear you";
    }

    /**
     * Get topic-specific reference that acknowledges what user said
     */
    private function getTopicReference(?string $topic, string $norm, array $keywords): string
    {
        if (!$topic) {
            return "What you're going through";
        }

        switch ($topic) {
            case 'academic_performance':
                if ($this->nluService->hasAnyWord($norm, ['failed', 'fail', 'failing'])) {
                    return "Failing can feel devastating, especially when you've tried your best";
                }
                if ($this->nluService->hasAnyWord($norm, ['grade', 'grades', 'low'])) {
                    return "Seeing grades you didn't expect can really shake your confidence";
                }
                return "Academic pressure can be really intense";

            case 'family_pressure':
                if ($this->nluService->hasAnyWord($norm, ['disappointed', 'disappoint'])) {
                    return "Carrying your family's disappointment on top of your own feelings";
                }
                if ($this->nluService->hasAnyWord($norm, ['fight', 'fighting', 'argue'])) {
                    return "When family conflicts happen, it can make home feel unsafe";
                }
                return "Family expectations can weigh so heavily";

            case 'romantic_relationship':
                if ($this->nluService->hasAnyWord($norm, ['broke up', 'breakup', 'break up'])) {
                    return "Breakups can leave you feeling lost and hurt";
                }
                if ($this->nluService->hasAnyWord($norm, ['cheated', 'cheat', 'cheating'])) {
                    return "Being cheated on cuts so deep and breaks trust";
                }
                return "Relationship struggles can be really painful";

            case 'friendship_conflict':
                if ($this->nluService->hasAnyWord($norm, ['ghosted', 'ghost'])) {
                    return "Being ghosted by someone you trusted leaves you with so many questions";
                }
                if ($this->nluService->hasAnyWord($norm, ['betrayed', 'betray'])) {
                    return "Feeling betrayed by a friend cuts especially deep";
                }
                return "When friendships hurt, it can feel really isolating";

            case 'bullying':
                return "Being bullied is never okay, and it's not your fault";

            case 'self_worth':
                return "Feeling not good enough can color everything";

            case 'loneliness':
                return "Loneliness can make everything feel heavier";

            case 'future_anxiety':
                return "Uncertainty about the future can be really overwhelming";

            // ===== NEW TOPICS BELOW =====

            case 'academic_pressure':
                if ($this->nluService->hasAnyWord($norm, ['workload', 'requirements', 'modules'])) {
                    return "When there's too much piling up at once, it can feel impossible to breathe";
                }
                if ($this->nluService->hasAnyWord($norm, ['deadline', 'deadlines', 'catching up'])) {
                    return "Racing against deadlines can make everything feel so heavy";
                }
                return "Academic pressure can feel relentless when it never stops";

            case 'peer_pressure':
                if ($this->nluService->hasAnyWord($norm, ['forced', 'pressured', 'everyone is doing'])) {
                    return "Being pressured to do something you're uncomfortable with is never okay";
                }
                if ($this->nluService->hasAnyWord($norm, ['fit in', 'belong', 'left out'])) {
                    return "Feeling like you have to change yourself to fit in can be exhausting";
                }
                return "Peer pressure can make it so hard to stand your ground";

            case 'body_image':
                if ($this->nluService->hasAnyWord($norm, ['ugly', 'fat', 'skinny', 'weight'])) {
                    return "How we see ourselves can be so much harsher than reality";
                }
                if ($this->nluService->hasAnyWord($norm, ['acne', 'pimples', 'skin'])) {
                    return "Skin struggles can really affect how confident you feel";
                }
                return "Worrying about appearance can take up so much mental space";

            case 'sleep_issues':
                if ($this->nluService->hasAnyWord($norm, ['insomnia', 'can\'t sleep', 'cannot sleep'])) {
                    return "Not being able to sleep when you need to rest must be so frustrating";
                }
                if ($this->nluService->hasAnyWord($norm, ['nightmare', 'nightmares', 'bad dreams'])) {
                    return "Nightmares can make sleep feel more stressful than restful";
                }
                return "When sleep becomes a struggle, everything else feels harder";

            case 'social_media_stress':
                if ($this->nluService->hasAnyWord($norm, ['compare', 'comparing', 'everyone seems'])) {
                    return "Comparing your behind-the-scenes to everyone's highlight reel is painful";
                }
                if ($this->nluService->hasAnyWord($norm, ['hate comments', 'bashers', 'cyberbully'])) {
                    return "Online hate can cut deep, even when it's from strangers";
                }
                if ($this->nluService->hasAnyWord($norm, ['likes', 'followers', 'fomo'])) {
                    return "When validation feels tied to numbers, it can mess with your self-worth";
                }
                return "Social media can twist how we see ourselves and others";

            case 'financial_stress':
                if ($this->nluService->hasAnyWord($norm, ['poor', 'poverty', 'mahirap'])) {
                    return "Financial struggles add a weight that people don't always see";
                }
                if ($this->nluService->hasAnyWord($norm, ['cannot afford', 'can\'t afford', 'expensive'])) {
                    return "Not being able to afford things can be isolating and unfair";
                }
                return "Money worries can make you feel powerless and stuck";

            case 'identity_crisis':
                if ($this->nluService->hasAnyWord($norm, ['sexuality', 'gay', 'lesbian', 'lgbtq'])) {
                    return "Figuring out who you are, especially in a world that doesn't always accept you, takes courage";
                }
                if ($this->nluService->hasAnyWord($norm, ['religion', 'faith', 'beliefs'])) {
                    return "Questioning your beliefs when they've been central to your identity can feel disorienting";
                }
                return "Not knowing who you are yet is okay — identity unfolds over time";

            case 'grief_loss':
                if ($this->nluService->hasAnyWord($norm, ['died', 'death', 'passed away'])) {
                    return "Losing someone leaves a hole that never quite fills the same way again";
                }
                if ($this->nluService->hasAnyWord($norm, ['miss', 'missing'])) {
                    return "Missing someone intensely can show up in unexpected moments";
                }
                return "Grief doesn't follow a timeline — it's okay to still be hurting";

            case 'performance_anxiety':
                if ($this->nluService->hasAnyWord($norm, ['stage fright', 'nervous', 'scared'])) {
                    return "Performance anxiety can make your body feel like it's working against you";
                }
                if ($this->nluService->hasAnyWord($norm, ['competition', 'audition', 'tryout'])) {
                    return "The pressure to perform well when it matters can be paralyzing";
                }
                return "Worrying about messing up can overshadow your actual abilities";

            case 'toxic_relationship':
                if ($this->nluService->hasAnyWord($norm, ['controlling', 'controls', 'manipulative'])) {
                    return "Being controlled by someone who claims to care about you is confusing and wrong";
                }
                if ($this->nluService->hasAnyWord($norm, ['abuse', 'abusive', 'hits me', 'hurts me'])) {
                    return "What you're experiencing is abuse, and it's never your fault";
                }
                return "Toxic relationships can make you question your own reality";

            default:
                return "What you're dealing with";
        }
    }

    /**
     * Get contextual question based on stage and topic
     */
    private function getContextualQuestion(int $stage, ?string $topic, array $labels): string
    {
        // Stage 1: Opening/exploration
        if ($stage <= 1) {
            if ($topic === 'academic_performance') {
                return "What's been the hardest part of dealing with this?";
            }
            if ($topic === 'family_pressure') {
                return "How has your family been responding to you?";
            }
            if ($topic === 'romantic_relationship' || $topic === 'friendship_conflict') {
                return "How have you been processing this?";
            }
            if ($topic === 'bullying') {
                return "How long has this been happening?";
            }
            // New topics - Stage 1
            if ($topic === 'academic_pressure') {
                return "What's making you feel the most overwhelmed right now?";
            }
            if ($topic === 'peer_pressure') {
                return "What are you feeling pressured to do?";
            }
            if ($topic === 'body_image') {
                return "When did you first start feeling this way about your appearance?";
            }
            if ($topic === 'sleep_issues') {
                return "How long have you been struggling with sleep?";
            }
            if ($topic === 'social_media_stress') {
                return "How is this affecting you day-to-day?";
            }
            if ($topic === 'financial_stress') {
                return "How is this weighing on you?";
            }
            if ($topic === 'identity_crisis') {
                return "What part of this feels the most confusing for you?";
            }
            if ($topic === 'grief_loss') {
                return "How have you been coping with this loss?";
            }
            if ($topic === 'performance_anxiety') {
                return "When do you notice the anxiety the most?";
            }
            if ($topic === 'toxic_relationship') {
                return "How long has this been going on?";
            }
            return "What part of this feels heaviest for you right now?";
        }
        
        // Stage 2: Deeper exploration
        if ($stage === 2) {
            if ($topic === 'academic_performance') {
                return "How are you feeling about yourself because of this?";
            }
            if ($topic === 'family_pressure') {
                return "What do you wish your family understood about what you're going through?";
            }
            if ($topic === 'romantic_relationship' || $topic === 'friendship_conflict') {
                return "What hurts the most when you think about it?";
            }
            // New topics - Stage 2
            if ($topic === 'academic_pressure') {
                return "How is this affecting your well-being outside of school?";
            }
            if ($topic === 'peer_pressure') {
                return "Have you been able to talk to anyone about this?";
            }
            if ($topic === 'body_image') {
                return "How does this affect how you show up for the day?";
            }
            if ($topic === 'sleep_issues') {
                return "What goes through your mind when you can't sleep?";
            }
            if ($topic === 'social_media_stress') {
                return "Have you thought about how you want to change your relationship with social media?";
            }
            if ($topic === 'financial_stress') {
                return "How does this affect your daily life and choices?";
            }
            if ($topic === 'identity_crisis') {
                return "What would help you feel more at peace with exploring who you are?";
            }
            if ($topic === 'grief_loss') {
                return "What do you miss most?";
            }
            if ($topic === 'performance_anxiety') {
                return "What thoughts run through your mind before you have to perform?";
            }
            if ($topic === 'toxic_relationship') {
                return "What makes it hard to leave or set boundaries?";
            }
            return "When did you first start feeling this way?";
        }
        
        // Stage 3+: Support and next steps
        if ($topic === 'academic_performance') {
            return "What kind of support do you feel you need right now?";
        }
        if ($topic === 'family_pressure') {
            return "What would make things feel safer for you at home?";
        }
        return "What do you feel you need most right now?";
    }

    /**
     * Combine validation, topic reference, and question into natural response
     */
    private function combineContextualResponse(
        string $validation,
        string $topicRef,
        string $question,
        string $first
    ): string {
        // Pattern: [Validation]. [Topic acknowledgment]. [Question]
        $parts = [];
        
        if ($validation !== "I hear you") {
            $parts[] = $validation;
        }
        
        if ($topicRef && $topicRef !== "What you're going through" && $topicRef !== "What you're dealing with") {
            $parts[] = strtolower($topicRef);
        }
        
        // Combine first parts with proper punctuation
        $opening = '';
        if (count($parts) === 2) {
            $opening = $parts[0] . ' — ' . $parts[1] . '.';
        } elseif (count($parts) === 1) {
            $opening = $parts[0] . '.';
        }
        
        // Add question
        if ($opening) {
            return $opening . ' ' . $question;
        }
        
        return "I hear you, {$first}. " . $question;
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use buildContextualResponse instead
     */
    public function empathicPrompt(string $first, array $labels, int $stage): string
    {
        // Fallback to contextual response with no topic
        return $this->buildContextualResponse($first, '', $labels, null, [], $stage);
    }

    /**
     * Apply emotional tone tothe reply based on detected emotions and risk
     */
    public function applyEmotionTone(string $replyText, string $norm, array $labels, string $risk): string
    {
        $base = trim($replyText);
        if ($base === '') {
            return $replyText;
        }

        $normLower = mb_strtolower($norm);

        // Don't touch explicit crisis scripts too much
        if ($risk === 'high') {
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

        // Lighter feelings / positive moments
        if ($risk === 'low') {
            if (preg_match('/\b(lol|lmao|haha+|hehe+|joke|funny)\b/u', $normLower)) {
                if (!preg_match('/[\x{1F600}-\x{1F64F}]/u', $base)) {
                    $base .= " 😄";
                }
                return $base;
            }
        }

        // Gratitude / relief → warm, supportive smile
        if ($risk !== 'high') {
            if (preg_match('/\b(thanks?|thank you|salamat|appreciate it)\b/u', $normLower)) {
                if (!preg_match('/[\x{1F600}-\x{1F64F}]/u', $base)) {
                    $base .= " Of course! I'm really glad I could be here for you. That's what I'm here for — you're never bothering me. 🙂";
                } else {
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

        return $base;
    }

    /**
     * Generate fallback support line when Rasa fails or returns empty
     */
    public function fallbackSupportLine(): string
    {
        $options = [
            "It's okay to feel that way, {USER_FIRST}. I'm here to listen. If you're comfortable, you can tell me a bit more about what's going on.",
            "Thank you for reaching out, {USER_FIRST}. Even if it's hard to put into words, I'm here with you. What part feels heaviest right now?",
            "I'm glad you messaged me, {USER_FIRST}. You don't have to go through this alone. What would you like to start with?",
            "You're not bothering me at all, {USER_FIRST}. Your feelings matter here. What's been staying in your mind the most these days?",
            "I'm here for you, {USER_FIRST}. Sometimes just talking about it slowly can help. What's one thing you wish someone asked you about today?",
            "Even if everything feels confusing, {USER_FIRST}, you've already taken a brave step by reaching out. What's been the hardest part to carry alone?",
        ];

        return $options[array_rand($options)];
    }

    /**
     * Generate non-mental topic boundary response
     */
    public function nonMentalReply(int $sessionId, string $first, string $norm, array $flags): string
    {
        $category   = $this->classifyNonMentalCategory($norm);
        $isQuestion = $flags['is_question'] ?? false;

        $first = trim($first) !== '' ? $first : 'there';

        $responses = [
            'games' => [
                'question' => [
                    "Gaming can really matter to us, {$first}. I'm mainly focused on your mental health, so I might not guide you on rank or mechanics — but if pressure from games, teammates, or losing is affecting how you feel, we can talk about that.",
                    "It sounds like you care a lot about gaming, {$first}. Lumi is more about what's going on inside you than builds or tactics. If rank, performance, or comments from others are stressing you out, tell me how it's been for you.",
                ],
                'statement' => [
                    "Playing games can be a real escape, {$first}. I'm not here to break down strategies, but if you're using games to cope or if things in-game are stressing you out, we can unpack how that feels.",
                    "Games can be fun—and also frustrating, {$first}. Lumi's role is to support your mental health. If losing streaks, rank, or people you play with are affecting your confidence or mood, I'm here for that part.",
                ],
            ],
            // Add more categories with similar structure...
        ];

        $key = 'nonmental_' . $category . '_' . ($isQuestion ? 'q' : 's');
        $type = $isQuestion ? 'question' : 'statement';
        
        $variants = $responses[$category][$type] ?? [
            "Thanks for sharing that, {$first}. Lumi stays focused on your mental and emotional health, so I might not handle all of the details here. But if this situation is stressing you or affecting how you feel, you can tell me about it.",
        ];

        return $this->pickVariantForSession($sessionId, $key, $variants);
    }

    /**
     * Build a ChatGPT-style session title from the latest message
     */
    public function buildSessionTitle(
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

        // If we already have a decent human title and convo is long, keep it
        if (
            $currentTitle
            && $msgCount > 6
            && !str_starts_with($currentTitle, 'Starting conversation')
        ) {
            // But if risk just escalated to HIGH, we allow renaming to a crisis title
            if ($risk !== 'high' || str_starts_with($currentTitle, 'Crisis')) {
                return $currentTitle;
            }
        }

        // Primary emotion label → mood phrase
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

        // Special cases: crisis / appointment / capabilities
        if ($risk === 'high') {
            $crisisTitle = 'Crisis thoughts';
            if (in_array('die_direct', $hits, true) || $this->nluService->hasAnyWord($norm, ['kill', 'suicide', 'unalive'])) {
                $crisisTitle = 'Crisis thoughts about self-harm';
            } elseif ($this->nluService->hasAnyWord($norm, ['disappear', 'exist', 'worthless', 'burden'])) {
                $crisisTitle = 'Crisis thoughts about wanting to disappear';
            }
            $title = $crisisTitle;
        } elseif ($flags['wants_appointment'] ?? false) {
            $title = 'Thinking about seeing a counselor';
        } elseif (($flags['asks_capabilities'] ?? false) && $msgCount <= 2) {
            $title = 'Getting to know LumiCHAT';
        } elseif ($primary && isset($moodPhrases[$primary])) {
            $title = $moodPhrases[$primary];
        } elseif ($flags['is_question'] ?? false) {
            $title = 'Question';
        } else {
            $title = 'Conversation';
        }

        // Topic / domain detection (what is this *about*?)
        $topicSuffix = '';

        if ($this->nluService->hasAnyWord($norm, [
            'school', 'class', 'classes', 'subject', 'assignment', 'homework', 'module',
            'quiz', 'exam', 'test', 'projects', 'grades', 'teacher', 'professor', 'deadline',
        ])) {
            $topicSuffix = 'about school';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'friend', 'friends', 'classmate', 'crush', 'partner', 'boyfriend', 'girlfriend',
            'relationship', 'breakup', 'trust', 'betray',
        ])) {
            $topicSuffix = 'about relationships';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'family', 'parents', 'mother', 'father', 'mom', 'dad', 'siblings', 'brother', 'sister',
        ])) {
            $topicSuffix = 'about family';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'bully', 'bullying', 'conflict', 'fighting', 'argue', 'toxic',
        ])) {
            $topicSuffix = 'about conflict or bullying';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'myself', 'self', 'identity', 'worth', 'worthless', 'failure', 'ugly', 'insecure',
        ])) {
            $topicSuffix = 'about self-worth';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'future', 'career', 'job', 'work', 'choice', 'decisions', 'plan', 'dream',
        ])) {
            $topicSuffix = 'about the future';
        } elseif ($this->nluService->hasAnyWord($norm, [
            'health', 'sick', 'body', 'weight', 'energy', 'fatigue', 'insomnia',
        ])) {
            $topicSuffix = 'about health';
        }

        // Combine mood + topic
        if ($topicSuffix !== '') {
            $title = trim($title . ' ' . $topicSuffix);
        } elseif ($flags['is_question'] ?? false) {
            $snippet = \Illuminate\Support\Str::limit($analysisText, 40, '…');
            $title   = 'Question: ' . $snippet;
        }

        // Fallback: if still too generic, just use a clean snippet
        if ($title === '' || mb_strlen($title) < 4 || $title === 'Conversation') {
            $title = \Illuminate\Support\Str::limit($analysisText, 40, '…');
        }

        // Make sure it's not insanely long
        $title = \Illuminate\Support\Str::limit($title, 60, '…');

        return ucfirst($title);
    }

    /**
     * Analyze conversation trajectory
     */
    public function analyzeTrajectory(
        ChatSession $session,
        string $msgRisk,
        array $sessionEmotionCounts,
        int $sessionUserMsgCount
    ): string {
        $prevRisk = $session->risk_level ?: 'low';

        if ($sessionUserMsgCount <= 1) {
            return 'single_message';
        }

        if ($msgRisk === 'high' && $prevRisk !== 'high') {
            return 'spike_to_high';
        }

        if ($msgRisk === 'high' && $prevRisk === 'high') {
            return 'persistent_high';
        }

        if ($msgRisk === 'moderate' && $prevRisk === 'low') {
            return 'rising_risk';
        }

        $totalEmo = array_sum(array_map('intval', $sessionEmotionCounts));
        if ($msgRisk === 'low' && $prevRisk === 'low') {
            if ($totalEmo >= 8 && $sessionUserMsgCount >= 6) {
                return 'persistent_low_emotional';
            }
            if ($totalEmo >= 4 && $sessionUserMsgCount >= 3) {
                return 'building_emotional_load';
            }
        }

        if ($msgRisk === 'moderate' && $prevRisk === 'moderate') {
            if ($sessionUserMsgCount >= 6) {
                return 'persistent_moderate';
            }
            return 'stable_moderate';
        }

        return 'stable_low';
    }

    /**
     * Detect conversation stage
     */
    public function detectConversationStage(
        ChatSession $session,
        string $msgRisk,
        array $flags,
        int $sessionUserMsgCount,
        bool $askedForAppt,
        bool $inVentWindow
    ): string {
        if (($flags['done'] ?? false) || ($flags['goodbye'] ?? false)) {
            return 'closing';
        }

        if ($msgRisk === 'high' || ($session->risk_level === 'high')) {
            return 'crisis';
        }

        if ($askedForAppt) {
            return 'appointment_flow';
        }

        if ($flags['wants_coping'] ?? false) {
            return 'coping';
        }

        if ($sessionUserMsgCount <= 1) {
            return 'opening';
        }

        if ($inVentWindow) {
            return 'venting';
        }

        if ($sessionUserMsgCount <= 4) {
            return 'exploration';
        }

        if ($sessionUserMsgCount <= 10) {
            return 'ongoing_support';
        }

        return 'long_running';
    }

    /**
     * Get last bot intent from session
     */
    public function lastBotIntent(int $sessionId): string
    {
        $lastBot = Chat::where('chat_session_id', $sessionId)
            ->where('sender', 'bot')
            ->latest('sent_at')
            ->first();
            
        if (!$lastBot) return '';
        
        try {
            $txt = Crypt::decryptString($lastBot->message);
        } catch (\Throwable $e) {
            $txt = (string)$lastBot->message;
        }
        
        $t = mb_strtolower($txt);
        if (str_contains($t, 'show tips') || str_contains($t, 'want them now')) return 'offer_coping';
        if (str_contains($t, 'book an appointment') || str_contains($t, 'book counselor') || str_contains($t, 'schedule')) return 'offer_appointment';
        
        return '';
    }

    /**
     * Pick a variant from a list, rotating per session+key
     */
    private function pickVariantForSession(int $sessionId, string $key, array $variants): string
    {
        if (empty($variants)) {
            return '';
        }

        $sessionKey = 'variant_' . $sessionId . '_' . $key;
        $idx        = (int)session($sessionKey, 0);

        $reply = $variants[$idx % count($variants)];

        session([$sessionKey => $idx + 1]);
        return $reply;
    }

    /**
     * Classify non-mental category (simplified for service)
     */
    private function classifyNonMentalCategory(string $norm): string
    {
        if ($this->nluService->hasAnyWord($norm, ['game', 'games', 'gaming', 'valorant', 'ml', 'dota'])) {
            return 'games';
        }

        if ($this->nluService->hasAnyWord($norm, ['movie', 'series', 'anime', 'music', 'netflix'])) {
            return 'entertainment';
        }

        if ($this->nluService->hasAnyWord($norm, ['food', 'restaurant', 'cafe', 'cooking', 'recipe'])) {
            return 'food';
        }

        return 'other';
    }

    /**
     * Build rich context payload for Rasa API
     * Passes conversation context so Rasa can give smarter responses
     */
    public function buildRasaContext(
        ?string $topic,
        array $labels,
        string $risk,
        array $keywords,
        array $flags,
        array $memory,
        string $first,
        int $stage,
        int $sessionId
    ): array {
        return [
            'lumichat' => [
                // Current turn analysis
                'topic' => $topic,
                'emotions' => array_slice($labels, 0, 3), // Top 3 emotions
                'risk_level' => $risk,
                'keywords' => array_slice($keywords, 0, 5), // Top 5 keywords
                'intents' => array_keys(array_filter($flags)),
                
                // Conversation memory
                'discussed_topics' => array_keys($memory['discussed_topics'] ?? []),
                'mentioned_people' => array_keys($memory['mentioned_people'] ?? []),
                'key_phrases' => $memory['key_phrases'] ?? [],
                'turn_count' => $memory['turn_count'] ?? 0,
                
                // User & session context
                'user_first_name' => $first,
                'conversation_stage' => $stage,
                'session_id' => $sessionId,
                
                // Critical flags
                'in_crisis' => $risk === 'high',
                'wants_appointment' => $flags['wants_appointment'] ?? false,
                'wants_coping' => $flags['wants_coping'] ?? false,
            ]
        ];
    }
}
