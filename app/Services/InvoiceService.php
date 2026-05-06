<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Person;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function validatePeriod(Person $person, int $year, int $month): array
    {
        $transactions = $this->getTransactionsForPeriod($person, $year, $month);

        $incomplete = $transactions->filter(fn ($t) => ! $t->isComplete());

        return [
            'valid' => $incomplete->isEmpty(),
            'total_transactions' => $transactions->count(),
            'incomplete_transactions' => $incomplete->count(),
            'incomplete_ids' => $incomplete->pluck('id')->toArray(),
        ];
    }

    public function generateInvoices(Person $person, int $year, int $month): array
    {
        $validation = $this->validatePeriod($person, $year, $month);

        if (! $validation['valid']) {
            throw new \Exception(
                "Cannot generate invoices: {$validation['incomplete_transactions']} incomplete transaction(s) found for this period."
            );
        }

        $transactions = $this->getTransactionsForPeriod($person, $year, $month)
            ->where('status', 'ready');

        if ($transactions->isEmpty()) {
            return [
                'generated' => 0,
                'invoices' => [],
            ];
        }

        $invoices = [];
        $groupedByCustomer = $transactions->groupBy('customer_name');

        foreach ($groupedByCustomer as $customerName => $customerTransactions) {
            $invoice = $this->createInvoice($person, $year, $month, $customerName, $customerTransactions);
            $invoices[] = $invoice;
        }

        return [
            'generated' => count($invoices),
            'invoices' => $invoices,
        ];
    }

    public function generateInvoiceForTransaction(StripeTransaction $transaction): Invoice
    {
        if ($transaction->isInvoiced()) {
            throw new \Exception('Cannot generate invoice: transaction has already been invoiced.');
        }

        if ($transaction->isIgnored()) {
            throw new \Exception('Cannot generate invoice: transaction is marked as ignored.');
        }

        if (! $transaction->isReady()) {
            throw new \Exception('Cannot generate invoice: transaction must be marked as "Ready".');
        }

        $transaction->load('stripeAccount.person');
        $person = $transaction->stripeAccount->person;

        $year = $transaction->transaction_date->year;
        $month = $transaction->transaction_date->month;

        return $this->createInvoice(
            $person,
            $year,
            $month,
            $transaction->customer_name,
            collect([$transaction])
        );
    }

    protected function createInvoice(Person $person, int $year, int $month, ?string $customerName, $transactions): Invoice
    {
        return DB::transaction(function () use ($person, $year, $month, $customerName, $transactions) {
            $firstTransaction = $transactions->first();
            $totalAmount = (int) $transactions->sum('amount');
            $currency = $firstTransaction->currency;

            $amountEur = $currency === 'EUR'
                ? $totalAmount
                : app(ExchangeRateService::class)->convertToEur(
                    $totalAmount,
                    $currency,
                    $firstTransaction->transaction_date,
                );

            $isSimplified = abs($amountEur) <= Invoice::SIMPLIFIED_THRESHOLD_EUR_CENTS;

            $invoice = Invoice::create([
                'person_id' => $person->id,
                'invoice_number' => $person->allocateNextInvoiceNumber(),
                'invoice_date' => $firstTransaction->transaction_date,
                'period_month' => $month,
                'period_year' => $year,
                'customer_name' => $customerName,
                'customer_address' => $firstTransaction->customer_address,
                'customer_tax_id' => null,
                'total_amount' => 0,
                'currency' => $currency,
                'is_simplified' => $isSimplified,
            ]);

            foreach ($transactions as $transaction) {
                $itemTotal = $transaction->amount;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'stripe_transaction_id' => $transaction->id,
                    'description' => $transaction->description,
                    'quantity' => 1,
                    'unit_price' => $transaction->amount,
                    'total' => $itemTotal,
                ]);
            }

            $invoice->update(['total_amount' => $totalAmount]);

            $this->generateAndStorePdf($invoice);

            return $invoice;
        });
    }

    public function regeneratePdf(Invoice $invoice): void
    {
        $this->generateAndStorePdf($invoice);
    }

    /**
     * Generate one invoice per ready, unprocessed Stripe transaction in
     * chronological order so invoice numbers and dates stay consistent.
     *
     * @return array{generated: int, failed: int, errors: array<int, array{transaction_id: int, error: string}>}
     */
    public function generateInvoicesForReadyTransactions(?StripeAccount $account = null): array
    {
        $query = StripeTransaction::query()
            ->where('status', 'ready')
            ->whereDoesntHave('invoiceItem')
            ->whereDoesntHave('otherIncome');

        if ($account) {
            $query->where('stripe_account_id', $account->id);
        }

        $transactions = $query->orderBy('transaction_date')->orderBy('id')->get();

        $generated = 0;
        $failed = 0;
        $errors = [];

        foreach ($transactions as $transaction) {
            try {
                $this->generateInvoiceForTransaction($transaction);
                $generated++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['transaction_id' => $transaction->id, 'error' => $e->getMessage()];
                Log::warning('Failed to auto-generate invoice for Stripe transaction', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['generated' => $generated, 'failed' => $failed, 'errors' => $errors];
    }

    public function finalizeImportedInvoice(Invoice $invoice): Invoice
    {
        if (! $invoice->canBeFinalized()) {
            throw new \Exception('Invoice cannot be finalized in its current state.');
        }

        if (! $invoice->person_id) {
            throw new \Exception('Invoice must be assigned to a person before finalizing.');
        }

        return DB::transaction(function () use ($invoice) {
            $person = $invoice->person;

            if (! $invoice->invoice_number) {
                $invoice->update([
                    'invoice_number' => $person->allocateNextInvoiceNumber(),
                ]);
            }

            $this->generateAndStorePdf($invoice);

            $invoice->markAsFinalized();

            return $invoice->fresh();
        });
    }

    protected function generateAndStorePdf(Invoice $invoice): void
    {
        $invoice->load(['person', 'items.stripeTransaction', 'bankAccount']);

        $directory = "invoices/{$invoice->person->invoice_prefix}/{$invoice->period_year}";
        Storage::makeDirectory($directory);

        $pdfEs = Pdf::loadView('invoices.pdf-es', ['invoice' => $invoice]);
        $filenameEs = "{$invoice->invoice_number}.pdf";
        $pathEs = "{$directory}/{$filenameEs}";
        Storage::put($pathEs, $pdfEs->output());

        $pdfEn = Pdf::loadView('invoices.pdf-en', ['invoice' => $invoice]);
        $filenameEn = "{$invoice->invoice_number}-en.pdf";
        $pathEn = "{$directory}/{$filenameEn}";
        Storage::put($pathEn, $pdfEn->output());

        $invoice->update([
            'pdf_path' => $pathEs,
            'pdf_path_en' => $pathEn,
            'generated_at' => now(),
            'generated_state_hash' => $invoice->current_state_hash,
        ]);
    }

    protected function getTransactionsForPeriod(Person $person, int $year, int $month)
    {
        return StripeTransaction::query()
            ->whereHas('stripeAccount', function ($query) use ($person) {
                $query->where('person_id', $person->id);
            })
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->whereDoesntHave('invoiceItem')
            ->get();
    }
}
