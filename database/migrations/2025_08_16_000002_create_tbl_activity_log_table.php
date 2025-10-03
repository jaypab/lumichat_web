<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('tbl_activity_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('chat_session_id')->nullable();
            $t->string('event', 64);                 // 'session_created','risk_detected','crisis_prompt'
            $t->enum('risk_level', ['low','moderate','high'])->nullable();
            $t->json('details')->nullable();         // freeform context
            $t->timestamps();
        });
    }
    public function down(){ Schema::dropIfExists('tbl_activity_log'); }
};
