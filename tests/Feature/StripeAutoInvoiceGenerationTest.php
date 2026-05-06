<?php

use App\Models\Invoice;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Services\InvoiceService;

it('generates one invoice per ready transaction in date order', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'AUT',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'description' => 'Later transaction',
        'amount' => 5000,
        'status' => 'ready',
    ]);
    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-01',
        'description' => 'Earlier transaction',
        'amount' => 3000,
        'status' => 'ready',
    ]);

    $result = app(InvoiceService::class)->generateInvoicesForReadyTransactions($account);

    expect($result['generated'])->toBe(2);
    expect($result['failed'])->toBe(0);

    $invoices = Invoice::where('person_id', $person->id)->orderBy('invoice_number')->get();

    expect($invoices)->toHaveCount(2);
    expect($invoices[0]->invoice_number)->toBe('AUT-00001');
    expect($invoices[0]->invoice_date->format('Y-m-d'))->toBe('2026-04-01');
    expect($invoices[1]->invoice_number)->toBe('AUT-00002');
    expect($invoices[1]->invoice_date->format('Y-m-d'))->toBe('2026-04-15');
});

it('skips transactions that are not ready or already invoiced', function () {
    $person = Person::factory()->create();
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'status' => 'pending_review',
    ]);
    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'status' => 'ignored',
    ]);

    $result = app(InvoiceService::class)->generateInvoicesForReadyTransactions($account);

    expect($result['generated'])->toBe(0);
    expect(Invoice::where('person_id', $person->id)->count())->toBe(0);
});

it('records failures without aborting subsequent transactions', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'FAIL',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    // A pre-existing invoice dated AFTER the next transaction would force
    // a date-ordering failure on the first auto-generated invoice.
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_number' => 'FAIL-00001',
        'invoice_date' => '2026-12-31',
    ]);
    $person->update(['next_invoice_number' => 2]);

    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-01-15',
        'description' => 'Backdated, should fail',
        'amount' => 5000,
        'status' => 'ready',
    ]);
    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2027-02-01',
        'description' => 'Forward-dated, should succeed',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    $result = app(InvoiceService::class)->generateInvoicesForReadyTransactions($account);

    expect($result['failed'])->toBe(1);
    expect($result['generated'])->toBe(1);
    expect(Invoice::where('person_id', $person->id)->count())->toBe(2);
});
