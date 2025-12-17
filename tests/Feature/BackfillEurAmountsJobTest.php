<?php

use App\Jobs\BackfillEurAmounts;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('BackfillEurAmounts Job', function () {
    it('updates invoices with EUR amounts', function () {
        $person = Person::factory()->create();

        // Create the exchange rate FIRST
        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.10,
        ]);

        // Create invoice without triggering the saving hook's EUR calculation
        // by using a future date initially, then updating
        $invoice = new Invoice([
            'person_id' => $person->id,
            'invoice_date' => '2024-01-15',
            'currency' => 'USD',
            'total_amount' => 11000, // $110.00
            'status' => 'pending',
        ]);
        $invoice->saveQuietly();

        // Ensure amount_eur is null
        $invoice->updateQuietly(['amount_eur' => null]);
        $invoice->refresh();
        expect($invoice->amount_eur)->toBeNull();

        $job = new BackfillEurAmounts;
        $job->handle(app(\App\Services\ExchangeRateService::class));

        $invoice->refresh();
        expect($invoice->amount_eur)->toBe(10000); // €100.00
    });

    it('skips invoices that already have EUR amounts unless force is true', function () {
        $person = Person::factory()->create();

        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.10,
        ]);

        $invoice = new Invoice([
            'person_id' => $person->id,
            'invoice_date' => '2024-01-15',
            'currency' => 'USD',
            'total_amount' => 11000,
            'amount_eur' => 5000, // Already has a value
            'status' => 'pending',
        ]);
        $invoice->saveQuietly();

        // Without force, should skip
        $job = new BackfillEurAmounts(force: false);
        $job->handle(app(\App\Services\ExchangeRateService::class));

        $invoice->refresh();
        expect($invoice->amount_eur)->toBe(5000); // Unchanged
    });

    it('updates invoices with force flag even if EUR amount exists', function () {
        $person = Person::factory()->create();

        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.10,
        ]);

        $invoice = new Invoice([
            'person_id' => $person->id,
            'invoice_date' => '2024-01-15',
            'currency' => 'USD',
            'total_amount' => 11000,
            'amount_eur' => 5000, // Already has a value
            'status' => 'pending',
        ]);
        $invoice->saveQuietly();

        // With force, should update
        $job = new BackfillEurAmounts(force: true);
        $job->handle(app(\App\Services\ExchangeRateService::class));

        $invoice->refresh();
        expect($invoice->amount_eur)->toBe(10000); // Updated
    });

    it('can be dispatched to queue', function () {
        Queue::fake();

        BackfillEurAmounts::dispatch();

        Queue::assertPushed(BackfillEurAmounts::class);
    });

    it('can be dispatched with force flag', function () {
        Queue::fake();

        BackfillEurAmounts::dispatch(true);

        Queue::assertPushed(BackfillEurAmounts::class, function ($job) {
            return $job->force === true;
        });
    });
});

describe('BackfillEurAmounts Command', function () {
    it('dispatches job to queue by default', function () {
        Queue::fake();

        $this->artisan('app:backfill-eur-amounts')
            ->expectsOutput('Backfill job has been dispatched to the queue.')
            ->assertExitCode(0);

        Queue::assertPushed(BackfillEurAmounts::class);
    });

    it('runs synchronously with --sync flag', function () {
        $person = Person::factory()->create();

        ExchangeRate::create([
            'date' => '2024-01-15',
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.10,
        ]);

        $invoice = new Invoice([
            'person_id' => $person->id,
            'invoice_date' => '2024-01-15',
            'currency' => 'USD',
            'total_amount' => 11000,
            'status' => 'pending',
        ]);
        $invoice->saveQuietly();
        $invoice->updateQuietly(['amount_eur' => null]);

        $this->artisan('app:backfill-eur-amounts --sync')
            ->expectsOutput('Running backfill synchronously...')
            ->expectsOutput('Backfill completed. Check logs for details.')
            ->assertExitCode(0);

        $invoice->refresh();
        expect($invoice->amount_eur)->toBe(10000);
    });
});
