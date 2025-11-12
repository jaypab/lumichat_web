<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CounselorReassignmentRequested extends Notification
{
    use Queueable;

    public function __construct(
        public int $appointmentId,
        public ?string $studentName = null,
        public ?string $reason = null,
    ) {}

    public function via($notifiable): array
    {
        return ['database']; // stored in notifications table
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'           => 'cr.request',
            'title'          => 'Reassignment requested',
            'message'        => 'Student requested counselor reassignment'
                                . ($this->studentName ? " • {$this->studentName}" : ''),
            'reason'         => $this->reason,
            'appointment_id' => $this->appointmentId,
            'url'            => route('counselor.appointments.show', $this->appointmentId),
        ];
    }
}
