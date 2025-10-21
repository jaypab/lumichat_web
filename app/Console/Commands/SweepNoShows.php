<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SweepNoShows extends Command
{
    protected $signature = 'lumichat:sweep-no-shows {--grace=30} {--slot=60}';
    protected $description = 'Mark past pending/confirmed appointments as no_show after grace.';

    public function handle(): int
    {
        $grace = (int) $this->option('grace'); // minutes
        $slot  = (int) $this->option('slot');  // minutes
        $cutoff = now()->subMinutes($slot + $grace);

        $updated = DB::table('tbl_appointments')
            ->whereIn('status', ['pending','confirmed'])
            ->where('scheduled_at', '<=', $cutoff)
            ->update(['status' => 'no_show', 'updated_at' => now()]);

        $this->info("No-show sweep updated {$updated} row(s).");
        return Command::SUCCESS;
    }
}
