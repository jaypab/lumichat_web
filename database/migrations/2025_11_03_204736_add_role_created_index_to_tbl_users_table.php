<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            // composite index on role + created_at
            $table->index(['role', 'created_at'], 'tbl_users_role_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropIndex('tbl_users_role_created_index');
        });
    }
};

