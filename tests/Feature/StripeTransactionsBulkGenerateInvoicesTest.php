<?php

use App\Filament\Resources\StripeTransactions\Pages\ListStripeTransactions;
use App\Models\Invoice;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->admin()->create());
});

it('generates invoices for pending_review transactions alongside ready ones', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'MIX',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    $pending = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-01',
        'amount' => 3000,
        'status' => 'pending_review',
    ]);
    $ready = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    Livewire::test(ListStripeTransactions::class)
        ->callTableBulkAction('generate_invoices', [$pending->id, $ready->id])
        ->assertHasNoTableBulkActionErrors();

    expect(Invoice::count())->toBe(2);
    expect($pending->fresh()->isInvoiced())->toBeTrue();
    expect($ready->fresh()->isInvoiced())->toBeTrue();
});

it('processes bulk invoice generation oldest-first regardless of selection order', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'BULK',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    $later = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);
    $earlier = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-01',
        'amount' => 3000,
        'status' => 'ready',
    ]);

    Livewire::test(ListStripeTransactions::class)
        ->callTableBulkAction('generate_invoices', [$later->id, $earlier->id])
        ->assertHasNoTableBulkActionErrors();

    $invoices = Invoice::where('person_id', $person->id)
        ->orderBy('invoice_number')
        ->get();

    expect($invoices)->toHaveCount(2);
    expect($invoices[0]->invoice_number)->toBe('BULK-00001');
    expect($invoices[0]->invoice_date->format('Y-m-d'))->toBe('2026-04-01');
    expect($invoices[1]->invoice_number)->toBe('BULK-00002');
    expect($invoices[1]->invoice_date->format('Y-m-d'))->toBe('2026-04-15');
});

it('generates nothing when any selected transaction is ignored or already processed', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'NONE',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    $ready = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);
    $ignored = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-20',
        'amount' => 7000,
        'status' => 'ignored',
    ]);

    Livewire::test(ListStripeTransactions::class)
        ->callTableBulkAction('generate_invoices', [$ready->id, $ignored->id])
        ->assertHasNoTableBulkActionErrors();

    expect(Invoice::count())->toBe(0);
    expect($ready->fresh()->isInvoiced())->toBeFalse();
});

it('generates nothing when an earlier unprocessed transaction sits outside the selection', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'GAP',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-01',
        'amount' => 3000,
        'status' => 'pending_review',
    ]);
    $later = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    Livewire::test(ListStripeTransactions::class)
        ->callTableBulkAction('generate_invoices', [$later->id])
        ->assertHasNoTableBulkActionErrors();

    expect(Invoice::count())->toBe(0);
});

it('rolls back all invoices if any single generation fails', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'ATOM',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    // Pre-existing finalized invoice in the middle of the date range, so the
    // second selected transaction will trip assertDateOrdering and the whole
    // batch must roll back.
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_number' => 'ATOM-00001',
        'invoice_date' => '2026-04-10',
    ]);
    $person->update(['next_invoice_number' => 2]);

    $first = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 3000,
        'status' => 'ready',
    ]);
    $second = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-05',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    Livewire::test(ListStripeTransactions::class)
        ->callTableBulkAction('generate_invoices', [$first->id, $second->id])
        ->assertHasNoTableBulkActionErrors();

    expect(Invoice::where('person_id', $person->id)->count())->toBe(1);
    expect($first->fresh()->isInvoiced())->toBeFalse();
    expect($second->fresh()->isInvoiced())->toBeFalse();
});
