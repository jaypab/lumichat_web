<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_counselor_availabilities', function ($table) {
            // use DB::statement for MySQL "modify" to be precise
        });
        DB::statement("ALTER TABLE tbl_counselor_availabilities 
            MODIFY `date` DATE NULL,
            MODIFY `weekday` TINYINT UNSIGNED NULL,
            MODIFY `start_time` TIME NOT NULL,
            MODIFY `end_time` TIME NOT NULL,
            MODIFY `slot_type` ENUM('available','blocked') NOT NULL");
    }
    public function down(): void
    {
        // if you need to revert (optional)
        DB::statement("ALTER TABLE tbl_counselor_availabilities 
            MODIFY `date` DATE NOT NULL,
            MODIFY `weekday` TINYINT UNSIGNED NOT NULL");
    }
};