<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    protected $table = 'tbl_user_settings';

    protected $fillable = [
        'user_id',
        'email_reminders',
        'sms_alerts',
        'autosave_chats',
        'autodelete_days',
        'dark_mode',
    ];

    protected $casts = [
        'email_reminders' => 'boolean',
        'sms_alerts'      => 'boolean',
        'autosave_chats'  => 'boolean',
        'dark_mode'       => 'boolean',
        'autodelete_days' => 'integer',
    ];
}
