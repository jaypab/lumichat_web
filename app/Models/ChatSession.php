<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ChatSession extends Model
{
    use HasFactory;

    protected $table = 'chat_sessions';

    protected $fillable = [
        'user_id',
        'topic_summary',
        'is_anonymous',
        'risk_level',
        'emotions',       // ← NEW
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'emotions'     => 'array',   // ← NEW (JSON <-> array)
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function chats()
    {
        return $this->hasMany(Chat::class, 'chat_session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
