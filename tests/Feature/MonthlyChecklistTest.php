<?php

use App\Enums\BillingFrequency;
use App\Models\IncomeSource;
use App\Models\MonthlyChecklist;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MonthlyChecklist Model', function () {
    it('creates checklist with default items', function () {
        $checklist = MonthlyChecklist::factory()->create();

        expect($checklist->items)->toBeArray();
        expect($checklist->items)->toHaveKeys(['suppliers', 'income_sources', 'invoices_reviewed', 'bank_statements_checked', 'other_incomes_reviewed']);
        expect($checklist->items['invoices_reviewed'])->toBeFalse();
        expect($checklist->items['bank_statements_checked'])->toBeFalse();
        expect($checklist->items['other_incomes_reviewed'])->toBeFalse();
    });

    it('can find or create checklist for a month', function () {
        $checklist1 = MonthlyChecklist::findOrCreateForMonth(6, 2025);
        $checklist2 = MonthlyChecklist::findOrCreateForMonth(6, 2025);

        expect($checklist1->id)->toBe($checklist2->id);
        expect($checklist1->period_month)->toBe(6);
        expect($checklist1->period_year)->toBe(2025);
    });

    it('returns correct period name', function () {
        $checklist = MonthlyChecklist::factory()->forMonth(6, 2025)->create();

        expect($checklist->period_name)->toBe('June 2025');
    });

    it('can be marked as complete', function () {
        $checklist = MonthlyChecklist::factory()->create();

        expect($checklist->isComplete())->toBeFalse();

        $checklist->markAsComplete();

        expect($checklist->isComplete())->toBeTrue();
        expect($checklist->completed_at)->not->toBeNull();
    });

    it('can be marked as incomplete', function () {
        $checklist = MonthlyChecklist::factory()->completed()->create();

        expect($checklist->isComplete())->toBeTrue();

        $checklist->markAsIncomplete();

        expect($checklist->isComplete())->toBeFalse();
        expect($checklist->completed_at)->toBeNull();
    });
});

describe('MonthlyChecklist Scopes', function () {
    it('can filter by month and year', function () {
        MonthlyChecklist::factory()->forMonth(6, 2025)->create();
        MonthlyChecklist::factory()->forMonth(7, 2025)->create();
        MonthlyChecklist::factory()->forMonth(6, 2024)->create();

        expect(MonthlyChecklist::forMonth(6, 2025)->count())->toBe(1);
    });

    it('can filter current month', function () {
        MonthlyChecklist::factory()->currentMonth()->create();
        MonthlyChecklist::factory()->forMonth(1, 2020)->create();

        expect(MonthlyChecklist::currentMonth()->count())->toBe(1);
    });
});

describe('MonthlyChecklist Completion Tracking', function () {
    it('calculates completion percentage with no items', function () {
        $checklist = MonthlyChecklist::factory()->create();

        expect($checklist->getCompletionPercentage())->toBe(0);
    });

    it('calculates completion percentage with suppliers', function () {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        $checklist = MonthlyChecklist::factory()
            ->withSuppliers([$supplier1->id, $supplier2->id])
            ->create();

        expect($checklist->getCompletionPercentage())->toBeLessThan(100);

        $checklist->markSupplierCompleted($supplier1->id);
        $checklist->refresh();

        expect($checklist->getCompletionPercentage())->toBeGreaterThan(0);
    });

    it('calculates completion percentage including general items', function () {
        $checklist = MonthlyChecklist::factory()->create();

        // With no suppliers/income sources, only general items count (3 items)
        expect($checklist->getCompletionPercentage())->toBe(0);

        $checklist->updateItem('invoices_reviewed', '', true);
        $checklist->refresh();

        expect($checklist->getCompletionPercentage())->toBe(33);

        $checklist->updateItem('bank_statements_checked', '', true);
        $checklist->refresh();

        expect($checklist->getCompletionPercentage())->toBe(67);

        $checklist->updateItem('other_incomes_reviewed', '', true);
        $checklist->refresh();

        expect($checklist->getCompletionPercentage())->toBe(100);
    });

    it('auto-completes when all items are done', function () {
        $checklist = MonthlyChecklist::factory()->create();

        $checklist->updateItem('invoices_reviewed', '', true);
        $checklist->updateItem('bank_statements_checked', '', true);
        $checklist->updateItem('other_incomes_reviewed', '', true);

        $checklist->refresh();

        expect($checklist->isComplete())->toBeTrue();
    });

    it('auto-uncompletes when an item is undone', function () {
        $checklist = MonthlyChecklist::factory()->create();

        // Complete all items
        $checklist->updateItem('invoices_reviewed', '', true);
        $checklist->updateItem('bank_statements_checked', '', true);
        $checklist->updateItem('other_incomes_reviewed', '', true);
        $checklist->refresh();

        expect($checklist->isComplete())->toBeTrue();

        // Undo one item
        $checklist->updateItem('invoices_reviewed', '', false);
        $checklist->refresh();

        expect($checklist->isComplete())->toBeFalse();
    });
});

