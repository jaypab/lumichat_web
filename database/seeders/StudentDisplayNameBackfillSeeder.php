<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StudentDisplayNameBackfillSeeder extends Seeder
{
    /**
     * Backfill tbl_users display-name fields used by reports.
     *
     * - Ensures `name` and/or `full_name` are filled with "First Last"
     * - Leaves existing proper values untouched
     * - Safe to run multiple times
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Detect which columns exist on your build
        $hasName      = $this->hasColumn('tbl_users', 'name');
        $hasFullName  = $this->hasColumn('tbl_users', 'full_name');
        $hasFirst     = $this->hasColumn('tbl_users', 'first_name');
        $hasLast      = $this->hasColumn('tbl_users', 'last_name');

        if (!$hasFirst && !$hasLast && !$hasName && !$hasFullName) {
            $this->command->warn('No compatible name columns found on tbl_users.');
            return;
        }

        $this->command->info('Backfilling student display names on tbl_users…');

        DB::table('tbl_users')
            ->where('role', 'student')
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($hasName, $hasFullName, $hasFirst, $hasLast, $now) {
                $updates = [];

                foreach ($rows as $u) {
                    // Compute "First Last" from what we have
                    $first = $hasFirst ? trim((string) ($u->first_name ?? '')) : '';
                    $last  = $hasLast  ? trim((string) ($u->last_name  ?? '')) : '';

                    // If we have neither first/last but have a one-piece name, try to reuse it
                    $fallback = '';
                    if (!$first && !$last) {
                        if ($hasName && !empty($u->name)) {
                            $fallback = trim((string) $u->name);
                        } elseif ($hasFullName && !empty($u->full_name)) {
                            $fallback = trim((string) $u->full_name);
                        }
                    }

                    $computed = trim($first . ' ' . $last);
                    if (!$computed && $fallback) {
                        $computed = $fallback;
                    }

                    // Normalize extra whitespace
                    $computed = preg_replace('/\s+/', ' ', $computed ?? '');
                    $computed = trim((string) $computed);

                    // Nothing we can compute → skip
                    if ($computed === '') {
                        continue;
                    }

                    $rowUpdate = [];

                    // Only set if empty/NULL or whitespace
                    if ($hasName && (!isset($u->name) || trim((string)$u->name) === '')) {
                        $rowUpdate['name'] = $computed;
                    }
                    if ($hasFullName && (!isset($u->full_name) || trim((string)$u->full_name) === '')) {
                        $rowUpdate['full_name'] = $computed;
                    }

                    if (!empty($rowUpdate)) {
                        $rowUpdate['updated_at'] = $now;
                        $updates[$u->id] = $rowUpdate;
                    }
                }

                // Bulk apply updates
                foreach ($updates as $id => $payload) {
                    DB::table('tbl_users')->where('id', $id)->update($payload);
                }

                $this->command->info('Updated '.count($updates).' student(s) in this chunk.');
            });

        $this->command->info('Done backfilling student display names.');
    }

    private function hasColumn(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
