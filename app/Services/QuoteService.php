<?php

namespace App\Services;

use App\Enums\InvoiceItemUnit;
use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use App\Models\QuoteItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuoteService
{
    public function generateAndStorePdf(Quote $quote): void
    {
        $quote->load(['person', 'customer', 'items']);

        $directory = "quotes/{$quote->person->invoice_prefix}/{$quote->quote_date->year}";
        Storage::makeDirectory($directory);

        $pdfEs = Pdf::loadView('quotes.pdf-es', ['quote' => $quote]);
        $pathEs = "{$directory}/{$quote->quote_number}.pdf";
        Storage::put($pathEs, $pdfEs->output());

        $pdfEn = Pdf::loadView('quotes.pdf-en', ['quote' => $quote]);
        $pathEn = "{$directory}/{$quote->quote_number}-en.pdf";
        Storage::put($pathEn, $pdfEn->output());

        $quote->update([
            'pdf_path' => $pathEs,
            'pdf_path_en' => $pathEn,
            'generated_at' => now(),
        ]);
    }

    /**
     * Create an Invoice from an accepted Quote. The invoice is created in the
     * Reviewed state with a freshly allocated invoice number and today's date
     * so it flows through the normal finalize/send lifecycle.
     */
    public function convertToInvoice(Quote $quote): Invoice
    {
        if (! $quote->canCreateInvoice()) {
            throw new \Exception('Only accepted quotes can be converted to an invoice.');
        }

        return DB::transaction(function () use ($quote) {
            $person = $quote->person;

            $invoice = Invoice::create([
                'person_id' => $quote->person_id,
                'customer_id' => $quote->customer_id,
                'invoice_number' => $person->allocateNextInvoiceNumber(),
                'invoice_date' => now(),
                'customer_name' => $quote->customer_name,
                'customer_address' => $quote->customer_address,
                'customer_tax_id' => $quote->customer_tax_id,
                'currency' => $quote->currency,
                'irpf_rate' => $quote->irpf_rate,
                'legal_notes' => $quote->legal_notes,
                'total_amount' => 0,
                'status' => InvoiceStatus::Reviewed,
            ]);

            foreach ($quote->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item->description,
                    'unit' => $item->unit ?? InvoiceItemUnit::Units,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $item->tax_rate,
                ]);
            }

            $invoice->recalculateTotal();

            $quote->markAsInvoiced($invoice);

            return $invoice;
        });
    }

    /**
     * Create a fresh Draft copy of a quote with its own quote number, dated
     * today, with all line items copied and any generated PDFs left behind.
     */
    public function duplicate(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote) {
            $copy = $quote->replicate(except: [
                'quote_number',
                'invoice_id',
                'pdf_path',
                'pdf_path_en',
                'generated_at',
                'status',
                'amount_eur',
            ]);

            $copy->quote_number = $quote->person->allocateNextQuoteNumber();
            $copy->quote_date = now();
            $copy->status = QuoteStatus::Draft;
            $copy->save();

            foreach ($quote->items as $item) {
                QuoteItem::create([
                    'quote_id' => $copy->id,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $item->tax_rate,
                ]);
            }

            $copy->recalculateTotal();

            return $copy->fresh();
        });
    }
}
