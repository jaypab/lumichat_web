<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tbl_highrisk_reviews', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Source of the signal
            $t->unsignedBigInteger('chat_session_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();          // student
            $t->timestamp('occurred_at')->nullable();

            // What triggered it (from mood tracker / keyword detector)
            $t->string('detected_word', 100)->nullable();
            $t->decimal('risk_score', 5, 2)->nullable();            // e.g., confidence
            $t->text('snippet')->nullable();                        // only the risky line(s)

            // Counselor review state
            $t->enum('review_status', ['pending','accepted','downgraded'])->default('pending');
            $t->unsignedBigInteger('reviewed_by')->nullable();      // counselor_id
            $t->timestamp('reviewed_at')->nullable();
            $t->text('review_notes')->nullable();

            $t->timestamps();

            // Indexes
            $t->index(['review_status', 'occurred_at']);
            $t->index('chat_session_id');
            $t->index('user_id');
        });

        // Optional: add FKs if you want (comment out if you prefer no constraints)
        // Schema::table('tbl_highrisk_reviews', function (Blueprint $t) {
        //     $t->foreign('chat_session_id')->references('id')->on('chat_sessions')->cascadeOnDelete();
        //     $t->foreign('user_id')->references('id')->on('tbl_users')->nullOnDelete();
        //     $t->foreign('reviewed_by')->references('id')->on('tbl_counselors')->nullOnDelete();
        // });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_highrisk_reviews');
    }
};
