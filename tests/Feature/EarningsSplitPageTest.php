<?php

use App\Enums\BillStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OtherIncomeStatus;
use App\Filament\Pages\EarningsSplit;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);

    // Create a supplier for bills (to avoid factory creating random people)
    $this->supplier = Supplier::factory()->create();
});

describe('Earnings Split Page', function () {
    it('can render the page', function () {
        Person::factory()->create(['name' => 'Simon Hamp']);
        Person::factory()->create(['name' => 'Maria Noelia Santana Garcia']);

        $this->get(EarningsSplit::getUrl())
            ->assertSuccessful();
    });

    it('shows month-by-month breakdown', function () {
        Person::factory()->create(['name' => 'Simon Hamp']);
        Person::factory()->create(['name' => 'Maria Noelia Santana Garcia']);

        Livewire::test(EarningsSplit::class)
            ->assertSee('Monthly Breakdown')
            ->assertSee('January')
            ->assertSee('December')
            ->assertSee('YEAR TOTAL');
    });

    it('calculates net earnings correctly', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);
        $noelia = Person::factory()->create(['name' => 'Maria Noelia Santana Garcia']);

        $currentYear = (int) date('Y');

        // Simon: Invoice €1000, Bill €200 = Net €800
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-001',
            'invoice_date' => "{$currentYear}-03-15",
            'period_month' => 3,
            'period_year' => $currentYear,
            'total_amount' => 100000,
            'amount_eur' => 100000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer',
        ]);

        Bill::create([
            'person_id' => $simon->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-001',
            'bill_date' => "{$currentYear}-03-10",
            'total_amount' => 20000,
            'amount_eur' => 20000,
            'currency' => 'EUR',
            'status' => BillStatus::Paid,
        ]);

        // Noelia: Invoice €600 = Net €600
        Invoice::create([
            'person_id' => $noelia->id,
            'invoice_number' => 'TEST-002',
            'invoice_date' => "{$currentYear}-03-20",
            'period_month' => 3,
            'period_year' => $currentYear,
            'total_amount' => 60000,
            'amount_eur' => 60000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer 2',
        ]);

        $component = Livewire::test(EarningsSplit::class, ['year' => (string) $currentYear]);

        $data = $component->instance()->getMonthlyData();

        // Find March data
        $march = $data->firstWhere('month', 3);

        expect($march['person_'.$simon->id.'_net'])->toBe(80000) // €800 in cents
            ->and($march['person_'.$noelia->id.'_net'])->toBe(60000) // €600 in cents
            ->and($march['total_earnings'])->toBe(140000) // €1400 combined
            ->and((int) $march['fair_share'])->toBe(70000); // €700 each
    });

    it('calculates settlement correctly when one person earns more', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);
        $noelia = Person::factory()->create(['name' => 'Maria Noelia Santana Garcia']);

        $currentYear = (int) date('Y');

        // Simon earns €1000
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-003',
            'invoice_date' => "{$currentYear}-06-15",
            'period_month' => 6,
            'period_year' => $currentYear,
            'total_amount' => 100000,
            'amount_eur' => 100000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer',
        ]);

        // Noelia earns €400
        Invoice::create([
            'person_id' => $noelia->id,
            'invoice_number' => 'TEST-004',
            'invoice_date' => "{$currentYear}-06-20",
            'period_month' => 6,
            'period_year' => $currentYear,
            'total_amount' => 40000,
            'amount_eur' => 40000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer 2',
        ]);

        $component = Livewire::test(EarningsSplit::class, ['year' => (string) $currentYear]);

        $data = $component->instance()->getMonthlyData();
        $june = $data->firstWhere('month', 6);

        // Total: €1400, Fair share: €700 each
        // Simon has +€300 (earned €1000, fair share €700)
        // Noelia has -€300 (earned €400, fair share €700)
        // Simon should pay Noelia €300
        expect($june['settlement'])->toContain('Simon')
            ->and($june['settlement'])->toContain('Maria')
            ->and($june['settlement'])->toContain('300.00');
    });

    it('only includes finalized invoices', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);

        $currentYear = (int) date('Y');

        // Pending invoice should NOT be included
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-005',
            'invoice_date' => "{$currentYear}-01-15",
            'period_month' => 1,
            'period_year' => $currentYear,
            'total_amount' => 100000,
            'amount_eur' => 100000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Pending,
            'customer_name' => 'Test Customer',
        ]);

        // Paid invoice should be included
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-006',
            'invoice_date' => "{$currentYear}-01-20",
            'period_month' => 1,
            'period_year' => $currentYear,
            'total_amount' => 50000,
            'amount_eur' => 50000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer 2',
        ]);

        $component = Livewire::test(EarningsSplit::class, ['year' => (string) $currentYear]);

        $data = $component->instance()->getMonthlyData();
        $january = $data->firstWhere('month', 1);

        // Only the paid invoice should be counted
        expect($january['person_'.$simon->id.'_invoices'])->toBe(50000);
    });

    it('only includes paid bills', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);

        $currentYear = (int) date('Y');

        // Reviewed (unpaid) bill should NOT be included
        Bill::create([
            'person_id' => $simon->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-002',
            'bill_date' => "{$currentYear}-02-15",
            'total_amount' => 30000,
            'amount_eur' => 30000,
            'currency' => 'EUR',
            'status' => BillStatus::Reviewed,
        ]);

        // Paid bill should be included
        Bill::create([
            'person_id' => $simon->id,
            'supplier_id' => $this->supplier->id,
            'bill_number' => 'BILL-003',
            'bill_date' => "{$currentYear}-02-20",
            'total_amount' => 20000,
            'amount_eur' => 20000,
            'currency' => 'EUR',
            'status' => BillStatus::Paid,
        ]);

        $component = Livewire::test(EarningsSplit::class, ['year' => (string) $currentYear]);

        $data = $component->instance()->getMonthlyData();
        $february = $data->firstWhere('month', 2);

        // Only the paid bill should be counted
        expect($february['person_'.$simon->id.'_bills'])->toBe(20000);
    });

    it('includes paid other income', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);

        $currentYear = (int) date('Y');

        OtherIncome::create([
            'person_id' => $simon->id,
            'income_date' => "{$currentYear}-04-15",
            'description' => 'Test income',
            'amount' => 25000,
            'amount_eur' => 25000,
            'currency' => 'EUR',
            'status' => OtherIncomeStatus::Paid,
        ]);

        $component = Livewire::test(EarningsSplit::class, ['year' => (string) $currentYear]);

        $data = $component->instance()->getMonthlyData();
        $april = $data->firstWhere('month', 4);

        expect($april['person_'.$simon->id.'_other_income'])->toBe(25000);
    });

    it('can filter by year', function () {
        $simon = Person::factory()->create(['name' => 'Simon Hamp']);

        // 2024 invoice
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-007',
            'invoice_date' => '2024-05-15',
            'period_month' => 5,
            'period_year' => 2024,
            'total_amount' => 100000,
            'amount_eur' => 100000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer',
        ]);

        // 2023 invoice
        Invoice::create([
            'person_id' => $simon->id,
            'invoice_number' => 'TEST-008',
            'invoice_date' => '2023-05-15',
            'period_month' => 5,
            'period_year' => 2023,
            'total_amount' => 50000,
            'amount_eur' => 50000,
            'currency' => 'EUR',
            'status' => InvoiceStatus::Paid,
            'customer_name' => 'Test Customer 2',
        ]);

        // Test 2024
        $component = Livewire::test(EarningsSplit::class, ['year' => '2024']);
        $data = $component->instance()->getMonthlyData();
        $may = $data->firstWhere('month', 5);
        expect($may['person_'.$simon->id.'_invoices'])->toBe(100000);

        // Test 2023
        $component = Livewire::test(EarningsSplit::class, ['year' => '2023']);
        $data = $component->instance()->getMonthlyData();
        $may = $data->firstWhere('month', 5);
        expect($may['person_'.$simon->id.'_invoices'])->toBe(50000);
    });

    it('is in the Reports navigation group', function () {
        expect(EarningsSplit::getNavigationGroup())->toBe('Reports');
    });
});
