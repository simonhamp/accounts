<?php

use App\Filament\Pages\BatchImportInvoices;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Batch Import Invoices Page Access', function () {
    it('denies page access to panel admins who are not the app admin', function () {
        config(['app.admin_email' => 'superadmin@example.com']);

        $user = User::factory()->admin()->create(['email' => 'other-admin@example.com']);

        $this->actingAs($user)
            ->get(BatchImportInvoices::getUrl())
            ->assertForbidden();
    });

    it('allows access to the app admin', function () {
        config(['app.admin_email' => 'admin@example.com']);

        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->actingAs($user)
            ->get(BatchImportInvoices::getUrl())
            ->assertSuccessful();
    });

    it('denies access when admin email is not configured', function () {
        config(['app.admin_email' => null]);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(BatchImportInvoices::getUrl())
            ->assertForbidden();
    });

    it('is in the Settings navigation group', function () {
        expect(BatchImportInvoices::getNavigationGroup())->toBe('Settings');
    });
});
