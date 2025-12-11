<?php

use App\Filament\Pages\FailedJobs;
use App\Filament\Pages\ViewFailedJobException;
use App\Models\FailedJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Failed Jobs Page Access', function () {
    it('denies access to non-admin users', function () {
        config(['app.admin_email' => 'admin@example.com']);

        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->actingAs($user)
            ->get(FailedJobs::getUrl())
            ->assertForbidden();
    });

    it('allows access to admin user', function () {
        config(['app.admin_email' => 'admin@example.com']);

        $user = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($user)
            ->get(FailedJobs::getUrl())
            ->assertSuccessful();
    });

    it('denies access when admin email is not configured', function () {
        config(['app.admin_email' => null]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(FailedJobs::getUrl())
            ->assertForbidden();
    });
});

describe('Failed Jobs Page Content', function () {
    beforeEach(function () {
        config(['app.admin_email' => 'admin@example.com']);
        $this->admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($this->admin);
    });

    it('displays failed jobs in the table', function () {
        FailedJob::create([
            'uuid' => 'test-uuid-123',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\ProcessInvoiceImport']),
            'exception' => 'Error: Test exception message',
            'failed_at' => now(),
        ]);

        Livewire::test(FailedJobs::class)
            ->assertSuccessful()
            ->assertSee('default')
            ->assertSee('Error: Test exception message');
    });

    it('shows empty state when no failed jobs exist', function () {
        Livewire::test(FailedJobs::class)
            ->assertSuccessful()
            ->assertSee('No failed jobs');
    });
});

describe('FailedJob Model', function () {
    it('extracts job name from payload', function () {
        $job = new FailedJob([
            'payload' => ['displayName' => 'App\\Jobs\\ProcessInvoiceImport'],
        ]);

        expect($job->getJobName())->toBe('ProcessInvoiceImport');
    });

    it('returns unknown job when displayName is missing', function () {
        $job = new FailedJob([
            'payload' => [],
        ]);

        expect($job->getJobName())->toBe('Unknown Job');
    });

    it('extracts short exception from full trace', function () {
        $exception = "Error: Something went wrong\nStack trace:\n#0 file.php";
        $job = new FailedJob([
            'exception' => $exception,
        ]);

        expect($job->getShortException())->toBe('Error: Something went wrong');
    });
});

describe('Failed Jobs Navigation Badge', function () {
    it('shows badge count when failed jobs exist', function () {
        FailedJob::create([
            'uuid' => 'test-uuid-1',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([]),
            'exception' => 'Error',
            'failed_at' => now(),
        ]);

        FailedJob::create([
            'uuid' => 'test-uuid-2',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([]),
            'exception' => 'Error',
            'failed_at' => now(),
        ]);

        expect(FailedJobs::getNavigationBadge())->toBe('2');
    });

    it('returns null badge when no failed jobs', function () {
        expect(FailedJobs::getNavigationBadge())->toBeNull();
    });
});

describe('View Failed Job Exception Page', function () {
    it('displays exception details for admin user', function () {
        config(['app.admin_email' => 'admin@example.com']);
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $failedJob = FailedJob::create([
            'uuid' => 'test-uuid-view',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\TestJob']),
            'exception' => "Error: Something went wrong\nStack trace here",
            'failed_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ViewFailedJobException::class, ['record' => $failedJob->id])
            ->assertSuccessful()
            ->assertSee('test-uuid-view')
            ->assertSee('Error: Something went wrong')
            ->assertSee('Job Information');
    });

    it('denies access to non-admin users', function () {
        config(['app.admin_email' => 'admin@example.com']);
        $user = User::factory()->create(['email' => 'user@example.com']);

        $failedJob = FailedJob::create([
            'uuid' => 'test-uuid-deny',
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([]),
            'exception' => 'Error',
            'failed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(ViewFailedJobException::getUrl(['record' => $failedJob->id]))
            ->assertForbidden();
    });
});
