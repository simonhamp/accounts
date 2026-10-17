<?php

use App\Enums\OtherIncomeStatus;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Services\InvoiceService;
use App\Services\StripeImportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Stripe\Collection;
use Stripe\Exception\PermissionException;
use Stripe\Service\ChargeService;
use Stripe\Service\DisputeService;
use Stripe\Service\InvoicePaymentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

/**
 * Bind a Mockery StripeClient whose list endpoints return the given objects.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $lists  keyed by service name: charges, refunds, disputes, invoicePayments
 * @param  array<string, MockInterface>  $overrides  services to use as they are
 * @return array<string, MockInterface>
 */
function fakeStripeClient(array $lists = [], array $overrides = []): array
{
    $services = [
        'charges' => fakeStripeListService(ChargeService::class, $lists['charges'] ?? []),
        'refunds' => fakeStripeListService(RefundService::class, $lists['refunds'] ?? []),
        'disputes' => fakeStripeListService(DisputeService::class, $lists['disputes'] ?? []),
        'invoicePayments' => fakeStripeListService(InvoicePaymentService::class, $lists['invoicePayments'] ?? []),
        ...$overrides,
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
 * A paid invoice payment with its invoice expanded, as the importer asks for.
 *
 * @param  array<string, mixed>  $invoice  invoice fields to replace
 * @param  array<string, mixed>  $payment
 * @return array<string, mixed>
 */
function stripeInvoicePayment(array $payment, array $invoice = []): array
{
    return [
        'id' => 'inpay_'.Str::random(14),
        'object' => 'invoice_payment',
        'status' => 'paid',
        'payment' => $payment,
        'invoice' => [
            'id' => 'in_'.Str::random(14),
            'object' => 'invoice',
            'description' => 'Clickback ad credit',
            'customer_name' => 'Acme GmbH',
            'customer_email' => 'billing@acme.test',
            'customer_address' => [
                'line1' => 'Hauptstrasse 1',
                'line2' => null,
                'city' => 'Berlin',
                'state' => null,
                'postal_code' => '10115',
                'country' => 'DE',
            ],
            'customer_tax_ids' => [
                ['type' => 'eu_vat', 'value' => 'DE123456789'],
            ],
            ...$invoice,
        ],
    ];
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
    Storage::fake('local');

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

describe('customer details from the Stripe invoice', function () {
    it('takes the customer from the invoice behind a charge', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_invoiced']);
        $invoicePayment = stripeInvoicePayment(['type' => 'payment_intent', 'payment_intent' => 'pi_invoiced']);
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

        expect($transaction->stripe_invoice_id)->toBe($invoicePayment['invoice']['id'])
            ->and($transaction->customer_name)->toBe('Acme GmbH')
            ->and($transaction->customer_email)->toBe('billing@acme.test')
            ->and($transaction->customer_address)->toBe('Hauptstrasse 1, Berlin, 10115, DE')
            ->and($transaction->customer_tax_ids)->toBe([['type' => 'eu_vat', 'value' => 'DE123456789']])
            ->and($transaction->formattedCustomerTaxId())->toBe('DE123456789')
            ->and($transaction->description)->toBe('Clickback ad credit')
            ->and($transaction->status)->toBe('ready');
    });

    it('keeps the billing details for a charge without an invoice', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_plain', 'description' => 'Plugin licence']);
        $otherInvoice = stripeInvoicePayment(['type' => 'payment_intent', 'payment_intent' => 'pi_someone_else']);
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$otherInvoice]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

        expect($transaction->stripe_invoice_id)->toBeNull()
            ->and($transaction->customer_tax_ids)->toBeNull()
            ->and($transaction->customer_name)->toBe('Card Holder')
            ->and($transaction->customer_email)->toBe('cardholder@example.com')
            ->and($transaction->description)->toBe('Plugin licence');
    });

    it('matches older invoice payments by charge ID', function () {
        $charge = stripeCharge(['payment_intent' => null]);
        $invoicePayment = stripeInvoicePayment(['type' => 'charge', 'charge' => $charge['id']]);
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        expect(StripeTransaction::firstWhere('stripe_transaction_id', $charge['id'])->customer_name)->toBe('Acme GmbH');
    });

    it('falls back to the billing details for fields the invoice leaves empty', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_partial']);
        $invoicePayment = stripeInvoicePayment(
            ['type' => 'payment_intent', 'payment_intent' => 'pi_partial'],
            ['customer_name' => null, 'customer_address' => null, 'customer_tax_ids' => [], 'description' => null],
        );
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

        expect($transaction->customer_name)->toBe('Card Holder')
            ->and($transaction->customer_email)->toBe('billing@acme.test')
            ->and($transaction->customer_address)->toBe('1 Card Street, Leeds, LS1 1AA, GB')
            ->and($transaction->customer_tax_ids)->toBeNull()
            ->and($transaction->description)->toBe('Payment');
    });

    it('keeps every tax ID with its type', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_two_ids']);
        $invoicePayment = stripeInvoicePayment(
            ['type' => 'payment_intent', 'payment_intent' => 'pi_two_ids'],
            ['customer_tax_ids' => [
                ['type' => 'eu_vat', 'value' => 'ESB12345678'],
                ['type' => 'es_cif', 'value' => 'B12345678'],
            ]],
        );
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $charge['id']);

        expect($transaction->customer_tax_ids)->toBe([
            ['type' => 'eu_vat', 'value' => 'ESB12345678'],
            ['type' => 'es_cif', 'value' => 'B12345678'],
        ])->and($transaction->formattedCustomerTaxId())->toBe('ESB12345678, B12345678');
    });

    it('lists paid invoice payments once for the window, with the invoice expanded', function () {
        $this->travelTo('2026-10-17 12:00:00');
        $services = fakeStripeClient(['charges' => [stripeCharge(), stripeCharge(), stripeCharge()]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $services['invoicePayments']->shouldHaveReceived('all')->once()->with(Mockery::on(fn (array $params) => $params['status'] === 'paid'
            && $params['expand'] === ['data.invoice']
            && $params['limit'] === 100
            && $params['created']['gte'] === now()->subDays(7)->startOfDay()->subDays(31)->timestamp
            && $params['created']['lte'] === now()->endOfDay()->addDay()->timestamp));
    });

    it('carries on with billing details when the key cannot read invoices', function () {
        $denied = Mockery::mock(InvoicePaymentService::class);
        $denied->shouldReceive('all')->andThrow(PermissionException::factory('The provided key does not have the required permissions', 403));

        $charge = stripeCharge();
        fakeStripeClient(['charges' => [$charge]], ['invoicePayments' => $denied]);

        $result = app(StripeImportService::class)->syncAccount($this->account);

        expect($result['payments'])->toBe(1)
            ->and($result['warnings'])->toHaveCount(1)
            ->and(StripeTransaction::firstWhere('stripe_transaction_id', $charge['id'])->customer_name)->toBe('Card Holder');
    });

    it('fills in the invoice details on a re-sync of a transaction not yet invoiced', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_later']);
        $transaction = StripeTransaction::factory()->create([
            'stripe_account_id' => $this->account->id,
            'stripe_transaction_id' => $charge['id'],
            'type' => 'payment',
            'customer_name' => 'Card Holder',
            'customer_email' => null,
            'status' => 'ready',
        ]);
        $invoicePayment = stripeInvoicePayment(['type' => 'payment_intent', 'payment_intent' => 'pi_later']);
        fakeStripeClient(['charges' => [$charge], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction->refresh();

        expect($transaction->stripe_invoice_id)->toBe($invoicePayment['invoice']['id'])
            ->and($transaction->customer_tax_ids)->toBe([['type' => 'eu_vat', 'value' => 'DE123456789']])
            ->and($transaction->customer_email)->toBe('billing@acme.test')
            ->and($transaction->customer_name)->toBe('Card Holder');
    });
});

describe('refunds and disputes', function () {
    it('gives a refund the customer of the charge it refunds', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_refunded']);
        $invoicePayment = stripeInvoicePayment(['type' => 'payment_intent', 'payment_intent' => 'pi_refunded']);
        $refund = stripeRefund(['charge' => $charge['id']]);
        $services = fakeStripeClient([
            'charges' => [$charge],
            'refunds' => [$refund],
            'invoicePayments' => [$invoicePayment],
        ]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $refund['id']);

        expect($transaction->type)->toBe('refund')
            ->and($transaction->amount)->toBe(-5000)
            ->and($transaction->stripe_charge_id)->toBe($charge['id'])
            ->and($transaction->stripe_invoice_id)->toBe($invoicePayment['invoice']['id'])
            ->and($transaction->customer_name)->toBe('Acme GmbH')
            ->and($transaction->customer_email)->toBe('billing@acme.test')
            ->and($transaction->formattedCustomerTaxId())->toBe('DE123456789')
            ->and($transaction->originalCharge->stripe_transaction_id)->toBe($charge['id']);

        $services['refunds']->shouldHaveReceived('all')->with(Mockery::on(fn (array $params) => $params['expand'] === ['data.charge']));
    });

    it('prefers the details edited by hand on the imported charge', function () {
        $original = StripeTransaction::factory()->create([
            'stripe_account_id' => $this->account->id,
            'stripe_transaction_id' => 'ch_old_charge',
            'type' => 'payment',
            'customer_name' => 'Corrected Name SL',
            'customer_email' => 'accounts@corrected.test',
        ]);
        $refund = stripeRefund(['charge' => stripeCharge(['id' => 'ch_old_charge'])]);
        fakeStripeClient(['refunds' => [$refund]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $refund['id']);

        expect($transaction->customer_name)->toBe('Corrected Name SL')
            ->and($transaction->customer_email)->toBe('accounts@corrected.test')
            ->and($transaction->originalCharge->is($original))->toBeTrue();
    });

    it('reads the expanded charge when the refunded charge was never imported', function () {
        $refund = stripeRefund(['charge' => stripeCharge(['id' => 'ch_before_our_time'])]);
        fakeStripeClient(['refunds' => [$refund]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $refund['id']);

        expect($transaction->stripe_charge_id)->toBe('ch_before_our_time')
            ->and($transaction->customer_name)->toBe('Card Holder')
            ->and($transaction->customer_email)->toBe('cardholder@example.com');
    });

    it('gives a dispute the customer of the disputed charge', function () {
        $charge = stripeCharge(['payment_intent' => 'pi_disputed']);
        $invoicePayment = stripeInvoicePayment(['type' => 'payment_intent', 'payment_intent' => 'pi_disputed']);
        $dispute = stripeDispute(['charge' => $charge]);
        $services = fakeStripeClient(['disputes' => [$dispute], 'invoicePayments' => [$invoicePayment]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $dispute['id']);

        expect($transaction->type)->toBe('chargeback')
            ->and($transaction->stripe_charge_id)->toBe($charge['id'])
            ->and($transaction->customer_email)->toBe('billing@acme.test')
            ->and($transaction->formattedCustomerTaxId())->toBe('DE123456789');

        $services['disputes']->shouldHaveReceived('all')->with(Mockery::on(fn (array $params) => $params['expand'] === ['data.charge']));
    });

    it('imports a refund with no charge at all', function () {
        $refund = stripeRefund(['charge' => null]);
        fakeStripeClient(['refunds' => [$refund]]);

        app(StripeImportService::class)->syncAccount($this->account);

        $transaction = StripeTransaction::firstWhere('stripe_transaction_id', $refund['id']);

        expect($transaction->stripe_charge_id)->toBeNull()
            ->and($transaction->customer_email)->toBeNull()
            ->and($transaction->status)->toBe('ready');
    });
});
