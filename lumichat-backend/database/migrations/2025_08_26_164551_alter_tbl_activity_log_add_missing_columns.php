<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Table exists already; add any missing columns one-by-one
        Schema::table('tbl_activity_log', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_activity_log', 'event')) {
                $table->string('event', 100)->after('id');
            }

            if (!Schema::hasColumn('tbl_activity_log', 'description')) {
                $table->text('description')->nullable()->after('event');
            }

            if (!Schema::hasColumn('tbl_activity_log', 'actor_id')) {
                $table->unsignedBigInteger('actor_id')->nullable()->after('description');
            }

            if (!Schema::hasColumn('tbl_activity_log', 'subject_type')) {
                $table->string('subject_type', 150)->nullable()->after('actor_id');
            }

            if (!Schema::hasColumn('tbl_activity_log', 'subject_id')) {
                $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            }

            // Use JSON if available; fallback to TEXT if not supported by your MySQL
            if (!Schema::hasColumn('tbl_activity_log', 'meta')) {
                $table->json('meta')->nullable()->after('subject_id');
            }

            if (!Schema::hasColumn('tbl_activity_log', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
            if (!Schema::hasColumn('tbl_activity_log', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            }
        });
    }

    public function down(): void
    {
        // Only drop columns we added (safe no-ops if they weren't there)
        Schema::table('tbl_activity_log', function (Blueprint $table) {
            if (Schema::hasColumn('tbl_activity_log', 'description')) $table->dropColumn('description');
            if (Schema::hasColumn('tbl_activity_log', 'actor_id'))    $table->dropColumn('actor_id');
            if (Schema::hasColumn('tbl_activity_log', 'subject_type'))$table->dropColumn('subject_type');
            if (Schema::hasColumn('tbl_activity_log', 'subject_id'))  $table->dropColumn('subject_id');
            if (Schema::hasColumn('tbl_activity_log', 'meta'))        $table->dropColumn('meta');
            // Leave event/timestamps in place, since other code may rely on them.
        });
    }
};
