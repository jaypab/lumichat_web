<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->boolean('student_confirm_required')
                  ->default(false)
                  ->after('status');

            $table->timestamp('student_confirmed_at')
                  ->nullable()
                  ->after('student_confirm_required');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->dropColumn(['student_confirm_required', 'student_confirmed_at']);
        });
    }
};
