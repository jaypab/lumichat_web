<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database in a safe, dependency-aware order.
     */
    public function run(): void
    {
        $this->fkOff();

        $this->section('Core accounts & people');
        $this->safeCall(\Database\Seeders\MasterAdminSeeder::class);
        $this->safeCall(\Database\Seeders\BulkStudentSeeder::class);
        $this->safeCall(\Database\Seeders\StudentDisplayNameBackfillSeeder::class);
        $this->safeCallIfExists('StudentRegistrationBackfillSeeder');

        $this->section('Counselors & schedules');
        $this->safeCall(\Database\Seeders\CounselorSeeder::class);

        $this->section('Conversations & appointments');
        $this->safeCall(\Database\Seeders\ChatSessionSeeder::class);
        $this->safeCall(\Database\Seeders\AppointmentSeeder::class);

        $this->section('Diagnosis reports');
        $this->safeCall(\Database\Seeders\DiagnosisReportSeeder::class);
        $this->safeCallIfExists('DiagnosisReportsBackfillSeeder');

        $this->section('Aggregations / analytics');
        $this->safeCallIfExists('CounselorLogsSeeder');
        $this->safeCallIfExists('CourseAnalyticsBackfillSeeder');

        $this->fkOn();
        $this->log('info', '✅ Database seeding complete.');
    }

    /* ------------------------- helpers ------------------------- */

    /** Run a seeder class if it exists and report status. */
    private function safeCall(string $seederClass): void
    {
        if (!class_exists($seederClass)) {
            $this->log('warn', "• Skipped: {$seederClass} (class not found)");
            return;
        }

        try {
            $this->call($seederClass);
        } catch (\Throwable $e) {
            $this->log('error', "✖ {$seederClass} failed: " . $e->getMessage());
            throw $e; // bubble up for real issues
        }
    }

    /** Convenience wrapper when you only know the short/seeder name. */
    private function safeCallIfExists(string $shortName): void
    {
        $fqcn = __NAMESPACE__ . '\\' . $shortName;
        $this->safeCall($fqcn);
    }

    /** Pretty section header (only when running via Artisan). */
    private function section(string $label): void
    {
        $this->log('line', ''); // blank line
        $this->log('info', "── {$label} ─────────────────────────────────────────");
    }

    /** Generic logger that works only when $this->command is available. */
    private function log(string $method, string $message): void
    {
        // $this->command is set when running through Artisan; null otherwise (e.g., tests)
        if ($this->command) {
            switch ($method) {
                case 'info':  $this->command->info($message);  break;
                case 'warn':  $this->command->warn($message);  break;
                case 'error': $this->command->error($message); break;
                default:      $this->command->line($message);  break;
            }
        }
    }

    /** Disable FK checks when supported (MySQL/MariaDB). */
    private function fkOff(): void
    {
        try {
            if ($this->isMySql()) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    /** Re-enable FK checks. */
    private function fkOn(): void
    {
        try {
            if ($this->isMySql()) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    private function isMySql(): bool
    {
        // Safe check: only try to read driver name when DB is configured
        try {
            return DB::getDriverName() === 'mysql';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
