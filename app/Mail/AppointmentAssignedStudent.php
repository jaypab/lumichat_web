<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentAssignedStudent extends Mailable
{
    use Queueable, SerializesModels;

    public int $appointmentId;
    public string $studentName;
    public string $counselorName;
    /** @var \DateTimeInterface|string */
    public $scheduledAt;
    public string $whenNice;

    public function __construct(
        int $appointmentId,
        string $studentName,
        string $counselorName,
        $scheduledAt,
        string $whenNice
    ) {
        $this->appointmentId = $appointmentId;
        $this->studentName   = $studentName;
        $this->counselorName = $counselorName;
        $this->scheduledAt   = $scheduledAt;
        $this->whenNice      = $whenNice;
    }

    public function build()
    {
        return $this->subject('LumiCHAT — Appointment Approved')
            ->view('emails.appointments.assigned-student');
    }
}
