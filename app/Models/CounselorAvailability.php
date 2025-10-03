<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselorAvailability extends Model
{
    protected $table = 'tbl_counselor_availabilities';  // <-- important
    protected $fillable = ['counselor_id','weekday','start_time','end_time'];

    public function counselor(): BelongsTo {
        return $this->belongsTo(Counselor::class, 'counselor_id');
    }
}

