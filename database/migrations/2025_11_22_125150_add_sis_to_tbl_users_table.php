<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            // adjust length if your SIS format changes
            $table->string('sis', 20)
                  ->nullable()
                  ->unique()
                  ->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropUnique(['sis']);
            $table->dropColumn('sis');
        });
    }
};
