<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HighriskReview extends Model
{
    protected $table = 'tbl_highrisk_reviews';

    protected $fillable = [
        'chat_session_id','user_id','occurred_at',
        'detected_word','risk_score','snippet',
        'review_status','reviewed_by','reviewed_at','review_notes',
    ];

    protected $casts = [
        'occurred_at'   => 'datetime',
        'reviewed_at'   => 'datetime',
    ];
}
