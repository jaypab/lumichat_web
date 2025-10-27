<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Availability;

class PurgePastAvailability extends Command
{
    protected $signature = 'availability:purge-past';
    protected $description = 'Delete availability rows with date in the past';

    public function handle(): int
    {
        $count = Availability::whereNotNull('date')
            ->where('date', '<', now()->toDateString())
            ->delete();

        $this->info("Purged {$count} past availability rows.");
        return self::SUCCESS;
    }
}
