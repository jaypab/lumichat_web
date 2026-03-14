<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRequest extends Model
{
    use HasFactory;

    protected $table = 'tbl_account_requests';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'sis',
        'name',
        'email',
        'contact_number',
        'course',
        'year_level',
        'attachment_path',
        'request_ip',
        'device_key',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_user_id',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }
}
