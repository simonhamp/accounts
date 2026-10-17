<?php

use App\Enums\OtherIncomeStatus;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Services\InvoiceService;
use App\Services\StripeImportService;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Stripe\Collection;
use Stripe\Service\ChargeService;
use Stripe\Service\DisputeService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

/**
 * Bind a Mockery StripeClient whose list endpoints return the given objects.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $lists  keyed by service name: charges, refunds, disputes
 * @return array<string, MockInterface>
 */
function fakeStripeClient(array $lists = []): array
{
    $services = [
        'charges' => fakeStripeListService(ChargeService::class, $lists['charges'] ?? []),
        'refunds' => fakeStripeListService(RefundService::class, $lists['refunds'] ?? []),
        'disputes' => fakeStripeListService(DisputeService::class, $lists['disputes'] ?? []),
    ];

    $client = Mockery::mock(StripeClient::class)->makePartial();
    $client->shouldReceive('getService')->andReturnUsing(fn (string $name) => $services[$name]);

    app()->bind(StripeClient::class, fn () => $client);

    return $services;
}

/**
 * @param  class-string  $serviceClass
 * @param  array<int, array<string, mixed>>  $items
 */
function fakeStripeListService(string $serviceClass, array $items): MockInterface
{
    $service = Mockery::mock($serviceClass);
    $service->shouldReceive('all')->andReturn(Collection::constructFrom([
        'object' => 'list',
        'data' => $items,
        'has_more' => false,
        'url' => '/v1/list',
    ]));

    return $service;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeCharge(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'ch_'.Str::random(14),
        'object' => 'charge',
        'amount' => 5000,
        'currency' => 'usd',
        'status' => 'succeeded',
        'created' => now()->subDay()->timestamp,
        'description' => null,
        'payment_intent' => 'pi_'.Str::random(14),
        'receipt_email' => null,
        'customer' => null,
        'billing_details' => [
            'name' => 'Card Holder',
            'email' => 'cardholder@example.com',
            'address' => [
                'line1' => '1 Card Street',
                'line2' => null,
                'city' => 'Leeds',
                'state' => null,
                'postal_code' => 'LS1 1AA',
                'country' => 'GB',
            ],
        ],
        'metadata' => [],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeRefund(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 're_'.Str::random(14),
        'object' => 'refund',
        'amount' => 5000,
        'currency' => 'usd',
        'status' => 'succeeded',
        'reason' => 'requested_by_customer',
        'created' => now()->subDay()->timestamp,
        'charge' => null,
        'payment_intent' => null,
        'metadata' => [],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeDispute(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'dp_'.Str::random(14),
        'object' => 'dispute',
        'amount' => 5000,
        'currency' => 'usd',
        'status' => 'needs_response',
        'reason' => 'fraudulent',
        'created' => now()->subDay()->timestamp,
        'charge' => null,
        'payment_intent' => null,
    ], $overrides);
}

beforeEach(function () {
    $this->account = StripeAccount::factory()->create([
        'person_id' => Person::factory()->create(['invoice_prefix' => 'IMP'])->id,
    ]);
});

it('imports a new charge as ready', function () {
    $charge = stripeCharge(['description' => 'Ad credit']);
    fakeStripeClient(['charges' => [$charge]]);

    $result = app(StripeImportService::class)->syncAccount($this->account);

    expect($result['payments'])->toBe(1);

    $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

    expect($transaction->status)->toBe('ready')
        ->and($transaction->type)->toBe('payment')
        ->and($transaction->amount)->toBe(5000)
        ->and($transaction->currency)->toBe('USD')
        ->and($transaction->description)->toBe('Ad credit')
        ->and($transaction->customer_name)->toBe('Card Holder')
        ->and($transaction->customer_email)->toBe('cardholder@example.com')
        ->and($transaction->customer_address)->toBe('1 Card Street, Leeds, LS1 1AA, GB');
});

it('stores charge metadata values without the request options or API key', function () {
    $charge = stripeCharge(['metadata' => ['clickback_advertiser_id' => '42']]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);

    $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

    expect($transaction->metadata)->toBe(['clickback_advertiser_id' => '42'])
        ->and($transaction->getRawOriginal('metadata'))->not->toContain('apiKey');
});

it('never changes the status of an existing transaction on a re-sync', function (string $status) {
    $charge = stripeCharge();
    $transaction = StripeTransaction::factory()->create([
        'stripe_account_id' => $this->account->id,
        'stripe_transaction_id' => $charge['id'],
        'type' => 'payment',
        'status' => $status,
    ]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);

    expect($transaction->fresh()->status)->toBe($status);
})->with(['ignored', 'pending_review', 'ready']);

it('does not invoice an ignored transaction after a re-sync', function () {
    $charge = stripeCharge();
    StripeTransaction::factory()->create([
        'stripe_account_id' => $this->account->id,
        'stripe_transaction_id' => $charge['id'],
        'type' => 'payment',
        'status' => 'ignored',
    ]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);
    $result = app(InvoiceService::class)->generateInvoicesForReadyTransactions($this->account);

    expect($result['generated'])->toBe(0)
        ->and(Invoice::count())->toBe(0);
});

it('keeps ignored refunds and disputes ignored on a re-sync', function () {
    $refund = stripeRefund();
    $dispute = stripeDispute();

    foreach ([[$refund['id'], 'refund'], [$dispute['id'], 'chargeback']] as [$id, $type]) {
        StripeTransaction::factory()->create([
            'stripe_account_id' => $this->account->id,
            'stripe_transaction_id' => $id,
            'type' => $type,
            'status' => 'ignored',
        ]);
    }

    fakeStripeClient(['refunds' => [$refund], 'disputes' => [$dispute]]);

    app(StripeImportService::class)->syncAccount($this->account);

    expect(StripeTransaction::where('status', 'ignored')->count())->toBe(2);
});

it('leaves an invoiced transaction exactly as it was apart from metadata', function () {
    $charge = stripeCharge([
        'amount' => 9999,
        'description' => 'Changed in Stripe',
        'metadata' => ['note' => 'fresh'],
    ]);
    $transaction = StripeTransaction::factory()->create([
        'stripe_account_id' => $this->account->id,
        'stripe_transaction_id' => $charge['id'],
        'type' => 'payment',
        'amount' => 5000,
        'description' => 'Original description',
        'customer_name' => null,
        'customer_email' => null,
        'status' => 'ready',
    ]);
    $invoice = Invoice::factory()->create(['person_id' => $this->account->person_id]);
    $transaction->invoiceItem()->create([
        'invoice_id' => $invoice->id,
        'description' => 'Original description',
        'quantity' => 1,
        'unit_price' => 5000,
        'total' => 5000,
    ]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);

    $transaction->refresh();

    expect($transaction->status)->toBe('ready')
        ->and($transaction->amount)->toBe(5000)
        ->and($transaction->description)->toBe('Original description')
        ->and($transaction->customer_name)->toBeNull()
        ->and($transaction->customer_email)->toBeNull()
        ->and($transaction->metadata)->toBe(['note' => 'fresh']);
});

it('leaves a transaction converted to other income alone', function () {
    $charge = stripeCharge();
    $transaction = StripeTransaction::factory()->create([
        'stripe_account_id' => $this->account->id,
        'stripe_transaction_id' => $charge['id'],
        'type' => 'payment',
        'customer_name' => null,
        'status' => 'ready',
    ]);
    OtherIncome::create([
        'person_id' => $this->account->person_id,
        'stripe_transaction_id' => $transaction->id,
        'income_date' => $transaction->transaction_date,
        'description' => $transaction->description,
        'amount' => $transaction->amount,
        'currency' => $transaction->currency,
        'status' => OtherIncomeStatus::Paid,
    ]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);

    expect($transaction->fresh()->customer_name)->toBeNull()
        ->and($transaction->fresh()->status)->toBe('ready');
});

it('keeps hand-edited customer details and only fills in blanks', function () {
    $charge = stripeCharge();
    $transaction = StripeTransaction::factory()->create([
        'stripe_account_id' => $this->account->id,
        'stripe_transaction_id' => $charge['id'],
        'type' => 'payment',
        'customer_name' => 'Name Simon Typed',
        'customer_email' => null,
        'customer_address' => null,
        'description' => 'Description Simon Typed',
        'status' => 'pending_review',
    ]);
    fakeStripeClient(['charges' => [$charge]]);

    app(StripeImportService::class)->syncAccount($this->account);

    $transaction->refresh();

    expect($transaction->customer_name)->toBe('Name Simon Typed')
        ->and($transaction->description)->toBe('Description Simon Typed')
        ->and($transaction->customer_email)->toBe('cardholder@example.com')
        ->and($transaction->customer_address)->toBe('1 Card Street, Leeds, LS1 1AA, GB')
        ->and($transaction->status)->toBe('pending_review');
});
