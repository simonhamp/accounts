<?php

use App\Enums\InvoiceStatus;
use App\Jobs\ProcessInvoiceImport;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Person;
use App\Services\InvoiceExtractionService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Invoice Status', function () {
    it('creates invoices with finalized status by default', function () {
        $invoice = Invoice::factory()->create();

        expect($invoice->status)->toBe(InvoiceStatus::Finalized);
        expect($invoice->isFinalized())->toBeTrue();
        expect($invoice->isPending())->toBeFalse();
    });

    it('can create pending invoices', function () {
        $invoice = Invoice::factory()->pending()->create();

        expect($invoice->status)->toBe(InvoiceStatus::Pending);
        expect($invoice->isPending())->toBeTrue();
        expect($invoice->person_id)->toBeNull();
        expect($invoice->invoice_number)->toBeNull();
    });

    it('can mark invoice as extracted', function () {
        $invoice = Invoice::factory()->pending()->create();

        $invoice->markAsExtracted();

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Extracted);
    });

    it('can mark invoice as reviewed', function () {
        $invoice = Invoice::factory()->extracted()->create();

        $invoice->markAsReviewed();

        expect($invoice->fresh()->status)->toBe(InvoiceStatus::Reviewed);
    });

    it('can mark invoice as failed with message', function () {
        $invoice = Invoice::factory()->pending()->create();

        $invoice->markAsFailed('Test error message');

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::Failed);
        expect($invoice->error_message)->toBe('Test error message');
    });
});

describe('Invoice Scopes', function () {
    it('can filter pending invoices', function () {
        Invoice::factory()->pending()->create();
        Invoice::factory()->extracted()->create();
        Invoice::factory()->reviewed()->create();
        Invoice::factory()->create(); // finalized

        expect(Invoice::pending()->count())->toBe(3);
    });

    it('can filter finalized invoices', function () {
        Invoice::factory()->pending()->create();
        Invoice::factory()->create(); // finalized

        expect(Invoice::finalized()->count())->toBe(1);
    });

    it('can filter failed invoices', function () {
        Invoice::factory()->failed()->create();
        Invoice::factory()->create();

        expect(Invoice::failed()->count())->toBe(1);
    });
});

describe('Invoice Finalization', function () {
    it('can determine if invoice can be finalized', function () {
        $pending = Invoice::factory()->pending()->create();
        $extracted = Invoice::factory()->extracted()->create();
        $reviewed = Invoice::factory()->reviewed()->create();
        $finalized = Invoice::factory()->create();

        expect($pending->canBeFinalized())->toBeFalse();
        expect($extracted->canBeFinalized())->toBeFalse();
        expect($reviewed->canBeFinalized())->toBeTrue();
        expect($finalized->canBeFinalized())->toBeFalse();
    });

    it('throws exception when finalizing invoice in wrong state', function () {
        $invoice = Invoice::factory()->extracted()->create();

        $service = app(InvoiceService::class);
        $service->finalizeImportedInvoice($invoice);
    })->throws(Exception::class, 'Invoice cannot be finalized in its current state.');

    it('throws exception when finalizing invoice without person', function () {
        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => null,
        ]);

        $service = app(InvoiceService::class);
        $service->finalizeImportedInvoice($invoice);
    })->throws(Exception::class, 'Invoice must be assigned to a person before finalizing.');

    it('generates invoice number on finalization if not set', function () {
        $person = Person::factory()->create([
            'invoice_prefix' => 'TEST',
            'next_invoice_number' => 1,
        ]);

        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => $person->id,
            'invoice_number' => null,
        ]);

        Storage::fake('local');

        $service = app(InvoiceService::class);
        $service->finalizeImportedInvoice($invoice);

        $invoice->refresh();
        expect($invoice->invoice_number)->toBe('TEST-00001');
        expect($invoice->status)->toBe(InvoiceStatus::Finalized);
        expect($person->fresh()->next_invoice_number)->toBe(2);
    });

    it('finalizes invoice with existing line items', function () {
        $person = Person::factory()->create();
        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => $person->id,
        ]);

        $invoice->items()->create([
            'description' => 'Test Service',
            'quantity' => 2,
            'unit_price' => 5000,
            'total' => 10000,
        ]);

        $invoice->items()->create([
            'description' => 'Another Service',
            'quantity' => 1,
            'unit_price' => 15000,
            'total' => 15000,
        ]);

        Storage::fake('local');

        $service = app(InvoiceService::class);
        $service->finalizeImportedInvoice($invoice);

        expect($invoice->items()->count())->toBe(2);
        expect($invoice->fresh()->isFinalized())->toBeTrue();
    });
});

