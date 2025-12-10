<?php

namespace App\Jobs;

use App\Enums\InvoiceItemUnit;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessBillImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Bill $bill
    ) {}

    public function handle(BillExtractionService $extractionService): void
    {
        if (! $this->bill->original_file_path) {
            $this->bill->markAsFailed('No original file path specified');

            return;
        }

        $pdfPath = Storage::disk('local')->path($this->bill->original_file_path);

        if (! file_exists($pdfPath)) {
            $this->bill->markAsFailed('Original PDF file not found');

            return;
        }

        try {
            $extracted = $extractionService->extractFromPdf($pdfPath);

            $supplier = $this->findOrCreateSupplier($extracted);

            $billDate = ! empty($extracted['bill_date'])
                ? \Carbon\Carbon::parse($extracted['bill_date'])
                : null;

            $dueDate = ! empty($extracted['due_date'])
                ? \Carbon\Carbon::parse($extracted['due_date'])
                : null;

            $this->bill->update([
                'supplier_id' => $supplier?->id,
                'bill_number' => $extracted['bill_number'] ?? null,
                'bill_date' => $billDate,
                'due_date' => $dueDate,
                'total_amount' => $extracted['total_amount'] ?? 0,
                'currency' => $extracted['currency'] ?? 'EUR',
                'extracted_data' => $extracted,
                'notes' => $extracted['notes'] ?? null,
            ]);

            $items = $extracted['items'] ?? [];
            foreach ($items as $item) {
                $description = $item['description'] ?? 'Imported item';

                $this->bill->items()->create([
                    'description' => $description,
                    'unit' => $this->guessUnit($description),
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total' => $item['total'] ?? 0,
                ]);
            }

            $isPaid = $extracted['is_paid'] ?? false;

            if ($isPaid) {
                $this->bill->markAsPaidNeedsReview();
            } else {
                $this->bill->markAsExtracted();
            }

            Log::info('Bill extraction completed', [
                'bill_id' => $this->bill->id,
                'bill_number' => $extracted['bill_number'] ?? 'unknown',
                'supplier' => $supplier?->name ?? 'unknown',
                'detected_as_paid' => $isPaid,
            ]);
        } catch (Throwable $e) {
            Log::error('Bill extraction failed', [
                'bill_id' => $this->bill->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->bill->markAsFailed($exception->getMessage());

        Log::error('Bill import job failed permanently', [
            'bill_id' => $this->bill->id,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function findOrCreateSupplier(array $extracted): ?Supplier
    {
        $supplierName = $extracted['supplier_name'] ?? null;
        $supplierTaxId = $extracted['supplier_tax_id'] ?? null;

        if (! $supplierName && ! $supplierTaxId) {
            return null;
        }

        if ($supplierTaxId) {
            $supplier = Supplier::query()
                ->where('tax_id', $supplierTaxId)
                ->first();

            if ($supplier) {
                return $supplier;
            }
        }

        if ($supplierName) {
            $supplier = Supplier::query()
                ->where('name', $supplierName)
                ->first();

            if ($supplier) {
                if ($supplierTaxId && ! $supplier->tax_id) {
                    $supplier->update(['tax_id' => $supplierTaxId]);
                }

                return $supplier;
            }
        }

        return Supplier::create([
            'name' => $supplierName ?? 'Unknown Supplier',
            'tax_id' => $supplierTaxId,
            'address' => $extracted['supplier_address'] ?? null,
            'email' => $extracted['supplier_email'] ?? null,
        ]);
    }

    protected function guessUnit(string $description): InvoiceItemUnit
    {
        $description = strtolower($description);

        if (preg_match('/\b(days?|días?|dia)\b/i', $description)) {
            return InvoiceItemUnit::Days;
        }

        if (preg_match('/\b(hours?|horas?|hrs?)\b/i', $description)) {
            return InvoiceItemUnit::Hours;
        }

        return InvoiceItemUnit::Units;
    }
}
