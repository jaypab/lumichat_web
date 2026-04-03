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
        'sis',              // ✅ SIS login id
        'course',
        'year_level',
        'contact_number',
        'profile_picture',
        'password',
        'role',
        'appointments_enabled',
        'last_seen_announcement_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'appointments_enabled' => 'boolean',
        'last_seen_appt_at'    => 'datetime',
        'last_seen_announcement_at' => 'datetime',
    ];

    // ── Roles ────────────────────────────────────────────────────────────────
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCounselor(): bool
    {
        return $this->role === self::ROLE_COUNSELOR;
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_COUNSELOR], true);
    }

    // ── Relations ───────────────────────────────────────────────────────────
    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Helper for login: find user by email OR SIS.
     * We will use this in the login controller later.
     */
    public static function findByLogin(string $identifier): ?self
    {
        $identifier = trim($identifier);

        return static::where('email', $identifier)
            ->orWhere('sis', $identifier)
            ->first();
    }

    /**
     * Send the password reset notification (queued).
     * NOTE: The "From" name/address are already pulled from DB
     * in App\Notifications\ResetPasswordQueued::toMail().
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordQueued($token));
    }

    /**
     * Get the user's initials for the placeholder avatar.
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', (string) $this->name);
        if (count($words) >= 2) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }
        return mb_strtoupper(mb_substr((string) $this->name, 0, 2));
    }

    /**
     * Get the full URL for the profile picture, if it exists.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->profile_picture ? asset('storage/' . $this->profile_picture) : null;
    }
}
