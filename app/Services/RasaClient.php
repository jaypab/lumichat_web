<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RasaClient
{
    public function __construct(
        private string $baseUrl = '',
        private string $restPath = ''
    ) {
        $this->baseUrl = config('services.rasa.base_url', env('RASA_BASE_URL', 'http://localhost:5005'));
        $this->restPath = config('services.rasa.rest_path', env('RASA_REST_PATH', '/webhooks/rest/webhook'));
    }

    public function sendMessage(string $senderId, string $message): array
    {
        $url = rtrim($this->baseUrl, '/') . $this->restPath;

        try {
            $res = Http::timeout(5)->post($url, [
                'sender'  => $senderId,
                'message' => $message,
            ]);

            if ($res->ok()) {
                return $res->json() ?? [];
            }
        } catch (\Exception $e) {
            \Log::error('[RASA_CLIENT] Communication failed', ['error' => $e->getMessage()]);
        }

        // Return a safe, hardcoded fallback if Rasa is unreachable
        return [[ 
            'text' => "I apologize, Earl. I'm having a little trouble connecting to my full engine right now, but I'm still here for you. Is there something specific on your mind?" 
        ]];
    }
}
