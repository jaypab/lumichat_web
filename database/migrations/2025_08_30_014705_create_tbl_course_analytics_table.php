<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tbl_course_analytics')) {
            return;
        }

        Schema::create('tbl_course_analytics', function (Blueprint $table) {
            $table->id();

            // Grouping keys
            $table->string('course')->index();       // e.g. "BSIT"
            $table->string('year_level')->index();   // e.g. "1", "2", "3", "4"

            // Aggregated metrics
            $table->unsignedInteger('student_count')->default(0);
            // Pipe-separated list of common diagnoses / presenting problems:
            // e.g. "Stress||Anxiety||Academic Pressure"
            $table->text('common_diagnoses')->nullable();

            // JSON summary of breakdown: [{ "label": "Stress", "count": 10 }, ...]
            $table->json('breakdown')->nullable();

            // When this row was last rebuilt from source tables
            $table->timestamp('generated_at')->nullable();

            // Audit (follow style of tbl_case_notes)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();

            // FK to tbl_users (same pattern as tbl_case_notes)
            $table->foreign('created_by')
                ->references('id')->on('tbl_users')
                ->nullOnDelete();

            $table->foreign('updated_by')
                ->references('id')->on('tbl_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_course_analytics');
    }
};
