<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $tables = ['tbl_chatbot_sessions', 'chat_sessions'];

    public function up(): void
    {
        foreach ($this->tables as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'high_risk_chat_id')) {
                    $table->unsignedBigInteger('high_risk_chat_id')->nullable()->after('risk_level')->index();
                }
                if (!Schema::hasColumn($tbl, 'high_risk_excerpt')) {
                    $table->string('high_risk_excerpt', 255)->nullable()->after('high_risk_chat_id');
                }
                if (!Schema::hasColumn($tbl, 'high_risk_at')) {
                    $table->timestamp('high_risk_at')->nullable()->after('high_risk_excerpt')->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tbl) {
            if (!Schema::hasTable($tbl)) continue;

            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (Schema::hasColumn($tbl, 'high_risk_at'))      $table->dropColumn('high_risk_at');
                if (Schema::hasColumn($tbl, 'high_risk_excerpt')) $table->dropColumn('high_risk_excerpt');
                if (Schema::hasColumn($tbl, 'high_risk_chat_id')) $table->dropColumn('high_risk_chat_id');
            });
        }
    }
};
