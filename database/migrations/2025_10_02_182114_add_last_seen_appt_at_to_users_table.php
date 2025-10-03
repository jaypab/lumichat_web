<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_last_seen_appt_at_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Resolve table name from the model so this works whether you use `users` or `tbl_users`
        $table = (new \App\Models\User)->getTable();

        Schema::table($table, function (Blueprint $t) {
            if (!Schema::hasColumn($t->getTable(), 'last_seen_appt_at')) {
                $t->timestamp('last_seen_appt_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        $table = (new \App\Models\User)->getTable();
        Schema::table($table, function (Blueprint $t) {
            if (Schema::hasColumn($t->getTable(), 'last_seen_appt_at')) {
                $t->dropColumn('last_seen_appt_at');
            }
        });
    }
};
