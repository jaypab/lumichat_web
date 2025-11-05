<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('tbl_case_notes', 'deleted_at')) {
            Schema::table('tbl_case_notes', function (Blueprint $table) {
                // timezone version is fine; will be nullable by default
                $table->softDeletesTz()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tbl_case_notes', 'deleted_at')) {
            Schema::table('tbl_case_notes', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
