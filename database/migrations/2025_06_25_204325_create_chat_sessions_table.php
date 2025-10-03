<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table doesn't exist, CREATE it complete
        if (!Schema::hasTable('chat_sessions')) {
            Schema::create('chat_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->boolean('is_anonymous')->default(false);                 // ✅ new
                $table->string('topic_summary')->nullable();
                $table->enum('risk_level', ['low','moderate','high'])->default('low'); // ✅ new
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
            if (!Schema::hasColumn('chat_sessions', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // Safe rollback for fresh DBs
        if (Schema::hasTable('chat_sessions')) {
            Schema::drop('chat_sessions');
        }
    }
};
