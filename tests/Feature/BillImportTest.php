<?php

use App\Enums\BillStatus;
use App\Jobs\ProcessBillImport;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Bill Status', function () {
    it('creates bills with pending status by default', function () {
        $bill = Bill::factory()->create();

        expect($bill->status)->toBe(BillStatus::Pending);
        expect($bill->isPending())->toBeTrue();
    });

    it('can create paid bills', function () {
        $bill = Bill::factory()->paid()->create();

        expect($bill->status)->toBe(BillStatus::Paid);
        expect($bill->isPaid())->toBeTrue();
        expect($bill->isPending())->toBeFalse();
    });

    it('can create paid needs review bills', function () {
        $bill = Bill::factory()->paidNeedsReview()->create();

        expect($bill->status)->toBe(BillStatus::PaidNeedsReview);
        expect($bill->isPending())->toBeTrue();
        expect($bill->needsReview())->toBeTrue();
    });

    it('can mark bill as extracted', function () {
        $bill = Bill::factory()->pending()->create();

        $bill->markAsExtracted();

        expect($bill->fresh()->status)->toBe(BillStatus::Extracted);
    });

    it('can mark bill as reviewed', function () {
        $bill = Bill::factory()->extracted()->create();

        $bill->markAsReviewed();

        expect($bill->fresh()->status)->toBe(BillStatus::Reviewed);
    });

    it('can mark bill as paid', function () {
        $bill = Bill::factory()->reviewed()->create();

        $bill->markAsPaid();

        expect($bill->fresh()->status)->toBe(BillStatus::Paid);
    });

    it('can mark bill as paid needs review', function () {
        $bill = Bill::factory()->pending()->create();

        $bill->markAsPaidNeedsReview();

        expect($bill->fresh()->status)->toBe(BillStatus::PaidNeedsReview);
    });

    it('can mark bill as failed with message', function () {
        $bill = Bill::factory()->pending()->create();

        $bill->markAsFailed('Test error message');

        $bill->refresh();
        expect($bill->status)->toBe(BillStatus::Failed);
        expect($bill->error_message)->toBe('Test error message');
    });
});

describe('Bill Scopes', function () {
    it('can filter pending bills', function () {
        Bill::factory()->pending()->create();
        Bill::factory()->extracted()->create();
        Bill::factory()->reviewed()->create();
        Bill::factory()->paidNeedsReview()->create();
        Bill::factory()->paid()->create();

        expect(Bill::pending()->count())->toBe(4);
    });

    it('can filter paid bills', function () {
        Bill::factory()->pending()->create();
        Bill::factory()->paid()->create();

        expect(Bill::paid()->count())->toBe(1);
    });

    it('can filter bills awaiting review', function () {
        Bill::factory()->pending()->create();
        Bill::factory()->extracted()->create();
        Bill::factory()->paidNeedsReview()->create();
        Bill::factory()->paid()->create();

        expect(Bill::awaitingReview()->count())->toBe(2);
    });

    it('can filter failed bills', function () {
        Bill::factory()->failed()->create();
        Bill::factory()->pending()->create();

        expect(Bill::failed()->count())->toBe(1);
    });
});

describe('Bill Payment Status', function () {
    it('can determine if bill can be paid', function () {
        $pending = Bill::factory()->pending()->create();
        $extracted = Bill::factory()->extracted()->create();
        $reviewed = Bill::factory()->reviewed()->create();
        $paid = Bill::factory()->paid()->create();
        $paidNeedsReview = Bill::factory()->paidNeedsReview()->create();

        expect($pending->canBePaid())->toBeFalse();
        expect($extracted->canBePaid())->toBeFalse();
        expect($reviewed->canBePaid())->toBeTrue();
        expect($paid->canBePaid())->toBeFalse();
        expect($paidNeedsReview->canBePaid())->toBeFalse();
    });

    it('identifies bills needing review', function () {
        $extracted = Bill::factory()->extracted()->create();
        $paidNeedsReview = Bill::factory()->paidNeedsReview()->create();
        $reviewed = Bill::factory()->reviewed()->create();
        $paid = Bill::factory()->paid()->create();

        expect($extracted->needsReview())->toBeTrue();
        expect($paidNeedsReview->needsReview())->toBeTrue();
        expect($reviewed->needsReview())->toBeFalse();
        expect($paid->needsReview())->toBeFalse();
    });
});

