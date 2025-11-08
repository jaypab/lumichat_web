<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->unsignedInteger('tos_version')->default(0)->after('remember_token');
            $table->timestamp('tos_accepted_at')->nullable()->after('tos_version');
            $table->string('tos_ip', 45)->nullable()->after('tos_accepted_at');
            $table->string('tos_user_agent', 255)->nullable()->after('tos_ip');
        });
    }
    public function down(): void {
        Schema::table('tbl_users', function (Blueprint $table) {
            $table->dropColumn(['tos_version','tos_accepted_at','tos_ip','tos_user_agent']);
        });
    }
};