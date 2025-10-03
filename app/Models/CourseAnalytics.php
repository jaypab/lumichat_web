<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAnalytics extends Model
{
    protected $table = 'tbl_course_analytics';

    protected $fillable = [
        'course',
        'year_level',
        'total_students',
        'common_diagnosis',
        'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    /** Expose total_students as student_count for the blade */
    public function getStudentCountAttribute(): int
    {
        return (int) ($this->attributes['total_students'] ?? 0);
    }

    /** Return an array of common diagnoses (handles JSON or CSV) */
    public function getCommonDiagnosesAttribute(): array
    {
        $raw = (string) ($this->attributes['common_diagnosis'] ?? '');

        if ($raw === '') return [];

        $trim = ltrim($raw);
        if ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                // JSON can be ["A","B"] or [{"label":"A",...}]
                if (isset($decoded[0]) && is_array($decoded[0]) && array_key_exists('label', $decoded[0])) {
                    return array_values(array_filter(array_map(fn($r) => trim((string) ($r['label'] ?? '')), $decoded)));
                }
                return array_values(array_filter(array_map(fn($v) => trim((string) $v), $decoded)));
            }
        }

        // CSV
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
