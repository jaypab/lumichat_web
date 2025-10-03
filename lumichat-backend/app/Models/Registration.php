<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Registration extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'tbl_registration';

    protected $fillable = [
        'full_name',
        'email',
        'contact_number',
        'course',
        'year_level',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Always store email in lowercase.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn($value) => is_string($value) ? mb_strtolower($value) : $value
        );
    }
}
