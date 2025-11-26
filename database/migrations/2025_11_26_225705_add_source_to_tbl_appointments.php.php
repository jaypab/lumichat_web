<?php
// database/migrations/2025_11_26_000000_add_source_to_tbl_appointments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            // simple string flag; null = normal booking
            $table->string('appointment_source', 32)
                  ->nullable()
                  ->after('status'); // or wherever you like
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->dropColumn('appointment_source');
        });
    }
};
