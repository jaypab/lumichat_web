<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If default "users" exists and "tbl_users" doesn't, rename it.
        if (Schema::hasTable('users') && !Schema::hasTable('tbl_users')) {
            Schema::rename('users', 'tbl_users');
        }
    }

    public function down(): void
    {
        // Rollback rename if needed.
        if (Schema::hasTable('tbl_users') && !Schema::hasTable('users')) {
            Schema::rename('tbl_users', 'users');
        }
    }
};
