<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_counselor_logs', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('counselor_id')->index();
            $t->unsignedSmallInteger('month');   // 1..12
            $t->unsignedSmallInteger('year');    // e.g., 2025

            // rollup data
            $t->unsignedInteger('students_count')->default(0);
            $t->text('students_sample')->nullable();        // "Name A, Name B, Name C +28 others"
            $t->json('common_diagnoses')->nullable();       // ["Stress","Anxiety","Depression"]

            $t->timestamp('generated_at')->nullable();
            $t->timestamps();

            // one row per counselor/month
            $t->unique(['counselor_id', 'year', 'month'], 'uniq_cslr_ym');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_counselor_logs');
    }
};
