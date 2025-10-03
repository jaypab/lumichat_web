<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'course'))          $table->string('course')->nullable()->after('email');
            if (!Schema::hasColumn('users', 'year_level'))      $table->string('year_level')->nullable()->after('course');
            if (!Schema::hasColumn('users', 'contact_number'))  $table->string('contact_number', 32)->nullable()->after('year_level');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['course','year_level','contact_number']);
        });
    }
};

