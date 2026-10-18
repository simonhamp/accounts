<?php

namespace App\Jobs;

use App\Exceptions\BrandMailException;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Email a finalized invoice to its customer from the queue, through its
 * brand's own mail server. The PDF is made first if it is missing or out of
 * date, and the invoice is only marked as sent once the mail server has
 * accepted the message. Dispatching waits for any open database transaction
 * to commit, so an invoice that gets rolled back is never emailed.
 *
 * An invoice that can no longer be emailed when the job runs, e.g. because
 * its brand turned emails off, is skipped and stays as it is.
 */
class SendInvoiceEmail implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public Invoice $invoice
    ) {}

    public function handle(InvoiceService $invoiceService): void
    {
        if (! $this->invoice->canBeEmailed()) {
            return;
        }

        $invoiceService->ensurePdfIsCurrent($this->invoice);

        $brand = $this->invoice->brand;
        $recipient = $this->invoice->customer_email;

        try {
            $brand->mailer()->to($recipient)->send(new InvoiceMail($this->invoice));
        } catch (Throwable $e) {
            throw BrandMailException::from($brand, $e, "Couldn't send invoice {$this->invoice->invoice_number}");
        }

        $this->invoice->markAsEmailed($recipient);
    }
}
