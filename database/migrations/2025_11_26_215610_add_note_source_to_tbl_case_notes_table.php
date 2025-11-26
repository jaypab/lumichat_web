<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_case_notes', function (Blueprint $table) {
            // simple string tag; index so it’s easy to sort/filter
            $table->string('note_source', 32)
                  ->nullable()
                  ->default(null)
                  ->after('appointment_id')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_case_notes', function (Blueprint $table) {
            $table->dropColumn('note_source');
        });
    }
};
