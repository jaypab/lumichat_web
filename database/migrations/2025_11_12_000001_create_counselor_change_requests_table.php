<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('counselor_change_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('appointment_id');
            $t->unsignedBigInteger('requested_by_student_id');
            $t->unsignedBigInteger('current_counselor_id')->nullable();
            $t->string('reason_code', 32);
            $t->text('reason_text')->nullable();
            $t->unsignedBigInteger('preference_counselor_id')->nullable();
            $t->json('preference_traits')->nullable(); // e.g. {"gender":"female","language":"Cebuano"}
            $t->enum('status', ['requested','approved','declined','canceled'])->default('requested');
            $t->unsignedBigInteger('handled_by_admin_id')->nullable();
            $t->timestamp('handled_at')->nullable();
            $t->text('decision_notes')->nullable();
            $t->timestamps();

            $t->index('appointment_id');
            $t->foreign('appointment_id')->references('id')->on('tbl_appointments')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('counselor_change_requests'); }
};
