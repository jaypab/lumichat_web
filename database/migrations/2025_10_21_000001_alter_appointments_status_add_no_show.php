<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Requires doctrine/dbal for ->change()
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->enum('status', [
                'pending','confirmed','canceled','completed','no_show'
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->enum('status', [
                'pending','confirmed','canceled','completed'
            ])->default('pending')->change();
        });
    }
};
