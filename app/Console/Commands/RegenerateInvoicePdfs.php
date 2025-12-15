<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class RegenerateInvoicePdfs extends Command
{
    protected $signature = 'invoices:regenerate-pdfs';

    protected $description = 'Regenerate PDFs for all finalized invoices';

    public function handle(InvoiceService $invoiceService): int
    {
        $invoices = Invoice::query()
            ->whereNotNull('invoice_number')
            ->orderBy('invoice_date')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No finalized invoices found.');

            return self::SUCCESS;
        }

        $this->info("Regenerating PDFs for {$invoices->count()} invoice(s)...");
        $this->newLine();

        $bar = $this->output->createProgressBar($invoices->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            try {
                $invoiceService->regeneratePdf($invoice);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed to regenerate {$invoice->invoice_number}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Regenerated: {$success}");

        if ($failed > 0) {
            $this->error("✗ Failed: {$failed}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
