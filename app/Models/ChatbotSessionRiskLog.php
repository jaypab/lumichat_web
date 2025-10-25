<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotSessionRiskLog extends Model
{
    protected $fillable = [
        'chatbot_session_id','admin_id',
        'from_level','to_level','to_score','note'
    ];
}