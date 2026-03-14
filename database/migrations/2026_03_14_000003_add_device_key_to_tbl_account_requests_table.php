<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_account_requests') && !Schema::hasColumn('tbl_account_requests', 'device_key')) {
            Schema::table('tbl_account_requests', function (Blueprint $table) {
                $table->string('device_key', 120)->nullable()->after('request_ip');
                $table->index('device_key', 'tbl_account_requests_device_key_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_account_requests') && Schema::hasColumn('tbl_account_requests', 'device_key')) {
            Schema::table('tbl_account_requests', function (Blueprint $table) {
                $table->dropIndex('tbl_account_requests_device_key_index');
                $table->dropColumn('device_key');
            });
        }
    }
};
