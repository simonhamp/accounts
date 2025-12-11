<?php

namespace App\Jobs;

use App\Enums\InvoiceItemUnit;
use App\Exceptions\ImportFailedException;
use App\Models\Invoice;
use App\Services\InvoiceExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInvoiceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function handle(InvoiceExtractionService $extractionService): void
    {
        if (! $this->invoice->original_file_path) {
            throw ImportFailedException::missingFilePath($this->invoice);
        }

        $pdfPath = Storage::disk('local')->path($this->invoice->original_file_path);

        if (! file_exists($pdfPath)) {
            throw ImportFailedException::fileNotFound($this->invoice, $pdfPath);
        }

        try {
            $extracted = $extractionService->extractFromPdf($pdfPath);

            $invoiceDate = ! empty($extracted['invoice_date'])
                ? \Carbon\Carbon::parse($extracted['invoice_date'])
                : null;

            $this->invoice->update([
                'invoice_number' => $extracted['invoice_number'] ?? null,
                'invoice_date' => $invoiceDate,
                'period_month' => $invoiceDate?->month,
                'period_year' => $invoiceDate?->year,
                'customer_name' => $extracted['customer_name'] ?? null,
                'customer_tax_id' => $extracted['customer_tax_id'] ?? null,
                'total_amount' => $extracted['total_amount'] ?? 0,
                'currency' => $extracted['currency'] ?? 'EUR',
                'extracted_data' => $extracted,
            ]);

            $items = $extracted['items'] ?? [];
            foreach ($items as $item) {
                $description = $item['description'] ?? 'Imported item';

                $this->invoice->items()->create([
                    'stripe_transaction_id' => null,
                    'description' => $description,
                    'unit' => $this->guessUnit($description),
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['total'] ?? 0,
                ]);
            }

            $this->invoice->markAsExtracted();

            Log::info('Invoice extraction completed', [
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $extracted['invoice_number'] ?? 'unknown',
            ]);
        } catch (Throwable $e) {
            Log::error('Invoice extraction failed', [
                'invoice_id' => $this->invoice->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->invoice->markAsFailed($exception->getMessage());

        Log::error('Invoice import job failed permanently', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function guessUnit(string $description): InvoiceItemUnit
    {
        $description = strtolower($description);

        // Check for days (English and Spanish)
        if (preg_match('/\b(days?|días?|dia)\b/i', $description)) {
            return InvoiceItemUnit::Days;
        }

        // Check for hours (English and Spanish)
        if (preg_match('/\b(hours?|horas?|hrs?)\b/i', $description)) {
            return InvoiceItemUnit::Hours;
        }

        // Default to units
        return InvoiceItemUnit::Units;
    }
}