describe('Process Invoice Import Job', function () {
    it('fails if no original file path', function () {
        $invoice = Invoice::factory()->pending()->create([
            'original_file_path' => null,
        ]);

        $job = new ProcessInvoiceImport($invoice);
        $job->handle(app(InvoiceExtractionService::class));

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::Failed);
        expect($invoice->error_message)->toBe('No original file path specified');
    });

    it('fails if file does not exist', function () {
        Storage::fake('local');

        $invoice = Invoice::factory()->pending()->create([
            'original_file_path' => 'non-existent-file.pdf',
        ]);

        $job = new ProcessInvoiceImport($invoice);
        $job->handle(app(InvoiceExtractionService::class));

        $invoice->refresh();
        expect($invoice->status)->toBe(InvoiceStatus::Failed);
        expect($invoice->error_message)->toBe('Original PDF file not found');
    });

    it('creates line items during extraction', function () {
        Storage::fake('local');
        Storage::disk('local')->put('test-invoice.pdf', 'fake pdf content');

        $invoice = Invoice::factory()->pending()->create([
            'original_file_path' => 'test-invoice.pdf',
        ]);

        $extractedData = [
            'invoice_number' => 'INV-001',
            'invoice_date' => '2025-01-15',
            'customer_name' => 'Test Customer',
            'total_amount' => 25000,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Consulting Services',
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'total' => 20000,
                ],
                [
                    'description' => 'Support Fee',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'total' => 5000,
                ],
            ],
        ];

        $mockExtractionService = mock(InvoiceExtractionService::class);
        $mockExtractionService->shouldReceive('extractFromPdf')
            ->once()
            ->andReturn($extractedData);

        $job = new ProcessInvoiceImport($invoice);
        $job->handle($mockExtractionService);

        $invoice->refresh();

        expect($invoice->status)->toBe(InvoiceStatus::Extracted);
        expect($invoice->items()->count())->toBe(2);

        $firstItem = $invoice->items->first();
        expect($firstItem->description)->toBe('Consulting Services');
        expect($firstItem->quantity)->toBe('2.0000');
        expect($firstItem->unit_price)->toBe(10000);
        expect($firstItem->total)->toBe(20000);

        $secondItem = $invoice->items->last();
        expect($secondItem->description)->toBe('Support Fee');
        expect($secondItem->quantity)->toBe('1.0000');
        expect($secondItem->total)->toBe(5000);
    });
});

describe('Batch Import', function () {
    it('dispatches jobs for batch import', function () {
        Queue::fake();

        $invoice1 = Invoice::factory()->pending()->create([
            'original_file_path' => 'test1.pdf',
        ]);

        $invoice2 = Invoice::factory()->pending()->create([
            'original_file_path' => 'test2.pdf',
        ]);

        ProcessInvoiceImport::dispatch($invoice1);
        ProcessInvoiceImport::dispatch($invoice2);

        Queue::assertPushed(ProcessInvoiceImport::class, 2);
    });
});

describe('Invoice Line Items', function () {
    it('can add line items to an invoice', function () {
        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
        ]);

        expect($invoice->items()->count())->toBe(0);

        $invoice->items()->create([
            'description' => 'Test Service',
            'quantity' => 2,
            'unit_price' => 5000,
            'total' => 10000,
        ]);

        expect($invoice->items()->count())->toBe(1);
        expect($invoice->items->first()->description)->toBe('Test Service');
        expect($invoice->items->first()->quantity)->toBe('2.0000');
        expect($invoice->items->first()->unit_price)->toBe(5000);
        expect($invoice->items->first()->total)->toBe(10000);
    });

    it('can update existing line items', function () {
        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
        ]);

        $item = $invoice->items()->create([
            'description' => 'Original Description',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        $item->update([
            'description' => 'Updated Description',
            'quantity' => 3,
            'unit_price' => 2000,
            'total' => 6000,
        ]);

        $item->refresh();
        expect($item->description)->toBe('Updated Description');
        expect($item->quantity)->toBe('3.0000');
        expect($item->total)->toBe(6000);
    });

    it('can delete line items from an invoice', function () {
        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
        ]);

        $item = $invoice->items()->create([
            'description' => 'To Be Deleted',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        expect($invoice->items()->count())->toBe(1);

        $item->delete();

        expect($invoice->items()->count())->toBe(0);
    });
});

