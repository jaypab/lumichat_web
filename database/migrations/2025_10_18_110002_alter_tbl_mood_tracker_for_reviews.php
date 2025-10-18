<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tbl_mood_tracker', function (Blueprint $t) {
            if (!Schema::hasColumn('tbl_mood_tracker','snippet')) $t->text('snippet')->nullable()->after('keywords');
            if (!Schema::hasColumn('tbl_mood_tracker','risk_score')) $t->unsignedSmallInteger('risk_score')->default(0)->after('snippet');
            if (!Schema::hasColumn('tbl_mood_tracker','review_status')) $t->enum('review_status',['pending','accepted','downgraded'])->default('pending')->after('risk_score');
            if (!Schema::hasColumn('tbl_mood_tracker','review_notes')) $t->text('review_notes')->nullable()->after('review_status');
            if (!Schema::hasColumn('tbl_mood_tracker','reviewed_at')) $t->timestamp('reviewed_at')->nullable()->after('review_notes');
            if (!Schema::hasColumn('tbl_mood_tracker','counselor_id')) $t->unsignedBigInteger('counselor_id')->nullable()->index()->after('reviewed_at');
            if (!Schema::hasColumn('tbl_mood_tracker','message_id')) $t->string('message_id',64)->nullable()->after('counselor_id');

            // Optional FK:
            // $t->foreign('counselor_id')->references('id')->on('tbl_counselors')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::table('tbl_mood_tracker', function (Blueprint $t) {
            foreach (['snippet','risk_score','review_status','review_notes','reviewed_at','counselor_id','message_id'] as $col) {
                if (Schema::hasColumn('tbl_mood_tracker',$col)) $t->dropColumn($col);
            }
        });
    }
};
