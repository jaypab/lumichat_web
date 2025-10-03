<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_counselor', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // display name
            $table->string('email')->unique();            // login/contact
            $table->string('phone')->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_active')->default(true);  // used by the KPI
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_counselor');
    }
};
