<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table doesn't exist, CREATE it complete (now with emotions)
        if (!Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->boolean('is_anonymous')->default(false);
                $table->string('topic_summary')->nullable();
                $table->enum('risk_level', ['low','moderate','high'])->default('low');
                $table->json('emotions')->nullable(); // ← NEW
                $table->timestamps();
            });
            return;
        }

        // Else, table exists → add any missing columns safely
        Schema::table('chat_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_sessions', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index()->first();
            }
            if (!Schema::hasColumn('chat_sessions', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('user_id');
            }
            if (!Schema::hasColumn('chat_sessions', 'topic_summary')) {
                $table->string('topic_summary')->nullable()->after('is_anonymous');
            }
            if (!Schema::hasColumn('chat_sessions', 'risk_level')) {
                $table->enum('risk_level', ['low','moderate','high'])->default('low')->after('topic_summary');
            }
            if (!Schema::hasColumn('chat_sessions', 'emotions')) {
                $table->json('emotions')->nullable()->after('risk_level'); // ← NEW
            }
            if (!Schema::hasColumn('chat_sessions', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // Prefer a non-destructive rollback: just drop the new column if it exists.
        if (Schema::hasTable('chat_sessions')) {
            Schema::table('chat_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('chat_sessions', 'emotions')) {
                    $table->dropColumn('emotions');
                }
            });
        }
    }
};
