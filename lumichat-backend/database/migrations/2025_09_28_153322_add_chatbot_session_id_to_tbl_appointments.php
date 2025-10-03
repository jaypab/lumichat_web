<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            // Adjust column types/names to your schema if different
            $table->unsignedBigInteger('chatbot_session_id')->nullable()->after('student_id');

            // FK to chatbot sessions; choose desired onDelete behavior
            $table->foreign('chatbot_session_id')
                  ->references('id')->on('chat_sessions')
                  ->onDelete('set null');

            // Helpful index to find “active by session”
            $table->index(['chatbot_session_id', 'status'], 'appt_session_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $table) {
            $table->dropForeign(['chatbot_session_id']);
            $table->dropIndex('appt_session_status_idx');
            $table->dropColumn('chatbot_session_id');
        });
    }
};
