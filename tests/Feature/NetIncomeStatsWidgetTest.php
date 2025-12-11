<?php

use App\Enums\InvoiceStatus;
use App\Filament\Widgets\NetIncomeStats;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the net income stats widget', function () {
    Livewire::test(NetIncomeStats::class)
        ->assertSuccessful();
});

it('calculates net income correctly for a single currency', function () {
    $person = Person::factory()->create();

    // Create invoices (finalized only count)
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 100000, // €1000
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    // Create other income
    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now(),
        'amount' => 50000, // €500
        'currency' => 'EUR',
    ]);

    // Create bills
    Bill::factory()->create([
        'bill_date' => now(),
        'total_amount' => 30000, // €300
        'currency' => 'EUR',
    ]);

    // Net should be: 1000 + 500 - 300 = €1200
    Livewire::test(NetIncomeStats::class)
        ->assertSee('Net Income (EUR)')
        ->assertSee('€1,200.00');
});

it('shows separate stats for different currencies', function () {
    $person = Person::factory()->create();

    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 200000,
        'currency' => 'USD',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    Livewire::test(NetIncomeStats::class)
        ->assertSee('Net Income (EUR)')
        ->assertSee('Net Income (USD)');
});

it('excludes non-finalized invoices from calculations', function () {
    $person = Person::factory()->create();

    // Finalized invoice - should be counted
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    // Pending invoice - should NOT be counted
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 50000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::Pending,
    ]);

    // Net should only include the finalized €1000
    Livewire::test(NetIncomeStats::class)
        ->assertSee('€1,000.00');
});

it('respects year filter', function () {
    $person = Person::factory()->create();

    // Current year invoice
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    // Last year invoice - should not appear when filtering current year
    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now()->subYear(),
        'total_amount' => 200000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    Livewire::test(NetIncomeStats::class)
        ->assertSee('€1,000.00');
});

it('shows negative net income when bills exceed income', function () {
    Bill::factory()->create([
        'bill_date' => now(),
        'total_amount' => 100000, // €1000
        'currency' => 'EUR',
    ]);

    // Net should be -€1000
    Livewire::test(NetIncomeStats::class)
        ->assertSee('-€1,000.00');
});

it('displays breakdown description', function () {
    $person = Person::factory()->create();

    Invoice::factory()->create([
        'person_id' => $person->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => InvoiceStatus::ReadyToSend,
    ]);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now(),
        'amount' => 50000,
        'currency' => 'EUR',
    ]);

    Bill::factory()->create([
        'bill_date' => now(),
        'total_amount' => 30000,
        'currency' => 'EUR',
    ]);

    Livewire::test(NetIncomeStats::class)
        ->assertSee('€1,000 invoices')
        ->assertSee('€500 other')
        ->assertSee('€300 bills');
});
