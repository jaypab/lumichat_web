<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_walkin_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Sino ang counselor na nag-handle ng walk-in
            $table->unsignedBigInteger('counselor_id')->nullable();

            // Optional link to regular student record (kung meron kang tbl_students)
            $table->unsignedBigInteger('student_id')->nullable();

            // Basic student profile captured sa walk-in form
            $table->string('student_name', 191);
            $table->string('course', 100);      // e.g. BSIT, EDUC, etc.
            $table->string('year_level', 20);   // e.g. 1st, 2nd, 3rd, 4th, Other

            // Brief reason for visit
            $table->text('reason')->nullable();

            /**
             * Status – align sa chips na ginagamit mo sa index:
             * pending | ongoing | completed | canceled | no_show
             * For walk-ins usually "ongoing" agad pag start.
             */
            $table->string('status', 20)->default('ongoing');

            // When the student actually walked in / session started / ended
            $table->dateTime('started_at')->nullable();  // from start button
            $table->dateTime('ended_at')->nullable();    // from end button

            // Optional computed duration in minutes (pwede mong i-fill sa controller)
            $table->unsignedInteger('duration_minutes')->nullable();

            // If ever i-convert mo into formal appointment entry
            $table->unsignedBigInteger('appointment_id')->nullable();

            // Audit fields
            $table->timestamps();            // created_at, updated_at
            $table->softDeletes();           // deleted_at (for archival, optional)

            // Indexes / foreign keys (adjust table names as per schema mo)
            $table->index('counselor_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('started_at');

            // FK examples – comment out if wala pa yung tables
            // $table->foreign('counselor_id')->references('id')->on('tbl_counselors')->onDelete('set null');
            // $table->foreign('student_id')->references('id')->on('tbl_students')->onDelete('set null');
            // $table->foreign('appointment_id')->references('id')->on('tbl_appointments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_walkin_sessions');
    }
};
