<?php

namespace App\Console\Commands;

use App\Jobs\BackfillEurAmounts as BackfillEurAmountsJob;
use Illuminate\Console\Command;

class BackfillEurAmounts extends Command
{
    protected $signature = 'app:backfill-eur-amounts
                            {--force : Re-calculate EUR amounts even if they already exist}
                            {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Backfill EUR equivalent amounts for all invoices, bills, and other income records';

    public function handle(): int
    {
        $force = $this->option('force');
        $sync = $this->option('sync');

        if ($sync) {
            $this->info('Running backfill synchronously...');
            BackfillEurAmountsJob::dispatchSync($force);
            $this->info('Backfill completed. Check logs for details.');
        } else {
            BackfillEurAmountsJob::dispatch($force);
            $this->info('Backfill job has been dispatched to the queue.');
            $this->info('Monitor the queue worker for progress. Check logs for details when complete.');
        }

        return Command::SUCCESS;
    }
}
