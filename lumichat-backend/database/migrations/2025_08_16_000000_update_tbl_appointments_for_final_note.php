<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $t) {
            if (!Schema::hasColumn('tbl_appointments', 'final_note')) {
                $t->longText('final_note')->nullable()->after('status');
            }
            if (!Schema::hasColumn('tbl_appointments', 'finalized_by')) {
                $t->unsignedBigInteger('finalized_by')->nullable()->after('final_note');
            }
            if (!Schema::hasColumn('tbl_appointments', 'finalized_at')) {
                $t->dateTime('finalized_at')->nullable()->after('finalized_by');
            }
            if (Schema::hasColumn('tbl_appointments', 'notes')) {
                $t->dropColumn('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbl_appointments', function (Blueprint $t) {
            if (Schema::hasColumn('tbl_appointments', 'final_note')) {
                $t->dropColumn('final_note');
            }
            if (Schema::hasColumn('tbl_appointments', 'finalized_by')) {
                $t->dropColumn('finalized_by');
            }
            if (Schema::hasColumn('tbl_appointments', 'finalized_at')) {
                $t->dropColumn('finalized_at');
            }
            // Optional: re-add legacy notes column
            // if (!Schema::hasColumn('tbl_appointments', 'notes')) {
            //     $t->longText('notes')->nullable();
            // }
        });
    }
};