describe('MonthlyChecklist Supplier Management', function () {
    it('can add a supplier', function () {
        $supplier = Supplier::factory()->create();
        $checklist = MonthlyChecklist::factory()->create();

        $checklist->addSupplier($supplier->id);
        $checklist->refresh();

        expect($checklist->items['suppliers'])->toHaveKey($supplier->id);
        expect($checklist->items['suppliers'][$supplier->id]['completed'])->toBeFalse();
    });

    it('can mark supplier as completed', function () {
        $supplier = Supplier::factory()->create();
        $checklist = MonthlyChecklist::factory()->withSuppliers([$supplier->id])->create();

        $checklist->markSupplierCompleted($supplier->id, 123);
        $checklist->refresh();

        expect($checklist->items['suppliers'][$supplier->id]['completed'])->toBeTrue();
        expect($checklist->items['suppliers'][$supplier->id]['bill_id'])->toBe(123);
        expect($checklist->items['suppliers'][$supplier->id]['skipped'])->toBeFalse();
    });

    it('can mark supplier as skipped', function () {
        $supplier = Supplier::factory()->create();
        $checklist = MonthlyChecklist::factory()->withSuppliers([$supplier->id])->create();

        $checklist->markSupplierSkipped($supplier->id);
        $checklist->refresh();

        expect($checklist->items['suppliers'][$supplier->id]['skipped'])->toBeTrue();
        expect($checklist->items['suppliers'][$supplier->id]['completed'])->toBeFalse();
    });

    it('can get pending supplier ids', function () {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();
        $supplier3 = Supplier::factory()->create();

        $checklist = MonthlyChecklist::factory()
            ->withSuppliers([$supplier1->id, $supplier2->id, $supplier3->id])
            ->create();

        $checklist->markSupplierCompleted($supplier1->id);
        $checklist->markSupplierSkipped($supplier2->id);

        expect($checklist->getPendingSupplierIds())->toBe([$supplier3->id]);
    });
});

describe('MonthlyChecklist Income Source Management', function () {
    it('can add an income source', function () {
        $incomeSource = IncomeSource::factory()->create();
        $checklist = MonthlyChecklist::factory()->create();

        $checklist->addIncomeSource($incomeSource->id);
        $checklist->refresh();

        expect($checklist->items['income_sources'])->toHaveKey($incomeSource->id);
        expect($checklist->items['income_sources'][$incomeSource->id]['completed'])->toBeFalse();
    });

    it('can mark income source as completed', function () {
        $incomeSource = IncomeSource::factory()->create();
        $checklist = MonthlyChecklist::factory()->withIncomeSources([$incomeSource->id])->create();

        $checklist->markIncomeSourceCompleted($incomeSource->id);
        $checklist->refresh();

        expect($checklist->items['income_sources'][$incomeSource->id]['completed'])->toBeTrue();
    });

    it('can mark income source as skipped', function () {
        $incomeSource = IncomeSource::factory()->create();
        $checklist = MonthlyChecklist::factory()->withIncomeSources([$incomeSource->id])->create();

        $checklist->markIncomeSourceSkipped($incomeSource->id);
        $checklist->refresh();

        expect($checklist->items['income_sources'][$incomeSource->id]['skipped'])->toBeTrue();
    });
});

