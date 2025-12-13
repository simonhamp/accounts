<?php

use App\Models\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('BackupDatabase Command Pruning', function () {
    it('prunes backups older than retention period in prune-only mode', function () {
        Storage::fake('local');

        $oldBackup = DatabaseBackup::create([
            'filename' => 'old-backup.sqlite',
            'path' => 'backups/old-backup.sqlite',
            'size_bytes' => 1000,
            'invoices_count' => 5,
            'bills_count' => 3,
            'stripe_transactions_count' => 10,
            'other_incomes_count' => 2,
            'people_count' => 1,
            'bank_accounts_count' => 2,
        ]);
        $oldBackup->created_at = now()->subDays(31);
        $oldBackup->saveQuietly();

        Storage::disk('local')->put('backups/old-backup.sqlite', 'test');

        $this->artisan('db:backup', ['--prune-only' => true])
            ->assertSuccessful();

        expect(DatabaseBackup::find($oldBackup->id))->toBeNull();
        expect(Storage::disk('local')->exists('backups/old-backup.sqlite'))->toBeFalse();
    });

    it('keeps backups within retention period in prune-only mode', function () {
        Storage::fake('local');

        $recentBackup = DatabaseBackup::create([
            'filename' => 'recent-backup.sqlite',
            'path' => 'backups/recent-backup.sqlite',
            'size_bytes' => 1000,
            'invoices_count' => 5,
            'bills_count' => 3,
            'stripe_transactions_count' => 10,
            'other_incomes_count' => 2,
            'people_count' => 1,
            'bank_accounts_count' => 2,
        ]);
        $recentBackup->created_at = now()->subDays(15);
        $recentBackup->saveQuietly();

        Storage::disk('local')->put('backups/recent-backup.sqlite', 'test');

        $this->artisan('db:backup', ['--prune-only' => true])
            ->assertSuccessful();

        expect(DatabaseBackup::find($recentBackup->id))->not->toBeNull();
        expect(Storage::disk('local')->exists('backups/recent-backup.sqlite'))->toBeTrue();
    });

    it('accepts custom retention period in prune-only mode', function () {
        Storage::fake('local');

        $backup = DatabaseBackup::create([
            'filename' => 'backup.sqlite',
            'path' => 'backups/backup.sqlite',
            'size_bytes' => 1000,
            'invoices_count' => 0,
            'bills_count' => 0,
            'stripe_transactions_count' => 0,
            'other_incomes_count' => 0,
            'people_count' => 0,
            'bank_accounts_count' => 0,
        ]);
        $backup->created_at = now()->subDays(10);
        $backup->saveQuietly();

        Storage::disk('local')->put('backups/backup.sqlite', 'test');

        $this->artisan('db:backup', ['--prune-only' => true, '--retention' => 5])
            ->assertSuccessful();

        expect(DatabaseBackup::find($backup->id))->toBeNull();
    });

    it('reports when there are no old backups to prune', function () {
        $this->artisan('db:backup', ['--prune-only' => true])
            ->expectsOutput('No old backups to prune.')
            ->assertSuccessful();
    });
});

describe('BackupDatabase Command Creation', function () {
    it('fails gracefully when database file does not exist', function () {
        // In-memory database has no file path, so the command should fail
        $this->artisan('db:backup')
            ->expectsOutput('Database file not found.')
            ->assertFailed();
    });
});

describe('DatabaseBackup Model', function () {
    it('formats size correctly', function () {
        $backup = new DatabaseBackup(['size_bytes' => 1024 * 1024]);

        expect($backup->formatted_size)->toBe('1 MB');
    });

    it('returns full path', function () {
        $backup = new DatabaseBackup(['path' => 'backups/test.sqlite']);

        expect($backup->full_path)->toContain('backups/test.sqlite');
    });

    it('checks if file exists', function () {
        Storage::disk('local')->put('backups/exists.sqlite', 'test');

        $existingBackup = new DatabaseBackup(['path' => 'backups/exists.sqlite']);
        $missingBackup = new DatabaseBackup(['path' => 'backups/missing.sqlite']);

        expect($existingBackup->exists())->toBeTrue();
        expect($missingBackup->exists())->toBeFalse();
    });

    it('deletes file when model is deleted', function () {
        Storage::disk('local')->put('backups/to-delete.sqlite', 'test');

        $backup = DatabaseBackup::create([
            'filename' => 'to-delete.sqlite',
            'path' => 'backups/to-delete.sqlite',
            'size_bytes' => 1000,
            'invoices_count' => 0,
            'bills_count' => 0,
            'stripe_transactions_count' => 0,
            'other_incomes_count' => 0,
            'people_count' => 0,
            'bank_accounts_count' => 0,
        ]);

        expect(Storage::disk('local')->exists('backups/to-delete.sqlite'))->toBeTrue();

        $backup->delete();

        expect(Storage::disk('local')->exists('backups/to-delete.sqlite'))->toBeFalse();
    });
});
