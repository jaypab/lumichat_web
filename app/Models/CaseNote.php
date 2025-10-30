<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
