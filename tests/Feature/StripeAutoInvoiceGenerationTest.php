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

it('halts later transactions once an earlier one fails to invoice', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'FAIL',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    // A pre-existing invoice dated AFTER the first transaction forces
    // assertDateOrdering to throw on the backdated one.
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
        'description' => 'Blocked because the earlier one is still unprocessed',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    $result = app(InvoiceService::class)->generateInvoicesForReadyTransactions($account);

    expect($result['failed'])->toBe(2);
    expect($result['generated'])->toBe(0);
    expect(Invoice::where('person_id', $person->id)->count())->toBe(1);
});

it('blocks invoice generation when an earlier transaction is pending review', function () {
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
    $ready = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    expect(fn () => app(InvoiceService::class)->generateInvoiceForTransaction($ready))
        ->toThrow(\Exception::class, 'earlier transaction');

    expect(Invoice::count())->toBe(0);
});

it('allows invoice generation when the earlier transaction is ignored', function () {
    $person = Person::factory()->create([
        'invoice_prefix' => 'OK',
        'next_invoice_number' => 1,
    ]);
    $account = StripeAccount::factory()->create(['person_id' => $person->id]);

    StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-01',
        'amount' => 3000,
        'status' => 'ignored',
    ]);
    $ready = StripeTransaction::factory()->create([
        'stripe_account_id' => $account->id,
        'transaction_date' => '2026-04-15',
        'amount' => 5000,
        'status' => 'ready',
    ]);

    $invoice = app(InvoiceService::class)->generateInvoiceForTransaction($ready);

    expect($invoice->invoice_number)->toBe('OK-00001');
});
