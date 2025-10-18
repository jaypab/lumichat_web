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

        // add a plain index on counselor_id ONLY if it doesn't exist
        if (!$this->indexExists($this->table, 'idx_counselor_id')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->index('counselor_id', 'idx_counselor_id');
            });
        }

        // drop FK by column (ignore if already gone)
        try {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropForeign(['counselor_id']);
            });
        } catch (\Throwable $e) {}

        // drop the old unique only if present
        if ($this->indexExists($this->table, 'uniq_slot')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropUnique('uniq_slot');
            });
        }

        // re-add FK
        Schema::table($this->table, function (Blueprint $t) {
            $t->foreign('counselor_id')
              ->references('id')->on('tbl_counselors')
              ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable($this->table)) return;

        // drop FK to allow restoring old unique safely
        try {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropForeign(['counselor_id']);
            });
        } catch (\Throwable $e) {}

        // restore original unique if missing
        if (!$this->indexExists($this->table, 'uniq_slot')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->unique(['counselor_id','weekday','start_time','end_time'], 'uniq_slot');
            });
        }

        // (optional) drop the plain index only if it exists
        if ($this->indexExists($this->table, 'idx_counselor_id')) {
            Schema::table($this->table, function (Blueprint $t) {
                $t->dropIndex('idx_counselor_id');
            });
        }

        // re-add FK
        Schema::table($this->table, function (Blueprint $t) {
            $t->foreign('counselor_id')
              ->references('id')->on('tbl_counselors')
              ->cascadeOnDelete();
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $db = DB::getDatabaseName();
        return DB::table('information_schema.statistics')
            ->where('table_schema', $db)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
