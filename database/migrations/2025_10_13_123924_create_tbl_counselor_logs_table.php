<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_counselor_logs', function (Blueprint $t) {
            $t->id();

            // Key
            $t->unsignedBigInteger('counselor_id')->index();
            $t->unsignedTinyInteger('month'); // 1..12
            $t->unsignedSmallInteger('year'); // yyyy

            // Summary fields
            $t->unsignedInteger('students_count')->default(0);
            $t->text('students_list')->nullable();            // "Alice | Bob | …"
            $t->json('common_dx')->nullable();                // ["Stress","Anxiety","…"]

            // Generated timestamp (time you computed this row)
            $t->timestamp('generated_at')->nullable();

            $t->timestamps();

            $t->unique(['counselor_id','month','year'], 'uniq_counselor_month_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_counselor_logs');
    }
};
