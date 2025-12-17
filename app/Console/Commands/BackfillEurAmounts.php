<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Services\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillEurAmounts extends Command
{
    protected $signature = 'app:backfill-eur-amounts
                            {--dry-run : Show what would be done without making changes}
                            {--force : Re-fetch rates even if they exist}';

    protected $description = 'Backfill EUR equivalent amounts for all invoices, bills, and other income records';

    public function handle(ExchangeRateService $exchangeRateService): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('Running in dry-run mode. No changes will be made.');
        }

        // Step 1: Gather all unique date-currency combinations that need rates
        $this->info('Gathering date-currency combinations...');
        $combinations = $this->gatherDateCurrencyCombinations();

        $this->info(sprintf('Found %d unique date-currency combinations', count($combinations)));

        // Step 2: Fetch missing rates from ECB
        $this->info('Fetching exchange rates from ECB...');
        $this->fetchMissingRates($exchangeRateService, $combinations, $dryRun, $force);

        // Step 3: Update records with EUR amounts
        $this->info('Updating records with EUR amounts...');
        $this->updateRecords($exchangeRateService, $dryRun);

        $this->info('Backfill completed.');

        return Command::SUCCESS;
    }

    /**
     * Gather all unique date-currency combinations from all record types.
     *
     * @return array<array{date: string, currency: string}>
     */
    private function gatherDateCurrencyCombinations(): array
    {
        $combinations = collect();

        // Invoices
        Invoice::query()
            ->whereNotNull('invoice_date')
            ->whereNotNull('currency')
            ->where('currency', '!=', 'EUR')
            ->select('invoice_date', 'currency')
            ->distinct()
            ->get()
            ->each(function ($invoice) use ($combinations) {
                $combinations->push([
                    'date' => $invoice->invoice_date->toDateString(),
                    'currency' => $invoice->currency,
                ]);
            });

        // Bills
        Bill::query()
            ->whereNotNull('bill_date')
            ->whereNotNull('currency')
            ->where('currency', '!=', 'EUR')
            ->select('bill_date', 'currency')
            ->distinct()
            ->get()
            ->each(function ($bill) use ($combinations) {
                $combinations->push([
                    'date' => $bill->bill_date->toDateString(),
                    'currency' => $bill->currency,
                ]);
            });

        // Other Income
        OtherIncome::query()
            ->whereNotNull('income_date')
            ->whereNotNull('currency')
            ->where('currency', '!=', 'EUR')
            ->select('income_date', 'currency')
            ->distinct()
            ->get()
            ->each(function ($income) use ($combinations) {
                $combinations->push([
                    'date' => $income->income_date->toDateString(),
                    'currency' => $income->currency,
                ]);
            });

        // Return unique combinations
        return $combinations->unique(function ($item) {
            return $item['date'].'_'.$item['currency'];
        })->values()->toArray();
    }

    /**
     * Fetch missing exchange rates from ECB.
     */
    private function fetchMissingRates(
        ExchangeRateService $exchangeRateService,
        array $combinations,
        bool $dryRun,
        bool $force
    ): void {
        $bar = $this->output->createProgressBar(count($combinations));
        $bar->start();

        $fetched = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($combinations as $combo) {
            $date = Carbon::parse($combo['date']);
            $currency = $combo['currency'];

            // Check if rate already exists (unless force mode)
            if (! $force && $exchangeRateService->hasRateFor($currency, $date)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            if ($dryRun) {
                $this->line("\n  Would fetch rate for {$currency} on {$date->toDateString()}");
                $bar->advance();

                continue;
            }

            // Try to fetch from ECB
            $rate = $exchangeRateService->fetchFromEcb($currency, $date);

            if ($rate !== null) {
                $fetched++;
            } else {
                // Try to find a fallback rate from a previous date
                $existingRate = ExchangeRate::latestForCurrency($date, $currency)->first();

                if ($existingRate) {
                    $this->line("\n  Using fallback rate from {$existingRate->date->toDateString()} for {$currency} on {$date->toDateString()}");
                } else {
                    $failed++;
                    $this->warn("\n  No rate found for {$currency} on {$date->toDateString()}");
                }
            }

            $bar->advance();

            // Brief pause to avoid overwhelming the ECB API
            usleep(100000); // 100ms
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Rates fetched: {$fetched}, Skipped (already exist): {$skipped}, Failed: {$failed}");
    }

    /**
     * Update all records with their EUR equivalent amounts.
     */
    private function updateRecords(ExchangeRateService $exchangeRateService, bool $dryRun): void
    {
        $updated = 0;
        $skipped = 0;

        // Update Invoices
        $this->info('Updating invoices...');
        Invoice::query()
            ->whereNotNull('invoice_date')
            ->whereNotNull('currency')
            ->whereNotNull('total_amount')
            ->whereNull('amount_eur')
            ->chunk(100, function ($invoices) use ($exchangeRateService, $dryRun, &$updated, &$skipped) {
                foreach ($invoices as $invoice) {
                    $amountEur = $exchangeRateService->convertToEur(
                        $invoice->total_amount,
                        $invoice->currency,
                        $invoice->invoice_date
                    );

                    if ($amountEur !== null) {
                        if (! $dryRun) {
                            $invoice->update(['amount_eur' => $amountEur]);
                        }
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("  Invoices - Updated: {$updated}, Skipped: {$skipped}");

        // Update Bills
        $updated = 0;
        $skipped = 0;
        $this->info('Updating bills...');
        Bill::query()
            ->whereNotNull('bill_date')
            ->whereNotNull('currency')
            ->whereNotNull('total_amount')
            ->whereNull('amount_eur')
            ->chunk(100, function ($bills) use ($exchangeRateService, $dryRun, &$updated, &$skipped) {
                foreach ($bills as $bill) {
                    $amountEur = $exchangeRateService->convertToEur(
                        $bill->total_amount,
                        $bill->currency,
                        $bill->bill_date
                    );

                    if ($amountEur !== null) {
                        if (! $dryRun) {
                            $bill->update(['amount_eur' => $amountEur]);
                        }
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("  Bills - Updated: {$updated}, Skipped: {$skipped}");

        // Update Other Income
        $updated = 0;
        $skipped = 0;
        $this->info('Updating other income...');
        OtherIncome::query()
            ->whereNotNull('income_date')
            ->whereNotNull('currency')
            ->whereNotNull('amount')
            ->whereNull('amount_eur')
            ->chunk(100, function ($incomes) use ($exchangeRateService, $dryRun, &$updated, &$skipped) {
                foreach ($incomes as $income) {
                    $amountEur = $exchangeRateService->convertToEur(
                        $income->amount,
                        $income->currency,
                        $income->income_date
                    );

                    if ($amountEur !== null) {
                        if (! $dryRun) {
                            $income->update(['amount_eur' => $amountEur]);
                        }
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("  Other Income - Updated: {$updated}, Skipped: {$skipped}");
    }
}
