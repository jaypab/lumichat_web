<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCode extends Notification
{
    public function __construct(public string $code) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage {
        return (new MailMessage)
            ->subject('Your LumiCHAT verification code')
            ->greeting('Hi!')
            ->line('Use this code to verify your email and continue:')
            ->line("**{$this->code}**")
            ->line('This code expires in 10 minutes.')
            ->line('If you did not request this, you can ignore the message.');
    }
}
