<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure table exists after the rename step
        if (Schema::hasTable('tbl_users') && !Schema::hasColumn('tbl_users', 'appointments_enabled')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->boolean('appointments_enabled')->default(false)->after('role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_users') && Schema::hasColumn('tbl_users', 'appointments_enabled')) {
            Schema::table('tbl_users', function (Blueprint $table) {
                $table->dropColumn('appointments_enabled');
            });
        }
    }
};
