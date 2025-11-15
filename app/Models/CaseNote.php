<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Artisan;  
class CaseNote extends Model
{
    use SoftDeletes;

    protected $table = 'tbl_case_notes';

    protected $fillable = [
        'appointment_id','counselor_id','student_id',
        'student_name','note_date','program_year','address',
        'presenting_problem','observations','interventions','response','plan_followup',
        'emergency_contact_person','emergency_relationship','emergency_contact_no','emergency_address',
        'created_by','updated_by',
    ];

    protected $casts = [
        'note_date' => 'date',
    ];

    public function appointment() { return $this->belongsTo(\App\Models\Appointment::class, 'appointment_id'); }

    protected static function booted()
    {
        // Whenever a case note is created or updated
        static::saved(function (CaseNote $note) {
            // Rebuild course analytics from case notes
            Artisan::call('analytics:rebuild-courses');
        });

        // Whenever a case note is soft-deleted / force-deleted
        static::deleted(function (CaseNote $note) {
            Artisan::call('analytics:rebuild-courses');
        });
    }
}

