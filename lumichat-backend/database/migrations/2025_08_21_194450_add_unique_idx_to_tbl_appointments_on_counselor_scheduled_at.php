<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->unique(['counselor_id', 'scheduled_at'], 'uniq_counselor_datetime');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_counselor_datetime');
        });
    }
};
