<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue; // uncomment if you want to queue
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeToLumichat extends Notification /* implements ShouldQueue */
{
    use Queueable;

    public function __construct(public string $name, public string $loginUrl) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to LumiCHAT 🎉')
            ->greeting('Hi ' . $this->name . ',')
            ->line('Your LumiCHAT account was created successfully.')
            ->line('You can now sign in and start using the chatbot for support anytime.')
            ->action('Sign in to LumiCHAT', $this->loginUrl)
            ->line('If you did not create this account, please ignore this email.');
    }
}
