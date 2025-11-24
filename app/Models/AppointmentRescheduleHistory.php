<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentRescheduleHistory extends Model
{
    protected $table = 'tbl_appointment_reschedule_history';

    protected $fillable = [
        'appointment_id',
        'student_id',
        'old_counselor_id',
        'new_counselor_id',
        'old_scheduled_at',
        'new_scheduled_at',
        'reason',
        'changed_by',
        'changed_by_role',
    ];

    protected $casts = [
        'old_scheduled_at' => 'datetime',
        'new_scheduled_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function oldCounselor()
    {
        return $this->belongsTo(User::class, 'old_counselor_id');
    }

    public function newCounselor()
    {
        return $this->belongsTo(User::class, 'new_counselor_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
