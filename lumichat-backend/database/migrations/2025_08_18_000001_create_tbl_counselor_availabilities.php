<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tbl_counselor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counselor_id')->constrained('tbl_counselors')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0=Sun … 6=Sat
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['counselor_id','weekday','start_time','end_time'],'uniq_slot');
        });
    }
    public function down(): void {
        Schema::dropIfExists('tbl_counselor_availabilities');
    }
};
