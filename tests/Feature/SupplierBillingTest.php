<?php

use App\Enums\BillingFrequency;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Supplier Billing Frequency', function () {
    it('creates suppliers with no billing by default', function () {
        $supplier = Supplier::factory()->create();

        expect($supplier->is_active)->toBeTrue();
        expect($supplier->billing_frequency)->toBe(BillingFrequency::None);
        expect($supplier->billing_month)->toBeNull();
    });

    it('can create monthly billing suppliers', function () {
        $supplier = Supplier::factory()->monthly()->create();

        expect($supplier->billing_frequency)->toBe(BillingFrequency::Monthly);
        expect($supplier->hasRegularBilling())->toBeTrue();
    });

    it('can create annual billing suppliers', function () {
        $supplier = Supplier::factory()->annual(6)->create();

        expect($supplier->billing_frequency)->toBe(BillingFrequency::Annual);
        expect($supplier->billing_month)->toBe(6);
        expect($supplier->hasRegularBilling())->toBeTrue();
    });

    it('can create inactive suppliers', function () {
        $supplier = Supplier::factory()->inactive()->create();

        expect($supplier->is_active)->toBeFalse();
    });
});

describe('Supplier Scopes', function () {
    it('can filter active suppliers', function () {
        Supplier::factory()->active()->create();
        Supplier::factory()->inactive()->create();

        expect(Supplier::active()->count())->toBe(1);
    });

    it('can filter suppliers with regular billing', function () {
        Supplier::factory()->noBilling()->create();
        Supplier::factory()->monthly()->create();
        Supplier::factory()->annual(3)->create();

        expect(Supplier::withRegularBilling()->count())->toBe(2);
    });

    it('can find suppliers expecting bill in a month - monthly', function () {
        Supplier::factory()->monthly()->create();
        Supplier::factory()->noBilling()->create();

        expect(Supplier::expectingBillInMonth(1)->count())->toBe(1);
        expect(Supplier::expectingBillInMonth(6)->count())->toBe(1);
        expect(Supplier::expectingBillInMonth(12)->count())->toBe(1);
    });

    it('can find suppliers expecting bill in a month - annual', function () {
        Supplier::factory()->annual(6)->create();
        Supplier::factory()->annual(12)->create();
        Supplier::factory()->noBilling()->create();

        expect(Supplier::expectingBillInMonth(6)->count())->toBe(1);
        expect(Supplier::expectingBillInMonth(12)->count())->toBe(1);
        expect(Supplier::expectingBillInMonth(3)->count())->toBe(0);
    });

    it('can find suppliers expecting bill in a month - mixed', function () {
        Supplier::factory()->monthly()->create();
        Supplier::factory()->annual(6)->create();
        Supplier::factory()->annual(12)->create();
        Supplier::factory()->noBilling()->create();

        expect(Supplier::expectingBillInMonth(6)->count())->toBe(2);
        expect(Supplier::expectingBillInMonth(12)->count())->toBe(2);
        expect(Supplier::expectingBillInMonth(3)->count())->toBe(1);
    });

    it('excludes inactive suppliers from expecting bill scope', function () {
        Supplier::factory()->monthly()->inactive()->create();
        Supplier::factory()->annual(6)->inactive()->create();
        Supplier::factory()->monthly()->active()->create();

        expect(Supplier::expectingBillInMonth(6)->count())->toBe(1);
    });
});

describe('Supplier Instance Methods', function () {
    it('knows if supplier has regular billing', function () {
        $noBilling = Supplier::factory()->noBilling()->create();
        $monthly = Supplier::factory()->monthly()->create();
        $annual = Supplier::factory()->annual(3)->create();

        expect($noBilling->hasRegularBilling())->toBeFalse();
        expect($monthly->hasRegularBilling())->toBeTrue();
        expect($annual->hasRegularBilling())->toBeTrue();
    });

    it('knows if supplier is expecting bill in a given month', function () {
        $monthly = Supplier::factory()->monthly()->create();
        $annual = Supplier::factory()->annual(6)->create();
        $noBilling = Supplier::factory()->noBilling()->create();
        $inactive = Supplier::factory()->monthly()->inactive()->create();

        expect($monthly->isExpectingBillInMonth(1))->toBeTrue();
        expect($monthly->isExpectingBillInMonth(6))->toBeTrue();
        expect($monthly->isExpectingBillInMonth(12))->toBeTrue();

        expect($annual->isExpectingBillInMonth(6))->toBeTrue();
        expect($annual->isExpectingBillInMonth(1))->toBeFalse();
        expect($annual->isExpectingBillInMonth(12))->toBeFalse();

        expect($noBilling->isExpectingBillInMonth(1))->toBeFalse();
        expect($inactive->isExpectingBillInMonth(1))->toBeFalse();
    });

    it('returns billing month name', function () {
        $january = Supplier::factory()->annual(1)->create();
        $june = Supplier::factory()->annual(6)->create();
        $december = Supplier::factory()->annual(12)->create();
        $noBilling = Supplier::factory()->noBilling()->create();

        expect($january->getBillingMonthName())->toBe('January');
        expect($june->getBillingMonthName())->toBe('June');
        expect($december->getBillingMonthName())->toBe('December');
        expect($noBilling->getBillingMonthName())->toBeNull();
    });
});

describe('BillingFrequency Enum', function () {
    it('has correct labels', function () {
        expect(BillingFrequency::None->label())->toBe('No regular billing');
        expect(BillingFrequency::Monthly->label())->toBe('Monthly');
        expect(BillingFrequency::Annual->label())->toBe('Annual');
    });

    it('knows if it has regular billing', function () {
        expect(BillingFrequency::None->hasRegularBilling())->toBeFalse();
        expect(BillingFrequency::Monthly->hasRegularBilling())->toBeTrue();
        expect(BillingFrequency::Annual->hasRegularBilling())->toBeTrue();
    });
});
