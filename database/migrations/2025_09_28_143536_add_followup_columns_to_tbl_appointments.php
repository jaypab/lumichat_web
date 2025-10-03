<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            // Optional note for follow-ups (or any appointment)
            if (!Schema::hasColumn('tbl_appointments', 'note')) {
                $table->text('note')->nullable()->after('status');
            }

            // Parent link (original appointment). Nullable for “first” appointments.
            if (!Schema::hasColumn('tbl_appointments', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                // If your PK is 'id' on the same table:
                $table->foreign('parent_id')
                      ->references('id')
                      ->on('tbl_appointments')
                      ->nullOnDelete(); // set parent_id = null if parent is deleted
            }

            // Optional: useful when querying follow-ups quickly
            if (!Schema::hasColumn('tbl_appointments', 'finalized_at')) {
                // (Only add if you want it and it doesn't already exist)
                // $table->timestamp('finalized_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_appointments', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('tbl_appointments', 'note')) {
                $table->dropColumn('note');
            }
        });
    }
};
