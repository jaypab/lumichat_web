<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_registration', function (Blueprint $table) {
            $table->boolean('has_seen_tutorial')->default(false)->after('remember_token');
        });
    }
    public function down(): void {
        Schema::table('tbl_registration', function (Blueprint $table) {
            $table->dropColumn('has_seen_tutorial');
        });
    }
};