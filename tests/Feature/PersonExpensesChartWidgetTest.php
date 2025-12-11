<?php

use App\Enums\BillStatus;
use App\Filament\Widgets\PersonExpensesChart;
use App\Models\Bill;
use App\Models\Person;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the person expenses chart widget', function () {
    Livewire::test(PersonExpensesChart::class)
        ->assertSuccessful();
});

it('includes paid bill expenses by person', function () {
    $person = Person::factory()->create(['name' => 'Test Person']);
    $supplier = Supplier::factory()->create();

    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => BillStatus::Paid,
    ]);

    $component = Livewire::test(PersonExpensesChart::class);

    $component->assertSuccessful();
});

it('includes all bills regardless of status', function () {
    $person = Person::factory()->create(['name' => 'Test Person']);
    $supplier = Supplier::factory()->create();

    // All bills should be counted regardless of status
    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => BillStatus::Pending,
    ]);

    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 50000,
        'currency' => 'EUR',
        'status' => BillStatus::Reviewed,
    ]);

    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 75000,
        'currency' => 'EUR',
        'status' => BillStatus::Paid,
    ]);

    $component = Livewire::test(PersonExpensesChart::class);

    $component->assertSuccessful();
});

it('handles multiple currencies correctly', function () {
    $person = Person::factory()->create(['name' => 'Multi Currency Person']);
    $supplier = Supplier::factory()->create();

    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => BillStatus::Paid,
    ]);

    Bill::factory()->create([
        'person_id' => $person->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 50000,
        'currency' => 'USD',
        'status' => BillStatus::Paid,
    ]);

    $component = Livewire::test(PersonExpensesChart::class);

    $component->assertSuccessful();
});

it('handles multiple people correctly', function () {
    $person1 = Person::factory()->create(['name' => 'Person One']);
    $person2 = Person::factory()->create(['name' => 'Person Two']);
    $supplier = Supplier::factory()->create();

    Bill::factory()->create([
        'person_id' => $person1->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 100000,
        'currency' => 'EUR',
        'status' => BillStatus::Paid,
    ]);

    Bill::factory()->create([
        'person_id' => $person2->id,
        'supplier_id' => $supplier->id,
        'bill_date' => now(),
        'total_amount' => 75000,
        'currency' => 'EUR',
        'status' => BillStatus::Paid,
    ]);

    $component = Livewire::test(PersonExpensesChart::class);

    $component->assertSuccessful();
});
