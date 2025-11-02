<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private string $table = 'tbl_counselor_availabilities';

    public function up(): void
    {
        if (!Schema::hasTable($this->table)) return;

        // Drop previous uniques safely if present
        foreach (['uniq_recurring_slot','uniq_dated_slot',
                  'tbl_counselor_availabilities_weekday_start_time_end_time_unique',
                  'tbl_counselor_availabilities_date_start_time_end_time_unique',
                  'uniq_slot'] as $idx) {
            try {
                DB::statement("ALTER TABLE `{$this->table}` DROP INDEX `{$idx}`");
            } catch (\Throwable $e) { /* ignore if missing */ }
        }

        Schema::table($this->table, function (Blueprint $t) {
            // Recurring (date is NULL logically) — include slot_type to avoid clashes
            $t->unique(
                ['counselor_id','weekday','start_time','end_time','slot_type'],
                'uniq_recurring_slot'
            );

            // Dated rows — also include slot_type
            $t->unique(
                ['counselor_id','date','start_time','end_time','slot_type'],
                'uniq_dated_slot'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) return;

        Schema::table($this->table, function (Blueprint $t) {
            $t->dropUnique('uniq_recurring_slot');
            $t->dropUnique('uniq_dated_slot');

            // (Optional) restore older 4-col uniques if you really want to roll back
            $t->unique(['counselor_id','weekday','start_time','end_time'], 'uniq_recurring_slot');
            $t->unique(['counselor_id','date','start_time','end_time'],    'uniq_dated_slot');
        });
    }
};
