<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table already exists (e.g., created via phpMyAdmin), skip creating it again.
        if (Schema::hasTable('tbl_case_notes')) {
            return;
        }

        Schema::create('tbl_case_notes', function (Blueprint $table) {
            $table->id();

            // Links
            $table->unsignedBigInteger('appointment_id')->unique(); // 1:1 per appointment
            $table->unsignedBigInteger('counselor_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();

            // Header
            $table->string('student_name')->nullable();
            $table->date('note_date')->nullable();
            $table->string('program_year')->nullable();
            $table->string('address')->nullable();

            // Sections
            $table->text('presenting_problem')->nullable();
            $table->text('observations')->nullable();
            $table->text('interventions')->nullable();
            $table->text('response')->nullable();
            $table->text('plan_followup')->nullable();

            // Emergency (optional)
            $table->string('emergency_contact_person')->nullable();
            $table->string('emergency_relationship')->nullable();
            $table->string('emergency_contact_no')->nullable();
            $table->string('emergency_address')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('appointment_id')
                ->references('id')->on('tbl_appointments')
                ->cascadeOnDelete();

            // Your app uses tbl_users (not users)
            $table->foreign('counselor_id')
                ->references('id')->on('tbl_users')
                ->nullOnDelete();

            $table->foreign('student_id')
                ->references('id')->on('tbl_users')
                ->nullOnDelete();

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
        Schema::dropIfExists('tbl_case_notes');
    }
};
