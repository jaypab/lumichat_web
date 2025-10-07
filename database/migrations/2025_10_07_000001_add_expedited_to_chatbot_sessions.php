<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function resolveTable(): ?string
    {
        foreach (['tbl_chatbot_sessions', 'chatbot_sessions', 'tbl_chatbot_session'] as $name) {
            if (Schema::hasTable($name)) return $name;
        }
        return null;
    }

    public function up(): void
    {
        $table = $this->resolveTable();
        if (!$table) return;

        if (!Schema::hasColumn($table, 'expedited_appt_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('expedited_appt_id')->nullable()->after('risk_score');
            });
        }
        if (!Schema::hasColumn($table, 'expedited_at')) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('expedited_at')->nullable()->after('expedited_appt_id');
            });
        }
    }

    public function down(): void
    {
        $table = $this->resolveTable();
        if (!$table) return;

        if (Schema::hasColumn($table, 'expedited_at')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('expedited_at');
            });
        }
        if (Schema::hasColumn($table, 'expedited_appt_id')) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('expedited_appt_id');
            });
        }
    }
};
