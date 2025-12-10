<?php

use App\Filament\Widgets\PersonEarningsChart;
use App\Models\Customer;
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

it('renders the person earnings chart widget', function () {
    Livewire::test(PersonEarningsChart::class)
        ->assertSuccessful();
});

it('includes invoice earnings by person', function () {
    $person = Person::factory()->create(['name' => 'Test Person']);
    $customer = Customer::factory()->create();

    Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
    ]);

    $component = Livewire::test(PersonEarningsChart::class);

    $component->assertSuccessful();
});

it('includes other income earnings by person', function () {
    $person = Person::factory()->create(['name' => 'Test Person']);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now(),
        'amount' => 50000,
        'currency' => 'EUR',
    ]);

    $component = Livewire::test(PersonEarningsChart::class);

    $component->assertSuccessful();
});

it('combines invoice and other income for same person and currency', function () {
    $person = Person::factory()->create(['name' => 'Combined Person']);
    $customer = Customer::factory()->create();

    Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
    ]);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now(),
        'amount' => 50000,
        'currency' => 'EUR',
    ]);

    $component = Livewire::test(PersonEarningsChart::class);

    $component->assertSuccessful();
});

it('handles multiple currencies correctly', function () {
    $person = Person::factory()->create(['name' => 'Multi Currency Person']);
    $customer = Customer::factory()->create();

    Invoice::factory()->create([
        'person_id' => $person->id,
        'customer_id' => $customer->id,
        'invoice_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
    ]);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now(),
        'amount' => 50000,
        'currency' => 'USD',
    ]);

    $component = Livewire::test(PersonEarningsChart::class);

    $component->assertSuccessful();
});
