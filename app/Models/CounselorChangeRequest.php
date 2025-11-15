<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounselorChangeRequest extends Model
{
    protected $table = 'counselor_change_requests';

    // Kung gumagamit ka ng timestamps sa table (oo, meron kang created_at/updated_at)
    public $timestamps = true;

    protected $fillable = [
        'appointment_id',
        'student_id',              // or requested_by_student_id kung ito talaga ang column name
        'requested_by_student_id', // include both para sure
        'current_counselor_id',
        'reason_code',
        'reason_text',
        'status',

        // 🔴 IMPORTANT: ito ang nawawala sayo
        'preference_counselor_id',

        // optional / extra columns, kung meron ka nito sa table
        'preference_traits',
        'previous_counselor_id',
        'handled_by_admin_id',
        'handled_at',
        'decision_notes',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}