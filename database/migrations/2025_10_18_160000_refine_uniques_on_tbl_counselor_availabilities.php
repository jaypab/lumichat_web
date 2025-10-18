<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Make sure a plain index on counselor_id exists
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            // if you already added it, this will be ignored by MySQL
            $t->index('counselor_id', 'idx_counselor_id');
        });

        // 2) Temporarily drop the FK that references tbl_counselors(id)
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            // drop by column (Laravel figures out the actual name)
            $t->dropForeign(['counselor_id']);
        });

        // 3) Drop the old composite unique that's blocking us
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            // old name from your earlier migration
            $t->dropUnique('uniq_slot'); // (counselor_id, weekday, start_time, end_time)
        });

        // 4) Re-add the FK cleanly
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            $t->foreign('counselor_id')
              ->references('id')->on('tbl_counselors')
              ->cascadeOnDelete();
        });

        // 5) Add the refined unique keys
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            // recurring (no date)
            $t->unique(
                ['counselor_id', 'weekday', 'start_time', 'end_time'],
                'uniq_recurring_slot'
            );
            // one-off dated
            $t->unique(
                ['counselor_id', 'date', 'start_time', 'end_time'],
                'uniq_dated_slot'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tbl_counselor_availabilities', function (Blueprint $t) {
            $t->dropUnique('uniq_recurring_slot');
            $t->dropUnique('uniq_dated_slot');

            // drop FK to restore the original unique
            $t->dropForeign(['counselor_id']);

            $t->unique(
                ['counselor_id', 'weekday', 'start_time', 'end_time'],
                'uniq_slot'
            );

            // re-add FK
            $t->foreign('counselor_id')
              ->references('id')->on('tbl_counselors')
              ->cascadeOnDelete();
        });
    }
};
