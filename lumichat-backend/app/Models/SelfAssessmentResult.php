<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelfAssessment extends Model
{
    protected $table = 'tbl_self_assessment';

    protected $fillable = [
        'student_id',
        'student_name',
        'assessment_result',
        'initial_diagnosis_result',
        'initial_diagnosis_date_time',
        // 'note', // <- add if you add the column in the migration
    ];

    protected $casts = [
        'initial_diagnosis_date_time' => 'datetime',
    ];
}
