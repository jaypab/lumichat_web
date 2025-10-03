<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    // Use your table name; change if you already have one
    protected $table = 'tbl_system_settings';
    public $timestamps = false;
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        try {
            if (!Schema::hasTable((new static)->getTable())) {
                return $default; // table not yet migrated in local
            }
        } catch (\Throwable $e) {
            return $default; // during php artisan migrate, etc.
        }

        return Cache::remember("sysset:$key", 300, function () use ($key, $default) {
            $val = static::query()->where('key', $key)->value('value');
            return $val ?? $default;
        });
    }
}
