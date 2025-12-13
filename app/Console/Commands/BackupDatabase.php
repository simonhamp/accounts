<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\DatabaseBackup;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\StripeTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--retention=30 : Number of days to keep backups}
                            {--prune-only : Only prune old backups, do not create a new one}';

    protected $description = 'Create a backup of the SQLite database and prune old backups';

    public function handle(): int
    {
        $retentionDays = (int) $this->option('retention');

        if ($this->option('prune-only')) {
            $this->pruneOldBackups($retentionDays);

            return self::SUCCESS;
        }

        $this->info('Creating database backup...');

        $sourcePath = config('database.connections.sqlite.database');

        if (! file_exists($sourcePath)) {
            $this->error('Database file not found.');

            return self::FAILURE;
        }

        $filename = 'database-backup-'.now()->format('Y-m-d-His').'.sqlite';
        $backupPath = 'backups/'.$filename;

        Storage::disk('local')->makeDirectory('backups');

        if (! copy($sourcePath, Storage::disk('local')->path($backupPath))) {
            $this->error('Failed to copy database file.');

            return self::FAILURE;
        }

        $backup = DatabaseBackup::create([
            'filename' => $filename,
            'path' => $backupPath,
            'size_bytes' => filesize(Storage::disk('local')->path($backupPath)),
            'invoices_count' => Invoice::count(),
            'bills_count' => Bill::count(),
            'stripe_transactions_count' => StripeTransaction::count(),
            'other_incomes_count' => OtherIncome::count(),
            'people_count' => Person::count(),
            'bank_accounts_count' => BankAccount::count(),
        ]);

        $this->info("Backup created: {$backup->filename}");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Invoices', $backup->invoices_count],
                ['Bills', $backup->bills_count],
                ['Stripe Transactions', $backup->stripe_transactions_count],
                ['Other Income', $backup->other_incomes_count],
                ['People', $backup->people_count],
                ['Bank Accounts', $backup->bank_accounts_count],
            ]
        );

        $this->pruneOldBackups($retentionDays);

        return self::SUCCESS;
    }

    protected function pruneOldBackups(int $retentionDays): void
    {
        $cutoffDate = now()->subDays($retentionDays);

        $oldBackups = DatabaseBackup::where('created_at', '<', $cutoffDate)->get();

        if ($oldBackups->isEmpty()) {
            $this->info('No old backups to prune.');

            return;
        }

        $this->info("Pruning {$oldBackups->count()} backup(s) older than {$retentionDays} days...");

        foreach ($oldBackups as $backup) {
            $this->line("  Deleting: {$backup->filename}");
            $backup->delete();
        }

        $this->info('Old backups pruned.');
    }
}
