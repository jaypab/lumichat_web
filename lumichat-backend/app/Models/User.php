<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ✅ Table name
    protected $table = 'tbl_users';

    public const ROLE_STUDENT   = 'student';
    public const ROLE_COUNSELOR = 'counselor';
    public const ROLE_ADMIN     = 'admin';

    protected $fillable = [
        'name',
        'email',
        'course',
        'year_level',
        'contact_number',
        'password',
        'role',
        'appointments_enabled',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'   => 'datetime',
        'password'            => 'hashed',
        'appointments_enabled'=> 'boolean',
         'last_seen_appt_at' => 'datetime',
    ];

    // ── Roles ──────────────────────────────────────────────────────────────────
    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isCounselor(): bool { return $this->role === self::ROLE_COUNSELOR; }
    public function canAccessAdmin(): bool { return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_COUNSELOR], true); }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function chatSessions(){ return $this->hasMany(ChatSession::class); }
    public function chats(){ return $this->hasMany(Chat::class); }

    /**
     * Send the password reset notification (queued).
     * NOTE: The "From" name/address are already pulled from DB
     * in App\Notifications\ResetPasswordQueued::toMail().
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordQueued($token));
    }
}
