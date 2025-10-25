<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_diagnosis_reports', function (Blueprint $table) {
            // add FK to appointments
            if (!Schema::hasColumn('tbl_diagnosis_reports', 'appointment_id')) {
                $table->unsignedBigInteger('appointment_id')->after('id')->index();
            }

            // make diagnosis_result long enough for 4,000 chars
            $table->mediumText('diagnosis_result')->change(); // TEXT/mediumText depending on your MySQL version

            // (optional) if you prefer "final_note" naming, you could also:
            // $table->mediumText('final_note')->nullable();
            // and later copy data from notes -> final_note, then drop notes.
        });

        // (optional but nice) add a foreign key if your MySQL engine supports it
        Schema::table('tbl_diagnosis_reports', function (Blueprint $table) {
            // safeguard: wrap in try/catch if you have legacy engines
            // $table->foreign('appointment_id')->references('id')->on('tbl_appointments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tbl_diagnosis_reports', function (Blueprint $table) {
            // revert type if needed
            $table->string('diagnosis_result', 255)->change();
            // drop FK first if you created it
            // $table->dropForeign(['appointment_id']);
            $table->dropColumn('appointment_id');
        });
    }
};