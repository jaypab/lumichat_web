<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_diagnosis_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');   // -> tbl_users.id
            $table->unsignedBigInteger('counselor_id'); // -> tbl_counselors.id
            $table->string('diagnosis_result', 255);    // e.g. Mild Anxiety
            $table->longText('notes')->nullable();      // counselor summary/plan
            $table->timestamps();

            $table->foreign('student_id')
                ->references('id')->on('tbl_users')
                ->cascadeOnDelete();

            $table->foreign('counselor_id')
                ->references('id')->on('tbl_counselors')
                ->cascadeOnDelete();

            $table->index(['student_id', 'counselor_id']);
            $table->index(['diagnosis_result']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_diagnosis_reports');
    }
};
