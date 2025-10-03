<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiChatController extends Controller
{
    // ==== Constants ====
    private const DEFAULT_RASA_WEBHOOK = 'http://localhost:5005/webhooks/rest/webhook';

    /**
     * Proxy a user message to Rasa and return bot replies.
     */
    public function handleMessage(Request $request): JsonResponse
    {
        // Validate early (fail-fast)
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // Require an authenticated user (prevents null->id errors)
        if (!$request->user()) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $rasaUrl = $this->getRasaWebhookUrl();

        try {
            $response = Http::timeout(8)
                ->connectTimeout(2)
                ->acceptJson()
                ->asJson()
                ->post($rasaUrl, [
                    'sender'  => (string) $request->user()->id, // Rasa expects string sender IDs
                    'message' => $validated['message'],
                ]);

            if (!$response->ok()) {
                Log::warning('Rasa returned non-OK response', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                // Keep error shape simple/same style
                return response()->json(['error' => 'Something went wrong.'], 500);
            }

            $payload = $response->json();
            $botMessages = collect(\is_array($payload) ? $payload : [])
                ->pluck('text')
                ->filter(fn ($t) => \is_string($t) && $t !== '')
                ->values();

            return response()->json([
                'bot_reply' => $botMessages,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error sending message to Rasa', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    // ==== Private helpers ====

    /**
     * Resolve the Rasa REST webhook URL from config with a safe fallback.
     */
    private function getRasaWebhookUrl(): string
    {
        // Configure in config/services.php => ['rasa' => ['rest_webhook_url' => 'http://...']]
        return (string) (config('services.rasa.rest_webhook_url') ?? self::DEFAULT_RASA_WEBHOOK);
    }
}