describe('Process Bill Import Job', function () {
    it('fails if no original file path', function () {
        $bill = Bill::factory()->pending()->create([
            'original_file_path' => null,
        ]);

        $job = new ProcessBillImport($bill);
        $job->handle(app(BillExtractionService::class));

        $bill->refresh();
        expect($bill->status)->toBe(BillStatus::Failed);
        expect($bill->error_message)->toBe('No original file path specified');
    });

    it('fails if file does not exist', function () {
        Storage::fake('local');

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'non-existent-file.pdf',
        ]);

        $job = new ProcessBillImport($bill);
        $job->handle(app(BillExtractionService::class));

        $bill->refresh();
        expect($bill->status)->toBe(BillStatus::Failed);
        expect($bill->error_message)->toBe('Original PDF file not found');
    });

    it('creates supplier during extraction if not found', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-bill.pdf', 'fake pdf content');

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'test-bill.pdf',
            'supplier_id' => null,
        ]);

        $extractedData = [
            'supplier_name' => 'Acme Corp',
            'supplier_tax_id' => 'B12345678',
            'supplier_email' => 'invoices@acme.com',
            'bill_number' => 'BILL-001',
            'bill_date' => '2025-01-15',
            'total_amount' => 25000,
            'currency' => 'EUR',
            'items' => [],
        ];

        $mockExtractionService = mock(BillExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        expect(Supplier::count())->toBe(0);

        $job = new ProcessBillImport($bill);
        $job->handle($mockExtractionService);

        expect(Supplier::count())->toBe(1);

        $supplier = Supplier::first();
        expect($supplier->name)->toBe('Acme Corp');
        expect($supplier->tax_id)->toBe('B12345678');
        expect($supplier->email)->toBe('invoices@acme.com');

        $bill->refresh();
        expect($bill->supplier_id)->toBe($supplier->id);
    });

    it('finds existing supplier by tax id', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-bill.pdf', 'fake pdf content');

        $existingSupplier = Supplier::factory()->create([
            'name' => 'Old Name',
            'tax_id' => 'B12345678',
        ]);

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'test-bill.pdf',
            'supplier_id' => null,
        ]);

        $extractedData = [
            'supplier_name' => 'New Name',
            'supplier_tax_id' => 'B12345678',
            'bill_number' => 'BILL-001',
            'bill_date' => '2025-01-15',
            'total_amount' => 25000,
            'currency' => 'EUR',
            'items' => [],
        ];

        $mockExtractionService = mock(BillExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        $job = new ProcessBillImport($bill);
        $job->handle($mockExtractionService);

        expect(Supplier::count())->toBe(1);

        $bill->refresh();
        expect($bill->supplier_id)->toBe($existingSupplier->id);
    });

    it('creates line items during extraction', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-bill.pdf', 'fake pdf content');

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'test-bill.pdf',
        ]);

        $extractedData = [
            'supplier_name' => 'Test Supplier',
            'bill_number' => 'BILL-001',
            'bill_date' => '2025-01-15',
            'total_amount' => 25000,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Office Supplies',
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'total' => 20000,
                ],
                [
                    'description' => 'Delivery Fee',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'total' => 5000,
                ],
            ],
        ];

        $mockExtractionService = mock(BillExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        $job = new ProcessBillImport($bill);
        $job->handle($mockExtractionService);

        $bill->refresh();

        expect($bill->status)->toBe(BillStatus::Extracted);
        expect($bill->items()->count())->toBe(2);

        $firstItem = $bill->items->first();
        expect($firstItem->description)->toBe('Office Supplies');
        expect($firstItem->quantity)->toBe(2);
        expect($firstItem->unit_price)->toBe(10000);
        expect($firstItem->total)->toBe(20000);
    });

    it('marks bill as paid needs review when is_paid is true', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-bill.pdf', 'fake pdf content');

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'test-bill.pdf',
        ]);

        $extractedData = [
            'supplier_name' => 'Test Supplier',
            'bill_number' => 'BILL-002',
            'bill_date' => '2025-01-15',
            'total_amount' => 15000,
            'currency' => 'EUR',
            'is_paid' => true,
            'items' => [],
        ];

        $mockExtractionService = mock(BillExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        $job = new ProcessBillImport($bill);
        $job->handle($mockExtractionService);

        $bill->refresh();

        expect($bill->status)->toBe(BillStatus::PaidNeedsReview);
        expect($bill->needsReview())->toBeTrue();
    });

    it('marks bill as extracted when is_paid is false', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-bill.pdf', 'fake pdf content');

        $bill = Bill::factory()->pending()->create([
            'original_file_path' => 'test-bill.pdf',
        ]);

        $extractedData = [
            'supplier_name' => 'Test Supplier',
            'bill_number' => 'BILL-003',
            'bill_date' => '2025-01-15',
            'total_amount' => 15000,
            'currency' => 'EUR',
            'is_paid' => false,
            'items' => [],
        ];

        $mockExtractionService = mock(BillExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        $job = new ProcessBillImport($bill);
        $job->handle($mockExtractionService);

        $bill->refresh();

        expect($bill->status)->toBe(BillStatus::Extracted);
        expect($bill->needsReview())->toBeTrue();
    });
});

