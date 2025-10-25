<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chatbot_session_risk_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chatbot_session_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('from_level', 20)->nullable();   // low|moderate|high
            $table->string('to_level', 20);
            $table->unsignedTinyInteger('to_score')->default(0);
            $table->text('note')->nullable();               // the modal textarea
            $table->timestamps();

            $table->index('chatbot_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_session_risk_logs');
    }
};
