<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $table = 'tbl_counselor_logs';

    public function up(): void
    {
        // 1) Create table if missing
        if (! Schema::hasTable($this->table)) {
            Schema::create($this->table, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('counselor_id');
                $t->unsignedTinyInteger('month');        // 1..12
                $t->unsignedSmallInteger('year');        // e.g., 2025
                $t->unsignedInteger('students_count')->default(0);

                // new canonical columns
                $t->text('students_list')->nullable();   // long text list (pipe-separated)
                $t->json('common_dx')->nullable();       // JSON array of top diagnoses
                $t->timestamp('generated_at')->nullable();

                $t->timestamps();

                // fast lookups / uniqueness per period
                $t->unique(['counselor_id','month','year'], 'ucl_period');
            });

            return; // nothing else to do if we just created it
        }

        // 2) Table exists: add any missing columns safely
        Schema::table($this->table, function (Blueprint $t) {
            if (! Schema::hasColumn($this->table, 'students_list')) {
                $t->text('students_list')->nullable()->after('students_count');
            }
            if (! Schema::hasColumn($this->table, 'common_dx')) {
                $t->json('common_dx')->nullable()->after('students_list');
            }
            if (! Schema::hasColumn($this->table, 'generated_at')) {
                $t->timestamp('generated_at')->nullable()->after('common_dx');
            }
        });

        // 3) Migrate data from old column names (no doctrine/dbal needed)
        // students_sample  => students_list
        if (Schema::hasColumn($this->table, 'students_sample') && Schema::hasColumn($this->table, 'students_list')) {
            DB::statement("UPDATE {$this->table} SET students_list = COALESCE(students_list, students_sample) WHERE (students_list IS NULL OR students_list = '') AND students_sample IS NOT NULL");
            // drop old col
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropColumn('students_sample');
            });
        }

        // common_diagnoses => common_dx
        if (Schema::hasColumn($this->table, 'common_diagnoses') && Schema::hasColumn($this->table, 'common_dx')) {
            // If old column was TEXT, try to parse; otherwise copy as-is
            // MySQL will coerce valid JSON text to JSON column.
            DB::statement("UPDATE {$this->table} SET common_dx = COALESCE(common_dx, common_diagnoses) WHERE common_dx IS NULL AND common_diagnoses IS NOT NULL");
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropColumn('common_diagnoses');
            });
        }

        // 4) Ensure unique index exists (some older tables may not have it)
        $this->ensureUniqueIndex();
    }

    private function ensureUniqueIndex(): void
    {
        // Add the unique index if it's missing
        $indexName = 'ucl_period';
        $hasIndex = false;

        // Portable check (works in shared hosts): try to create; if it exists, MySQL will error gracefully in try/catch
        try {
            Schema::table($this->table, function (Blueprint $t) use ($indexName) {
                $t->unique(['counselor_id','month','year'], $indexName);
            });
        } catch (\Throwable $e) {
            // ignore if already exists
        }
    }

    public function down(): void
    {
        // Be conservative on down: just drop the table (only if you really need to roll back)
        // Comment this out if you prefer keeping the table.
        // Schema::dropIfExists($this->table);
    }
};
