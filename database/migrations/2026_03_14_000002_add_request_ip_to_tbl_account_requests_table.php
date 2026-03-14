<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tbl_account_requests') && !Schema::hasColumn('tbl_account_requests', 'request_ip')) {
            Schema::table('tbl_account_requests', function (Blueprint $table) {
                $table->string('request_ip', 45)->nullable()->after('attachment_path');
                $table->index('request_ip', 'tbl_account_requests_request_ip_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_account_requests') && Schema::hasColumn('tbl_account_requests', 'request_ip')) {
            Schema::table('tbl_account_requests', function (Blueprint $table) {
                $table->dropIndex('tbl_account_requests_request_ip_index');
                $table->dropColumn('request_ip');
            });
        }
    }
};
