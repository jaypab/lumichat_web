<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');      // users.id
            $table->unsignedBigInteger('counselor_id');    // users.id
            $table->dateTime('scheduled_at')->index();
            $table->enum('status', ['pending','confirmed','canceled','completed'])
                  ->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id','counselor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_appointments');
    }
};