describe('Dual Language PDF Generation', function () {
    it('generates both Spanish and English PDFs on finalization', function () {
        Storage::fake('local');

        $person = Person::factory()->create([
            'invoice_prefix' => 'TEST',
            'next_invoice_number' => 1,
        ]);

        $invoice = Invoice::factory()->reviewed()->create([
            'person_id' => $person->id,
            'invoice_number' => null,
        ]);

        $service = app(InvoiceService::class);
        $service->finalizeImportedInvoice($invoice);

        $invoice->refresh();

        expect($invoice->pdf_path)->not->toBeNull();
        expect($invoice->pdf_path_en)->not->toBeNull();
        expect($invoice->pdf_path)->toContain('TEST-00001.pdf');
        expect($invoice->pdf_path_en)->toContain('TEST-00001-en.pdf');

        Storage::disk('local')->assertExists($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path_en);
    });

    it('regenerates both PDFs when using regeneratePdf', function () {
        Storage::fake('local');

        $person = Person::factory()->create([
            'invoice_prefix' => 'REGEN',
        ]);

        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
            'invoice_number' => 'REGEN-00001',
            'pdf_path' => null,
            'pdf_path_en' => null,
        ]);

        $service = app(InvoiceService::class);
        $service->regeneratePdf($invoice);

        $invoice->refresh();

        expect($invoice->pdf_path)->not->toBeNull();
        expect($invoice->pdf_path_en)->not->toBeNull();

        Storage::disk('local')->assertExists($invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path_en);
    });

    it('downloads Spanish PDF by default', function () {
        Storage::fake('local');

        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
            'pdf_path' => 'invoices/test/2025/INV-00001.pdf',
            'pdf_path_en' => 'invoices/test/2025/INV-00001-en.pdf',
        ]);

        Storage::put($invoice->pdf_path, 'Spanish PDF content');
        Storage::put($invoice->pdf_path_en, 'English PDF content');

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.download-pdf', ['invoice' => $invoice]))
            ->assertOk()
            ->assertDownload($invoice->invoice_number.'.pdf');
    });

    it('downloads Spanish PDF when language is es', function () {
        Storage::fake('local');

        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
            'pdf_path' => 'invoices/test/2025/INV-00001.pdf',
            'pdf_path_en' => 'invoices/test/2025/INV-00001-en.pdf',
        ]);

        Storage::put($invoice->pdf_path, 'Spanish PDF content');
        Storage::put($invoice->pdf_path_en, 'English PDF content');

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.download-pdf', ['invoice' => $invoice, 'language' => 'es']))
            ->assertOk()
            ->assertDownload($invoice->invoice_number.'.pdf');
    });

    it('downloads English PDF when language is en', function () {
        Storage::fake('local');

        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
            'pdf_path' => 'invoices/test/2025/INV-00001.pdf',
            'pdf_path_en' => 'invoices/test/2025/INV-00001-en.pdf',
        ]);

        Storage::put($invoice->pdf_path, 'Spanish PDF content');
        Storage::put($invoice->pdf_path_en, 'English PDF content');

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.download-pdf', ['invoice' => $invoice, 'language' => 'en']))
            ->assertOk()
            ->assertDownload($invoice->invoice_number.'-en.pdf');
    });

    it('returns 404 when English PDF is not available', function () {
        Storage::fake('local');

        $person = Person::factory()->create();
        $invoice = Invoice::factory()->create([
            'person_id' => $person->id,
            'pdf_path' => 'invoices/test/2025/INV-00001.pdf',
            'pdf_path_en' => null,
        ]);

        Storage::put($invoice->pdf_path, 'Spanish PDF content');

        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('invoices.download-pdf', ['invoice' => $invoice, 'language' => 'en']))
            ->assertNotFound();
    });
});

describe('Invoice Bank Account Relationship', function () {
    it('can associate a bank account with an invoice', function () {
        $bankAccount = BankAccount::factory()->create([
            'name' => 'Business Account',
        ]);

        $invoice = Invoice::factory()->create([
            'bank_account_id' => $bankAccount->id,
        ]);

        expect($invoice->bankAccount)->not->toBeNull();
        expect($invoice->bankAccount->name)->toBe('Business Account');
    });

    it('can have no bank account', function () {
        $invoice = Invoice::factory()->create([
            'bank_account_id' => null,
        ]);

        expect($invoice->bankAccount)->toBeNull();
    });

    it('can check if invoice has payment details', function () {
        $bankAccount = BankAccount::factory()->create();

        $invoiceWithPaymentDetails = Invoice::factory()->create([
            'bank_account_id' => $bankAccount->id,
        ]);

        $invoiceWithoutPaymentDetails = Invoice::factory()->create([
            'bank_account_id' => null,
        ]);

        expect($invoiceWithPaymentDetails->hasPaymentDetails())->toBeTrue();
        expect($invoiceWithoutPaymentDetails->hasPaymentDetails())->toBeFalse();
    });
});
