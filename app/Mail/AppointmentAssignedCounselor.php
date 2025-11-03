<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentAssignedCounselor extends Mailable
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
        return $this->subject('LumiCHAT — New Appointment Assigned')
            ->view('emails.appointments.assigned-counselor')
            ->with([
                'appointmentId' => $this->appointmentId,
                'studentName'   => $this->studentName,
                'counselorName' => $this->counselorName,
                'scheduledAt'   => $this->scheduledAt,
                'whenNice'      => $this->whenNice,
            ]);
    }
}
