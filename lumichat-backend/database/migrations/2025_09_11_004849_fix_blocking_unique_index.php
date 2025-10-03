<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private function indexExists(string $name): bool
    {
        $sql = "SELECT 1 FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name   = 'tbl_appointments'
                  AND index_name   = ?";
        return !empty(DB::select($sql, [$name]));
    }

    private function columnExists(string $name): bool
    {
        $sql = "SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name   = 'tbl_appointments'
                  AND column_name  = ?";
        return !empty(DB::select($sql, [$name]));
    }

    public function up(): void
    {
        // Drop old unique index(es)
        foreach (['uniq_counselor_datetime_blocking', 'uniq_counselor_datetime'] as $idx) {
            if ($this->indexExists($idx)) {
                DB::statement("ALTER TABLE tbl_appointments DROP INDEX {$idx}");
            }
        }

        // Remove legacy is_blocking column if present
        if ($this->columnExists('is_blocking')) {
            DB::statement("ALTER TABLE tbl_appointments DROP COLUMN is_blocking");
        }

        // New generated column: only set for blocking statuses
        if (!$this->columnExists('blocking_at')) {
            DB::statement("
                ALTER TABLE tbl_appointments
                ADD COLUMN blocking_at DATETIME
                AS (CASE
                        WHEN status IN ('pending','confirmed','completed')
                        THEN scheduled_at
                        ELSE NULL
                    END) STORED
            ");
        }

        // Unique only for blocking rows (NULLs allowed for canceled etc.)
        if (!$this->indexExists('uniq_counselor_blocking_at')) {
            DB::statement("
                ALTER TABLE tbl_appointments
                ADD UNIQUE INDEX uniq_counselor_blocking_at (counselor_id, blocking_at)
            ");
        }
    }

    public function down(): void
    {
        if ($this->indexExists('uniq_counselor_blocking_at')) {
            DB::statement("ALTER TABLE tbl_appointments DROP INDEX uniq_counselor_blocking_at");
        }
        if ($this->columnExists('blocking_at')) {
            DB::statement("ALTER TABLE tbl_appointments DROP COLUMN blocking_at");
        }

        // Recreate legacy scheme if needed
        if (!$this->columnExists('is_blocking')) {
            DB::statement("
                ALTER TABLE tbl_appointments
                ADD COLUMN is_blocking TINYINT(1)
                AS (CASE
                        WHEN status IN ('pending','confirmed','completed') THEN 1
                        ELSE 0
                    END) STORED
            ");
        }
        if (!$this->indexExists('uniq_counselor_datetime_blocking')) {
            DB::statement("
                ALTER TABLE tbl_appointments
                ADD UNIQUE INDEX uniq_counselor_datetime_blocking
                (counselor_id, scheduled_at, is_blocking)
            ");
        }
    }
};
