<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counselor extends Model
{
    protected $table = 'tbl_counselors';

    protected $fillable = [
        'name', 'email', 'phone', 'is_active',
    ];

    // Relationship: Counselor has many availability slots
    public function availabilities(): HasMany
    {
        return $this->hasMany(CounselorAvailability::class, 'counselor_id', 'id');
    }

    // handy scope
    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
