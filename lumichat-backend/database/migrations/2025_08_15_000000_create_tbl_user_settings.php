<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('email_reminders')->default(true);
            $table->boolean('sms_alerts')->default(false);
            $table->boolean('autosave_chats')->default(true);
            $table->unsignedSmallInteger('autodelete_days')->nullable(); // e.g., 30; null = off
            $table->boolean('dark_mode')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_user_settings');
    }
};
