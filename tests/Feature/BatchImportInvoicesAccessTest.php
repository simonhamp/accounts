<?php

use App\Filament\Pages\BatchImportInvoices;
use App\Filament\Pages\ImportInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Invoice Import Pages', function () {
    it('denies access to BatchImportInvoices for everyone', function () {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);
        config(['app.admin_email' => 'admin@example.com']);

        $this->actingAs($user)
            ->get(BatchImportInvoices::getUrl())
            ->assertForbidden();
    });

    it('denies access to ImportInvoice for everyone', function () {
        $user = User::factory()->admin()->create(['email' => 'admin@example.com']);
        config(['app.admin_email' => 'admin@example.com']);

        $this->actingAs($user)
            ->get(ImportInvoice::getUrl())
            ->assertForbidden();
    });

    it('does not register BatchImportInvoices in navigation', function () {
        expect(BatchImportInvoices::shouldRegisterNavigation())->toBeFalse();
    });

    it('does not register ImportInvoice in navigation', function () {
        expect(ImportInvoice::shouldRegisterNavigation())->toBeFalse();
    });
});
