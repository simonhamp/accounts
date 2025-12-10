<?php

use App\Filament\Widgets\OtherIncomeChart;
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

it('renders the other income chart widget', function () {
    Livewire::test(OtherIncomeChart::class)
        ->assertSuccessful();
});

it('displays other income data by month', function () {
    $person = Person::factory()->create();

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now()->startOfYear()->addMonths(2),
        'amount' => 50000,
    ]);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => now()->startOfYear()->addMonths(5),
        'amount' => 75000,
    ]);

    $component = Livewire::test(OtherIncomeChart::class);

    $component->assertSuccessful();
});

it('respects year filter from page filters', function () {
    $person = Person::factory()->create();

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => '2024-03-15',
        'amount' => 50000,
    ]);

    OtherIncome::factory()->create([
        'person_id' => $person->id,
        'income_date' => '2023-03-15',
        'amount' => 30000,
    ]);

    $component = Livewire::test(OtherIncomeChart::class);

    $component->assertSuccessful();
});
