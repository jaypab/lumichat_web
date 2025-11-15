<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAnalytics extends Model
{
    protected $table = 'tbl_course_analytics';

    protected $fillable = [
        'course',
        'year_level',
        'student_count',
        'common_diagnoses',
        'breakdown',
        'generated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'breakdown'    => 'array',
        'generated_at' => 'datetime',
    ];
}
