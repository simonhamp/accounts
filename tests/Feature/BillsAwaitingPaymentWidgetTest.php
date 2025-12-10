<?php

use App\Enums\BillStatus;
use App\Filament\Widgets\BillsAwaitingPayment;
use App\Models\Bill;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the bills awaiting payment widget', function () {
    Livewire::test(BillsAwaitingPayment::class)
        ->assertSuccessful();
});

it('shows bills with reviewed status', function () {
    $supplier = Supplier::factory()->create(['name' => 'Test Supplier']);

    $readyToPay = Bill::factory()->create([
        'supplier_id' => $supplier->id,
        'bill_number' => 'BILL-001',
        'status' => BillStatus::Reviewed,
        'total_amount' => 10000,
    ]);

    $paid = Bill::factory()->create([
        'supplier_id' => $supplier->id,
        'bill_number' => 'BILL-002',
        'status' => BillStatus::Paid,
    ]);

    $pending = Bill::factory()->create([
        'supplier_id' => $supplier->id,
        'bill_number' => 'BILL-003',
        'status' => BillStatus::Pending,
    ]);

    Livewire::test(BillsAwaitingPayment::class)
        ->assertCanSeeTableRecords([$readyToPay])
        ->assertCanNotSeeTableRecords([$paid, $pending]);
});

it('can mark a bill as paid', function () {
    $bill = Bill::factory()->create([
        'status' => BillStatus::Reviewed,
    ]);

    expect($bill->status)->toBe(BillStatus::Reviewed);

    Livewire::test(BillsAwaitingPayment::class)
        ->callTableAction('markPaid', $bill);

    $bill->refresh();

    expect($bill->status)->toBe(BillStatus::Paid);
});

it('shows empty state when no bills awaiting payment', function () {
    Livewire::test(BillsAwaitingPayment::class)
        ->assertSee('No bills awaiting payment');
});
