<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_counselors', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_counselors', 'is_accepting_appointments')) {
                $table->boolean('is_accepting_appointments')->default(true)->after('is_active');
            }
        });
    }
    public function down(): void {
        Schema::table('tbl_counselors', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_counselors', 'is_accepting_appointments')) {
                $table->dropColumn('is_accepting_appointments');
            }
        });
    }
};