<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    /** Table name stays the same */
    protected $table = 'tbl_appointments';


    public function caseNote()  { return $this->hasOne(\App\Models\CaseNote::class, 'appointment_id'); }


    /** Keep your exact fillable fields */
    protected $fillable = [
        'student_id',
        'counselor_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    /** Keep your exact datetime casting */
    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /** Relations (typed, but same targets/keys you already use) */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor(): BelongsTo
    {
        // Keep pointing to your Counselor model (not User)
        return $this->belongsTo(Counselor::class, 'counselor_id');
    }
    public function caseNotes()
{
    return $this->hasMany(\App\Models\CaseNote::class, 'appointment_id');
}
}

