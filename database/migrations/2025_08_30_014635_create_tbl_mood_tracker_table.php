<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_mood_tracker', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('chat_session_id');
        $table->unsignedBigInteger('user_id');
        $table->string('detected_mood', 100);
        $table->text('keywords')->nullable();
        $table->decimal('confidence', 5, 2)->nullable();
        $table->timestamps();

        // ✅ Match your actual table names:
        $table->foreign('chat_session_id')
            ->references('id')->on('chat_sessions')   // <— was tbl_chat_sessions
            ->cascadeOnDelete();

        $table->foreign('user_id')
            ->references('id')->on('tbl_users')
            ->cascadeOnDelete();

        $table->index(['user_id', 'chat_session_id']);
        $table->index(['detected_mood']);
        $table->index(['created_at']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_mood_tracker');
    }
};
