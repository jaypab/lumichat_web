<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // You already added chat_session_id in 2025_06_25_* migration,
            // so we only add idempotency_key if missing.
            if (!Schema::hasColumn('chats', 'idempotency_key')) {
                $table->uuid('idempotency_key')
                      ->nullable()
                      ->unique()
                      ->after('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            if (Schema::hasColumn('chats', 'idempotency_key')) {
                // This drops the implicit unique index (name is inferred)
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
