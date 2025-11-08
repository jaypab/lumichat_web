<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            // MySQL 8+ (stored generated column)
            // For active rows (pending/confirmed) -> active_key=1
            // For non-active rows -> active_key=id (unique by itself)
            $table->unsignedBigInteger('active_key')
                  ->storedAs("CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE id END")
                  ->after('status');

            $table->unique(['student_id','active_key'], 'uq_student_one_active');
        });
    }
    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->dropUnique('uq_student_one_active');
            $table->dropColumn('active_key');
        });
    }
};
