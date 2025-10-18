<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_date_type_reason_to_tbl_counselor_availabilities.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_counselor_availabilities', function (Blueprint $table) {
            // one-off date (nullable). Leave null for recurring weekday slots
            $table->date('date')->nullable()->after('counselor_id');

            // slot kind: available window or a block
            $table->enum('slot_type', ['available', 'blocked'])
                  ->default('available')
                  ->after('end_time');

            // counselor’s note (optional)
            $table->string('reason', 255)->nullable()->after('slot_type');
        });
    }

    public function down(): void {
        Schema::table('tbl_counselor_availabilities', function (Blueprint $table) {
            $table->dropColumn(['date','slot_type','reason']);
        });
    }
};

