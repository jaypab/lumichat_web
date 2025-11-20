<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminGeneric extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public ?string $body = null,
        public ?string $url  = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
        ];
    }
}
