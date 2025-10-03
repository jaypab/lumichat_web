<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_course_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('course', 255);         // e.g., BSIT
            $table->string('year_level', 50);      // e.g., 2nd Year
            $table->unsignedInteger('total_students')->default(0);
            $table->text('common_diagnosis')->nullable(); // CSV or JSON list
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['course', 'year_level']); // one row per course/year
            $table->index(['generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_course_analytics');
    }
};
