<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // If the table already exists, don't try to recreate it.
        if (!Schema::hasTable('tbl_highrisk_reviews')) {
            Schema::create('tbl_highrisk_reviews', function (Blueprint $t) {
                $t->bigIncrements('id');

                // Source of the signal
                $t->unsignedBigInteger('chat_session_id')->nullable();
                $t->unsignedBigInteger('user_id')->nullable();            // student
                $t->timestamp('occurred_at')->nullable();

                // What triggered it (from mood tracker / keyword detector)
                $t->string('detected_word', 100)->nullable();
                $t->decimal('risk_score', 5, 2)->nullable();              // e.g., confidence
                $t->text('snippet')->nullable();                          // risky line(s)

                // (compat) free-text risk label, optional
                $t->string('risk_level', 50)->nullable();

                // Counselor review state (keep your original enum)
                $t->enum('review_status', ['pending','accepted','downgraded'])->default('pending');
                $t->unsignedBigInteger('reviewed_by')->nullable();        // counselor_id
                $t->timestamp('reviewed_at')->nullable();
                $t->text('review_notes')->nullable();

                $t->timestamps();

                // Indexes
                $t->index(['review_status', 'occurred_at']);
                $t->index('chat_session_id');
                $t->index('user_id');
            });

            // (Optional) FKs — uncomment if you want constraints
            // Schema::table('tbl_highrisk_reviews', function (Blueprint $t) {
            //     $t->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
            //     $t->foreign('user_id')->references('id')->on('tbl_users')->nullOnDelete();
            //     $t->foreign('reviewed_by')->references('id')->on('tbl_counselors')->nullOnDelete();
            // });
            return;
        }

        // If the table exists already, do a light-touch "merge":
        Schema::table('tbl_highrisk_reviews', function (Blueprint $t) {
            // Add risk_level if it's missing (harmless, nullable)
            if (!Schema::hasColumn('tbl_highrisk_reviews', 'risk_level')) {
                $t->string('risk_level', 50)->nullable()->after('snippet');
            }

            // Ensure our most-used composite index exists — if it errors it will be ignored below.
        });

        // Attempt to add the composite index only if it’s missing (Laravel can’t check easily; wrap in try/catch)
        try {
            // Some DBs require a named index to avoid duplicates across environments.
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX IF NOT EXISTS thr_status_occurred_idx ON tbl_highrisk_reviews (review_status, occurred_at)'
            );
        } catch (\Throwable $e) {
            // ignore if the platform doesn't support IF NOT EXISTS or the index already exists
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_highrisk_reviews');
    }
};
