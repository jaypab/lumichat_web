<?php

namespace App\Observers;

use App\Models\ChatSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatSessionObserver
{
    /**
     * Fire after a ChatSession row is created or updated.
     * We alert admins when the session *enters* HIGH risk (or crosses a score threshold),
     * write a (deduped) review row, and throttle alerts to avoid spam.
     */
    public function saved(ChatSession $session): void
    {
        try {
            // --- 1) Decide "is high risk?" using flexible rules (works whether you store level or score) ---
            $enteredHigh = $this->enteredHighRisk($session);
            if (! $enteredHigh) {
                return;
            }

            // --- 2) Anti-spam: throttle per session per calendar day (adjust if you prefer) ---
            $todayKey = sprintf('risk_alert:session:%d:%s', (int) $session->id, now()->toDateString());
            if (Cache::has($todayKey)) {
                return; // already alerted today for this session
            }
            Cache::put($todayKey, 1, now()->endOfDay());

            // --- 3) Insert (or dedupe) a pending review row for this event ---
            $this->ensurePendingReview($session);

            // --- 4) Build deep link to Admin → Chatbot Session ---
            $sessionUrl = null;
            foreach (['admin.chatbot-sessions.show','admin.chatbot.sessions.show','admin.chatbot_sessions.show'] as $name) {
                if (Route::has($name)) {
                    $sessionUrl = route($name, (int) $session->id);
                    break;
                }
            }
            if (!$sessionUrl) {
                // fallback if route name ever changes (assumes /admin prefix)
                $sessionUrl = url('/admin/chatbot-sessions/'.(int) $session->id);
            }

            // --- 5) Resolve student label (non-fatal if users table differs) ---
            $studentName  = null;
            $studentEmail = null;
            try {
                if (method_exists($session, 'user') && $session->relationLoaded('user')) {
                    $studentName  = $session->user?->name;
                    $studentEmail = $session->user?->email;
                } elseif (property_exists($session, 'user_id')) {
                    $u = DB::table('tbl_users')->where('id', $session->user_id)->first(['name','email']);
                    $studentName  = $u->name  ?? null;
                    $studentEmail = $u->email ?? null;
                }
            } catch (\Throwable $e) {
                // ignore
            }
            $studentLabel = $studentName ?: ('#'.(int) ($session->user_id ?? 0));

            // --- 6) In-app notify all admins (soft-fail) ---
            $title = 'High-risk student detected';
            $body  = 'The system detected a high-risk student • '.$studentLabel.($studentEmail ? (' <'.$studentEmail.'>') : '');

            try {
                if (class_exists(\App\Support\Notify::class) && method_exists(\App\Support\Notify::class, 'admins')) {
                    \App\Support\Notify::admins($title, $body, $sessionUrl);
                }
            } catch (\Throwable $e) {
                Log::notice('Notify::admins failed for high-risk alert', ['session_id' => $session->id, 'e' => $e->getMessage()]);
            }

            Log::info('High-risk admin alert sent', ['session_id' => $session->id, 'link' => $sessionUrl]);
        } catch (\Throwable $e) {
            Log::error('ChatSessionObserver.saved error', ['session_id' => $session->id ?? null, 'e' => $e]);
        }
    }

    /**
     * Return true only when we *enter* high risk.
     * Supports string level or numeric score (0–1 or 0–100).
     */
    private function enteredHighRisk(ChatSession $s): bool
    {
        // 1) String level
        if (Schema::hasColumn($s->getTable(), 'risk_level')) {
            $prev = strtolower(trim((string) ($s->getOriginal('risk_level') ?? '')));
            $curr = strtolower(trim((string) ($s->risk_level ?? '')));
            $isCurrHigh = in_array($curr, ['h','high','high-risk','high_risk'], true);
            $wasPrevHigh = in_array($prev, ['h','high','high-risk','high_risk'], true);
            if ($isCurrHigh && ! $wasPrevHigh) {
                return true;
            }
        }

        // 2) Numeric score (handle 0–1 or 0–100 scales)
        if (Schema::hasColumn($s->getTable(), 'risk_score')) {
            $prev = (float) ($s->getOriginal('risk_score') ?? 0);
            $curr = (float) ($s->risk_score ?? 0);

            // If values look like 0–1, use 0.80; if they look like 0–100, use 80.
            $threshold = ($curr <= 1.0 && $prev <= 1.0) ? 0.80 : 80.0;

            if ($prev < $threshold && $curr >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure we have a recent pending review (dedupe ~10 minutes).
     * Requires tbl_highrisk_reviews (will silently skip if table is absent).
     */
    private function ensurePendingReview(ChatSession $session): void
    {
        if (! Schema::hasTable('tbl_highrisk_reviews')) {
            return;
        }

        $sessionId = (int) $session->id;
        $userId    = (int) ($session->user_id ?? 0);
        $now       = now();

        // De-dup within the last 10 minutes
        $recentExists = DB::table('tbl_highrisk_reviews')
            ->where('chat_session_id', $sessionId)
            ->where('review_status', 'pending')
            ->where('occurred_at', '>=', $now->copy()->subMinutes(10))
            ->exists();

        if ($recentExists) {
            return;
        }

        // Try to capture a short snippet if available on the session
        $snippet = null;
        if (Schema::hasColumn($session->getTable(), 'last_message')) {
            $snippet = (string) str($session->last_message ?? '')->limit(200);
        }

        DB::table('tbl_highrisk_reviews')->insert([
            'chat_session_id' => $sessionId,
            'user_id'         => $userId ?: null,
            'occurred_at'     => $now,
            'detected_word'   => $session->risk_level ? ('level:'.strtolower((string) $session->risk_level)) : 'score_threshold',
            'risk_score'      => $session->risk_score,
            'snippet'         => $snippet,
            'review_status'   => 'pending',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}
