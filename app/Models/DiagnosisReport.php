<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisReport extends Model
{
    protected $table = 'tbl_diagnosis_reports';

    protected $fillable = [
        'student_id',
        'counselor_id',
        'diagnosis_result',
        'notes',
    ];

    public function student(): BelongsTo
    {
        // tbl_users.id
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor(): BelongsTo
    {
        // tbl_counselors.id
        return $this->belongsTo(Counselor::class, 'counselor_id');
    }
}
