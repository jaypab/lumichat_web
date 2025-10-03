<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPasswordQueued extends BaseResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable)
    {
        $fromAddress = SystemSetting::get('mail_from_address', config('mail.from.address'));
        $fromName    = SystemSetting::get('mail_from_name',    config('mail.from.name'));

        // Build the default message first
        $mail = parent::toMail($notifiable);

        // Set dynamic From (fallback to config if DB empty)
        if ($fromAddress) {
            $mail->from($fromAddress, $fromName);
        }

        // Small security copy
        $mail->line(__('This link expires in about 20 minutes. If you did not request it, you can ignore this email.'));

        return $mail;
    }
}
