<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('tbl_counselor_overrides')) {
            Schema::create('tbl_counselor_overrides', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('counselor_id')->index();
                $t->date('date')->index();
                $t->time('start_time');
                $t->time('end_time');
                $t->boolean('is_available')->default(true)->index(); // false = blackout
                $t->string('reason', 255)->nullable();
                $t->timestamps();
                // FK optional if you already enforce app-level:
                // $t->foreign('counselor_id')->references('id')->on('tbl_counselors')->cascadeOnDelete();
                $t->unique(['counselor_id','date','start_time','end_time'], 'uniq_c_override_span');
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('tbl_counselor_overrides');
    }
};
