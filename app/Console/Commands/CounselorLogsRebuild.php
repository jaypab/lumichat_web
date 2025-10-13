<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Repositories\Contracts\CounselorLogRepositoryInterface;

class CounselorLogsRebuild extends Command
{
    protected $signature = 'counselor-logs:rebuild {--counselor=} {--month=} {--year=}';
    protected $description = 'Rebuild tbl_counselor_logs (all or one counselor/month/year).';

    public function handle(CounselorLogRepositoryInterface $repo): int
    {
        $cid   = $this->option('counselor') ? (int)$this->option('counselor') : null;
        $month = $this->option('month') ? (int)$this->option('month') : null;
        $year  = $this->option('year')  ? (int)$this->option('year')  : null;

        if ($cid && $month && $year) {
            $repo->refreshMonth($cid, $month, $year);
            $this->info("OK: refreshed counselor_id={$cid} month={$month} year={$year}");
            return self::SUCCESS;
        }

        $n = $repo->backfillAllLogs();
        $this->info("OK: backfilled {$n} (counselor,month,year) group(s).");
        return self::SUCCESS;
    }
}
