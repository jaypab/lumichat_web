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

        $res = Http::timeout(8)->post($url, [
            'sender'  => $senderId,
            'message' => $message,
        ]);

        if (!$res->ok()) {
            return [[ 'text' => 'Sorry, the assistant is temporarily unavailable. Please try again shortly.' ]];
        }

        // Rasa returns an array of messages: [{text: "..."} ...]
        return $res->json() ?? [];
    }
}
