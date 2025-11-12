<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounselorChangeRequest extends Model
{
    protected $fillable = [
        'appointment_id','requested_by_student_id','current_counselor_id',
        'reason_code','reason_text','preference_counselor_id','preference_traits',
        'status','handled_by_admin_id','handled_at','decision_notes',
    ];

    protected $casts = [
        'preference_traits' => 'array',
        'handled_at' => 'datetime',
    ];
}

