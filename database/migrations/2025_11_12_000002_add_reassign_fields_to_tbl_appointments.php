<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_appointments', function (Blueprint $t) {
            $t->unsignedTinyInteger('reassigned_count')->default(0)->after('status');
            $t->timestamp('last_reassigned_at')->nullable()->after('reassigned_count');
        });
    }
    public function down(): void {
        Schema::table('tbl_appointments', function (Blueprint $t) {
            $t->dropColumn(['reassigned_count','last_reassigned_at']);
        });
    }
};
