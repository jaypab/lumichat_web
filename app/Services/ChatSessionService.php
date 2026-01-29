<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\ChatSession;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * ChatSessionService - Session management for mental health chatbot
 * 
 * Handles session creation, emotion tracking, history management, and session locking.
 */
class ChatSessionService
{
    /**
     * Normalize any stored shape (null | list | map) into a simple label=>count map
     */
    public function emotionsAsCounts(null|array|string $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) return [];

        // If already a map of counts, normalize to int
        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            $out = [];
            foreach ($value as $k => $v) {
                if (!is_string($k)) continue;
                $out[strtolower($k)] = max(0, (int)$v);
            }
            return $out;
        }

        // If it was a list (["sad","anxious"]), turn into counts
        $out = [];
        foreach ($value as $label) {
            if (!is_string($label) || $label === '') continue;
            $k = strtolower($label);
            $out[$k] = ($out[$k] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * Increment counts for the newly detected labels
     */
    public function incrementEmotionCounts(array $counts, array $labels): array
    {
        // If someone accidentally passes the full detectEmotions() struct, unwrap it
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

    /**
     * Check if session is locked
     */
    public function isLocked(int $sessionId): bool
    {
        return (bool)session('session_locked_' . $sessionId, false);
    }

    /**
     * Lock a session
     */
    public function lockSession(int $sessionId, string $reason = 'declined_referral'): void
    {
        session(['session_locked_' . $sessionId => true]);
        session(['session_lock_reason_' . $sessionId => $reason]);
    }

    /**
     * Get lock reason
     */
    public function lockReason(int $sessionId): string
    {
        return (string)session('session_lock_reason_' . $sessionId, '');
    }

    /**
     * Log activity
     */
    public function logActivity(string $event, string $description, int $sessionId, ?array $meta = null): void
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

    /**
     * Check if user declined referral
     */
    public function declinedReferral(string $raw): bool
    {
        $t = trim(mb_strtolower($raw));

        // Rasa-style payload
        if (preg_match('~^/deny\s*(\{.*\})?~i', $t, $m)) {
            if (!empty($m[1])) {
                try {
                    $j = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
                    if (isset($j['confirm_topic']) && strtolower($j['confirm_topic']) === 'referral') {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            return true;
        }

        // Fallback: common phrasing
        if (preg_match('/\b(not now|no thanks|maybe later|pass for now)\b/u', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Check if we confirmed after offer (for appointment)
     */
    public function confirmedAfterOffer(string $text, int $sessionId): bool
    {
        $t = mb_strtolower($text);
        if (!preg_match('/\b(yes|yeah|yup|sure|ok(?:ay)?|go|go ahead|proceed|please|yes please)\b/u', $t)) {
            return false;
        }
        
        $lastBot = Chat::where('chat_session_id', $sessionId)
            ->where('sender', 'bot')
            ->latest('sent_at')
            ->first();
            
        if (!$lastBot) return false;
        
        try {
            $last = \Illuminate\Support\Facades\Crypt::decryptString($lastBot->message);
        } catch (\Throwable $e) {
            $last = (string)$lastBot->message;
        }
        
        return (bool)preg_match('/\b(counsel(?:or|ling)|appointment|schedule|book|connect)\b/i', $last);
    }

    /**
     * Should we bypass venting based on flags and risk?
     */
    public function shouldBypassVenting(string $text, string $risk, array $flags, int $lenLimit = 400): bool
    {
        if ($risk === 'high') return true;
        if ($flags['wants_appointment'] ?? false) return true;
        if (($flags['is_question'] ?? false) && mb_strlen($text) <= $lenLimit) return true;
        return false;
    }

    /**
     * Get preferred name for user
     */
    public function preferredName(): string
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
            $c = trim((string)$c);
            if ($c !== '') return $c;
        }
        
        // email local-part fallback
        $email = (string)($u->email ?? '');
        if (str_contains($email, '@')) return strtok($email, '@');
        
        return 'there';
    }

    // ========== CONVERSATION MEMORY METHODS ==========

    /**
     * Get conversation memory for a session
     * 
     * @return array Memory structure with topics, people, phrases, etc.
     */
    public function getMemory(int $sessionId): array
    {
        return session("conversation_memory_{$sessionId}", [
            'discussed_topics' => [],
            'mentioned_people' => [],
            'key_phrases' => [],
            'emotions_over_time' => [],
            'last_question_asked' => null,
            'turn_count' => 0,
        ]);
    }

    /**
     * Update conversation memory with new turn information
     */
    public function updateMemory(
        int $sessionId,
        ?string $topic,
        array $keywords,
        array $emotions,
        ?string $lastQuestion
    ): void {
        $memory = $this->getMemory($sessionId);
        
        // Track topic frequency
        if ($topic) {
            $memory['discussed_topics'][$topic] = 
                ($memory['discussed_topics'][$topic] ?? 0) + 1;
        }
        
        // Extract and store people mentioned
        $people = $this->extractPeople($keywords, $topic);
        foreach ($people as $person => $contexts) {
            if (!isset($memory['mentioned_people'][$person])) {
                $memory['mentioned_people'][$person] = [];
            }
            $memory['mentioned_people'][$person] = array_unique(array_merge(
                $memory['mentioned_people'][$person],
                $contexts
            ));
        }
        
        // Store key phrases (max 5 recent)
        foreach ($keywords as $phrase) {
            if (!in_array($phrase, $memory['key_phrases'])) {
                $memory['key_phrases'][] = $phrase;
            }
        }
        $memory['key_phrases'] = array_slice($memory['key_phrases'], -5);
        
        // Track emotions over time (max 5 turns)
        if (!empty($emotions)) {
            $memory['emotions_over_time'][] = $emotions;
            $memory['emotions_over_time'] = array_slice($memory['emotions_over_time'], -5);
        }
        
        // Store last question
        if ($lastQuestion) {
            $memory['last_question_asked'] = $lastQuestion;
        }
        
        $memory['turn_count']++;
        
        session(["conversation_memory_{$sessionId}" => $memory]);
    }

    /**
     * Extract people and their contexts from keywords and topic
     * 
     * @return array ['person' => ['context1', 'context2']]
     */
    private function extractPeople(array $keywords, ?string $topic): array
    {
        $people = [];
        
        // Common person patterns with context words
        $personPatterns = [
            'parents' => ['disappointed', 'mad', 'angry', 'strict', 'fighting', 'arguing', 'pressure', 'expect'],
            'mom' => ['disappointed', 'mad', 'angry', 'crying', 'worried'],
            'dad' => ['disappointed', 'mad', 'angry', 'strict', 'yelling'],
            'mother' => ['disappointed', 'mad', 'angry'],
            'father' => ['disappointed', 'mad', 'angry'],
            'girlfriend' => ['broke up', 'breakup', 'cheated', 'left', 'dumped', 'ghosted'],
            'boyfriend' => ['broke up', 'breakup', 'cheated', 'left', 'dumped', 'ghosted'],
            'friend' => ['ghosted', 'betrayed', 'left out', 'backstabbed', 'fake'],
            'friends' => ['ghosted', 'betrayed', 'left out', 'abandoned'],
            'best friend' => ['ghosted', 'betrayed', 'left', 'backstabbed'],
            'classmate' => ['bullying', 'teasing', 'mocking', 'making fun'],
            'teacher' => ['unfair', 'strict', 'mean', 'failing me'],
            'lola' => ['died', 'sick', 'passed away'],
            'lolo' => ['died', 'sick', 'passed away'],
        ];
        
        // Check keywords for person mentions
        $fullText = implode(' ', $keywords);
        
        foreach ($personPatterns as $person => $contexts) {
            // Check if person is mentioned
            if (preg_match("/\b{$person}\b/i", $fullText)) {
                $foundContexts = [];
                
                // Check which contexts apply
                foreach ($contexts as $context) {
                    if (stripos($fullText, $context) !== false) {
                        $foundContexts[] = $context;
                    }
                }
                
                // If we found the person, add them (even without specific context)
                if (!empty($foundContexts) || $topic) {
                    $people[$person] = $foundContexts;
                }
            }
        }
        
        return $people;
    }

    /**
     * Get the primary person being discussed (most recently mentioned)
     */
    public function getPrimaryPerson(array $memory): ?string
    {
        if (empty($memory['mentioned_people'])) {
            return null;
        }
        
        // Return the last added person (most recent)
        $keys = array_keys($memory['mentioned_people']);
        return end($keys) ?: null;
    }

    /**
     * Get the most discussed topic
     */
    public function getPrimaryTopic(array $memory): ?string
    {
        if (empty($memory['discussed_topics'])) {
            return null;
        }
        
        // Sort by count descending
        arsort($memory['discussed_topics']);
        $keys = array_keys($memory['discussed_topics']);
        return $keys[0] ?? null;
    }

    /**
     * Analyze emotion progression to detect worsening mental state
     * 
     * @return array ['status' => 'improving|stable|worsening', 'severity_change' => int]
     */
    public function analyzeEmotionProgression(array $memory): array
    {
        $emotions = $memory['emotions_over_time'] ?? [];
        
        if (count($emotions) < 2) {
            return ['status' => 'stable', 'severity_change' => 0];
        }
        
        // Define emotion severity levels
        $severityMap = [
            'hopeless' => 10,
            'not_ok' => 9,
            'overwhelmed' => 8,
            'ashamed' => 8,
            'guilty' => 7,
            'anxious' => 7,
            'stressed' => 7,
            'sad' => 6,
            'angry' => 6,
            'lonely' => 6,
            'disappointed' => 5,
            'tired' => 5,
            'confused' => 4,
            'bored' => 2,
        ];
        
        // Calculate average severity for last 2 turns
        $recent = array_slice($emotions, -2);
        $severities = [];
        
        foreach ($recent as $turnEmotions) {
            $turnSeverity = 0;
            $count = 0;
            foreach ($turnEmotions as $emotion) {
                if (isset($severityMap[$emotion])) {
                    $turnSeverity += $severityMap[$emotion];
                    $count++;
                }
            }
            $severities[] = $count > 0 ? $turnSeverity / $count : 0;
        }
        
        // Compare severity
        $change = $severities[1] - $severities[0];
        
        if ($change >= 2) {
            return ['status' => 'worsening', 'severity_change' => (int)$change];
        } elseif ($change <= -2) {
            return ['status' => 'improving', 'severity_change' => (int)$change];
        }
        
        return ['status' => 'stable', 'severity_change' => 0];
    }
}
