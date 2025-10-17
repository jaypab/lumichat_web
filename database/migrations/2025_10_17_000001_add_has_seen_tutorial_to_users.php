<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_registration', function (Blueprint $table) {
            // If your table actually *does* have remember_token in some env, keep the order.
            if (Schema::hasColumn('tbl_registration', 'remember_token')) {
                $table->boolean('has_seen_tutorial')->default(false)->after('remember_token');
            } else {
                $table->boolean('has_seen_tutorial')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_registration', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_registration', 'has_seen_tutorial')) {
                $table->dropColumn('has_seen_tutorial');
            }
        });
    }
};
