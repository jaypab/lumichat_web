<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Drop old view if it exists, then (re)create
        DB::statement('DROP VIEW IF EXISTS `tbl_students`');

        // Detect columns so it works whether you use role or user_type
        $hasRole     = Schema::hasColumn('tbl_users', 'role');
        $hasUserType = Schema::hasColumn('tbl_users', 'user_type');

        // Basic projection used by admin list/search
        $select = "SELECT id, name, email FROM tbl_users";
        if ($hasRole) {
            $sql = "$select WHERE role = 'student'";
        } elseif ($hasUserType) {
            $sql = "$select WHERE user_type = 'student'";
        } else {
            // No role column — expose everyone (still safe for listing names/emails)
            $sql = $select;
        }

        DB::statement("CREATE VIEW `tbl_students` AS $sql");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `tbl_students`');
    }
};
