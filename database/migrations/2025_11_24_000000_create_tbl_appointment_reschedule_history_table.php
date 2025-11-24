<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_appointment_reschedule_history', function (Blueprint $table) {
            $table->id();

            // Core
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('student_id')->nullable();

            // If reschedule also changed counselor
            $table->unsignedBigInteger('old_counselor_id')->nullable();
            $table->unsignedBigInteger('new_counselor_id')->nullable();

            // Old / new date & time
            $table->timestamp('old_scheduled_at')->nullable();
            $table->timestamp('new_scheduled_at')->nullable();

            // Optional reason / note
            $table->string('reason', 255)->nullable();

            // Who changed it
            $table->unsignedBigInteger('changed_by')->nullable();      // user_id
            $table->string('changed_by_role', 30)->nullable();         // admin / counselor / system

            $table->timestamps();

            // FKs (adjust table names if different in your project)
            $table->foreign('appointment_id')
                  ->references('id')->on('tbl_appointments')
                  ->cascadeOnDelete();

            $table->foreign('student_id')
                  ->references('id')->on('tbl_users')
                  ->nullOnDelete();

            $table->foreign('old_counselor_id')
                  ->references('id')->on('tbl_users')
                  ->nullOnDelete();

            $table->foreign('new_counselor_id')
                  ->references('id')->on('tbl_users')
                  ->nullOnDelete();

            $table->foreign('changed_by')
                  ->references('id')->on('tbl_users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_appointment_reschedule_history');
    }
};
