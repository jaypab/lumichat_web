<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'tbl_appointments';
    protected $fillable = ['student_id','counselor_id','scheduled_at','status','notes'];
    protected $casts = ['scheduled_at' => 'datetime'];

    public function student()   { return $this->belongsTo(User::class, 'student_id'); }
    public function counselor() { return $this->belongsTo(Counselor::class, 'counselor_id'); }
}
