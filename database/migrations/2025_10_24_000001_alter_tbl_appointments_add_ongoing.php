<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Adjust table name/column as needed
        // MySQL ENUM add: rebuild with the extra value
        DB::statement("
            ALTER TABLE tbl_appointments
            MODIFY COLUMN status ENUM('pending','confirmed','canceled','completed','no_show','ongoing')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE tbl_appointments
            MODIFY COLUMN status ENUM('pending','confirmed','canceled','completed','no_show')
            NOT NULL DEFAULT 'pending'
        ");
    }
};