describe('Batch Import', function () {
    it('dispatches jobs for batch import', function () {
        Queue::fake();

        $bill1 = Bill::factory()->pending()->create([
            'original_file_path' => 'test1.pdf',
        ]);

        $bill2 = Bill::factory()->pending()->create([
            'original_file_path' => 'test2.pdf',
        ]);

        ProcessBillImport::dispatch($bill1);
        ProcessBillImport::dispatch($bill2);

        Queue::assertPushed(ProcessBillImport::class, 2);
    });
});

describe('Bill Line Items', function () {
    it('can add line items to a bill', function () {
        $bill = Bill::factory()->create();

        expect($bill->items()->count())->toBe(0);

        $bill->items()->create([
            'description' => 'Test Item',
            'quantity' => 2,
            'unit_price' => 5000,
            'total' => 10000,
        ]);

        expect($bill->items()->count())->toBe(1);
        expect($bill->items->first()->description)->toBe('Test Item');
        expect($bill->items->first()->quantity)->toBe(2);
        expect($bill->items->first()->unit_price)->toBe(5000);
        expect($bill->items->first()->total)->toBe(10000);
    });

    it('deletes line items when bill is deleted', function () {
        $bill = Bill::factory()->create();

        $bill->items()->create([
            'description' => 'Test Item',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        expect(\App\Models\BillItem::count())->toBe(1);

        $bill->delete();

        expect(\App\Models\BillItem::count())->toBe(0);
    });
});

describe('Supplier Relationship', function () {
    it('bill belongs to supplier', function () {
        $supplier = Supplier::factory()->create();
        $bill = Bill::factory()->create([
            'supplier_id' => $supplier->id,
        ]);

        expect($bill->supplier)->toBeInstanceOf(Supplier::class);
        expect($bill->supplier->id)->toBe($supplier->id);
    });

    it('supplier has many bills', function () {
        $supplier = Supplier::factory()->create();

        Bill::factory()->count(3)->create([
            'supplier_id' => $supplier->id,
        ]);

        expect($supplier->bills()->count())->toBe(3);
    });
});

describe('Bill Original PDF Route', function () {
    it('shows original pdf when available', function () {
        Storage::fake('local');
        Storage::disk('local')->put('bills/test.pdf', 'fake pdf content');

        $bill = Bill::factory()->create([
            'original_file_path' => 'bills/test.pdf',
        ]);

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('bills.original-pdf', ['bill' => $bill]))
            ->assertOk();
    });

    it('returns 404 when no original file path', function () {
        $bill = Bill::factory()->create([
            'original_file_path' => null,
        ]);

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('bills.original-pdf', ['bill' => $bill]))
            ->assertNotFound();
    });

    it('returns 404 when file does not exist', function () {
        Storage::fake('local');

        $bill = Bill::factory()->create([
            'original_file_path' => 'non-existent.pdf',
        ]);

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('bills.original-pdf', ['bill' => $bill]))
            ->assertNotFound();
    });
});
