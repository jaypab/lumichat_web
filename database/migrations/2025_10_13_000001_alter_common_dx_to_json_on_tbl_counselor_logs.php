<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tbl_counselor_logs')) return;

        // Try JSON if supported; otherwise keep TEXT
        try {
            Schema::table('tbl_counselor_logs', function (Blueprint $table) {
                // If the column doesn't exist yet, create it; else change it
                if (!Schema::hasColumn('tbl_counselor_logs', 'common_dx')) {
                    $table->json('common_dx')->nullable()->after('students_list');
                } else {
                    $table->json('common_dx')->nullable()->change();
                }
            });
        } catch (\Throwable $e) {
            // Fallback to TEXT on MySQL variants that don't support json change()
            Schema::table('tbl_counselor_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('tbl_counselor_logs', 'common_dx')) {
                    $table->text('common_dx')->nullable()->after('students_list');
                } else {
                    $table->text('common_dx')->nullable()->change();
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tbl_counselor_logs')) return;

        // Revert to TEXT for simplicity
        Schema::table('tbl_counselor_logs', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_counselor_logs', 'common_dx')) {
                $table->text('common_dx')->nullable()->change();
            }
        });
    }
};
