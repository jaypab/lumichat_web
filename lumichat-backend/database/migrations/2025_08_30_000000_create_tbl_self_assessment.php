<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_self_assessment', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->string('student_name', 150)->nullable();
            // Mood selected: Happy, Calm, Sad, Anxious, Stressed
            $table->string('assessment_result', 50)->nullable();
            // Derived: 'low' | 'moderate'
            $table->string('initial_diagnosis_result', 20)->nullable();
            $table->timestamp('initial_diagnosis_date_time')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('initial_diagnosis_result');
            $table->index('initial_diagnosis_date_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_self_assessment');
    }
};