describe('Generate Checklist Command', function () {
    it('generates a checklist for the current month', function () {
        $this->artisan('checklist:generate')
            ->assertSuccessful();

        expect(MonthlyChecklist::currentMonth()->count())->toBe(1);
    });

    it('does not regenerate existing checklist without force flag', function () {
        MonthlyChecklist::factory()->currentMonth()->create();

        $this->artisan('checklist:generate')
            ->assertSuccessful();

        expect(MonthlyChecklist::currentMonth()->count())->toBe(1);
    });

    it('regenerates checklist with force flag', function () {
        $checklist = MonthlyChecklist::factory()->currentMonth()->create();
        $originalId = $checklist->id;

        $this->artisan('checklist:generate', ['--force' => true])
            ->assertSuccessful();

        expect(MonthlyChecklist::currentMonth()->count())->toBe(1);
        expect(MonthlyChecklist::currentMonth()->first()->id)->toBe($originalId);
    });

    it('includes suppliers expecting bills this month', function () {
        $monthlySupplier = Supplier::factory()->monthly()->create();
        $annualSupplierThisMonth = Supplier::factory()->annual(now()->month)->create();
        $annualSupplierOtherMonth = Supplier::factory()->annual(now()->month === 1 ? 2 : 1)->create();
        $noBillingSupplier = Supplier::factory()->noBilling()->create();

        $this->artisan('checklist:generate', ['--force' => true])
            ->assertSuccessful();

        $checklist = MonthlyChecklist::currentMonth()->first();

        expect(array_keys($checklist->items['suppliers']))->toContain($monthlySupplier->id);
        expect(array_keys($checklist->items['suppliers']))->toContain($annualSupplierThisMonth->id);
        expect(array_keys($checklist->items['suppliers']))->not->toContain($annualSupplierOtherMonth->id);
        expect(array_keys($checklist->items['suppliers']))->not->toContain($noBillingSupplier->id);
    });

    it('includes income sources expecting income this month', function () {
        $monthlySource = IncomeSource::factory()->monthly()->create();
        $annualSourceThisMonth = IncomeSource::factory()->annual(now()->month)->create();
        $annualSourceOtherMonth = IncomeSource::factory()->annual(now()->month === 1 ? 2 : 1)->create();
        $noBillingSource = IncomeSource::factory()->noBilling()->create();

        $this->artisan('checklist:generate', ['--force' => true])
            ->assertSuccessful();

        $checklist = MonthlyChecklist::currentMonth()->first();

        expect(array_keys($checklist->items['income_sources']))->toContain($monthlySource->id);
        expect(array_keys($checklist->items['income_sources']))->toContain($annualSourceThisMonth->id);
        expect(array_keys($checklist->items['income_sources']))->not->toContain($annualSourceOtherMonth->id);
        expect(array_keys($checklist->items['income_sources']))->not->toContain($noBillingSource->id);
    });

    it('validates month parameter', function () {
        $this->artisan('checklist:generate', ['--month' => 13])
            ->assertFailed();
    });
});

describe('IncomeSource Billing Frequency', function () {
    it('creates income sources with no billing by default', function () {
        $incomeSource = IncomeSource::factory()->create();

        expect($incomeSource->is_active)->toBeTrue();
        expect($incomeSource->billing_frequency)->toBe(BillingFrequency::None);
        expect($incomeSource->billing_month)->toBeNull();
    });

    it('can create monthly income sources', function () {
        $incomeSource = IncomeSource::factory()->monthly()->create();

        expect($incomeSource->billing_frequency)->toBe(BillingFrequency::Monthly);
        expect($incomeSource->hasRegularBilling())->toBeTrue();
    });

    it('can create annual income sources', function () {
        $incomeSource = IncomeSource::factory()->annual(6)->create();

        expect($incomeSource->billing_frequency)->toBe(BillingFrequency::Annual);
        expect($incomeSource->billing_month)->toBe(6);
        expect($incomeSource->hasRegularBilling())->toBeTrue();
    });

    it('can find income sources expecting income in a month - monthly', function () {
        IncomeSource::factory()->monthly()->create();
        IncomeSource::factory()->noBilling()->create();

        expect(IncomeSource::expectingIncomeInMonth(1)->count())->toBe(1);
        expect(IncomeSource::expectingIncomeInMonth(6)->count())->toBe(1);
    });

    it('can find income sources expecting income in a month - annual', function () {
        IncomeSource::factory()->annual(6)->create();
        IncomeSource::factory()->annual(12)->create();
        IncomeSource::factory()->noBilling()->create();

        expect(IncomeSource::expectingIncomeInMonth(6)->count())->toBe(1);
        expect(IncomeSource::expectingIncomeInMonth(12)->count())->toBe(1);
        expect(IncomeSource::expectingIncomeInMonth(3)->count())->toBe(0);
    });

    it('knows if income source is expecting income in a given month', function () {
        $monthly = IncomeSource::factory()->monthly()->create();
        $annual = IncomeSource::factory()->annual(6)->create();
        $noBilling = IncomeSource::factory()->noBilling()->create();

        expect($monthly->isExpectingIncomeInMonth(1))->toBeTrue();
        expect($monthly->isExpectingIncomeInMonth(6))->toBeTrue();

        expect($annual->isExpectingIncomeInMonth(6))->toBeTrue();
        expect($annual->isExpectingIncomeInMonth(1))->toBeFalse();

        expect($noBilling->isExpectingIncomeInMonth(1))->toBeFalse();
    });

    it('returns billing month name for income source', function () {
        $june = IncomeSource::factory()->annual(6)->create();
        $noBilling = IncomeSource::factory()->noBilling()->create();

        expect($june->getBillingMonthName())->toBe('June');
        expect($noBilling->getBillingMonthName())->toBeNull();
    });
});
