<?php

namespace App\Jobs;

use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Email a finalized invoice to its customer from the queue. The PDF is made
 * first if it is missing or out of date, and the invoice is only marked as
 * sent once the mailer has accepted the message.
 */
class SendInvoiceEmail implements ShouldQueue
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

        $recipient = $this->invoice->customer_email;

        Mail::to($recipient)->send(new InvoiceMail($this->invoice));

        $this->invoice->markAsEmailed($recipient);
    }
}
