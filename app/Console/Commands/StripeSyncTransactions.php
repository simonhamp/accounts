<?php

namespace App\Console\Commands;

use App\Models\StripeAccount;
use App\Services\StripeImportService;
use Illuminate\Console\Command;

class StripeSyncTransactions extends Command
{
    protected $signature = 'stripe:sync-transactions
                            {--account= : Sync specific account ID}
                            {--year= : Sync transactions for specific year}
                            {--month= : Sync transactions for specific month (1-12)}';

    protected $description = 'Sync Stripe transactions from all configured accounts';

    public function handle(StripeImportService $importService): int
    {
        $accounts = $this->option('account')
            ? StripeAccount::query()->where('id', $this->option('account'))->get()
            : StripeAccount::all();

        if ($accounts->isEmpty()) {
            $this->error('No Stripe accounts found. Please add accounts in the admin panel.');

            return self::FAILURE;
        }

        $year = $this->option('year') ? (int) $this->option('year') : null;
        $month = $this->option('month') ? (int) $this->option('month') : null;

        if ($month && ! $year) {
            $this->error('--year must be provided when using --month.');

            return self::FAILURE;
        }

        if ($month && ($month < 1 || $month > 12)) {
            $this->error('Month must be between 1 and 12.');

            return self::FAILURE;
        }

        $periodInfo = $year && $month ? " for {$year}/{$month}" : ($year ? " for year {$year}" : '');
        $this->info("Syncing {$accounts->count()} Stripe account(s){$periodInfo}...");

        foreach ($accounts as $account) {
            $this->line("Syncing account: {$account->account_name}");

            try {
                $imported = $importService->syncAccount($account, $year, $month);

                $this->info("  ✓ Imported {$imported['payments']} payments");
                $this->info("  ✓ Imported {$imported['refunds']} refunds");
                $this->info("  ✓ Imported {$imported['disputes']} disputes");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('✓ All accounts synced successfully!');

        return self::SUCCESS;
    }
}
