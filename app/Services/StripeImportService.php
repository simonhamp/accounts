<?php

namespace App\Services;

use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Stripe\Charge;
use Stripe\Collection;
use Stripe\Exception\PermissionException;
use Stripe\Invoice;
use Stripe\StripeClient;
use Stripe\StripeObject;

class StripeImportService
{
    /**
     * How many days back a scheduled sync (one without a year or month) reads.
     */
    public const SCHEDULED_SYNC_DAYS = 7;

    /**
     * How far before the sync window to look for invoice payments. Stripe
     * creates an invoice payment when the invoice is finalized, which can be
     * a while before the charge that pays it, e.g. after failed retries.
     */
    protected const INVOICE_LOOKBACK_DAYS = 31;

    /**
     * Fields a re-sync may fill in on an existing transaction when they are
     * still empty. Anything already set is left as it is.
     *
     * @var array<int, string>
     */
    protected const FILL_WHEN_BLANK = [
        'stripe_invoice_id',
        'stripe_charge_id',
        'customer_name',
        'customer_email',
        'customer_address',
        'customer_tax_ids',
    ];

    public function __construct(
        protected int $rateLimitPerMinute = 90
    ) {}

    /**
     * @return array{payments: int, refunds: int, disputes: int, warnings: array<int, string>}
     */
    public function syncAccount(StripeAccount $account, ?int $year = null, ?int $month = null): array
    {
        $stripe = app(StripeClient::class, ['config' => $account->api_key]);
        $imported = [
            'payments' => 0,
            'refunds' => 0,
            'disputes' => 0,
            'warnings' => [],
        ];

        $dateFilter = $this->buildDateFilter($year, $month);

        $invoices = $this->fetchInvoicesByPayment($stripe, $account, $imported, $dateFilter);

        $this->importCharges($stripe, $account, $imported, $dateFilter, $invoices);
        $this->importRefunds($stripe, $account, $imported, $dateFilter, $invoices);
        $this->importDisputes($stripe, $account, $imported, $dateFilter, $invoices);

        $account->update(['last_synced_at' => now()]);

        return $imported;
    }

    protected function buildDateFilter(?int $year, ?int $month): array
    {
        if ($year === null) {
            $startDate = now()->subDays(self::SCHEDULED_SYNC_DAYS)->startOfDay();
            $endDate = now()->endOfDay();
        } elseif ($month !== null) {
            // Specific month and year
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        } else {
            // Entire year
            $startDate = Carbon::create($year, 1, 1)->startOfYear();
            $endDate = $startDate->copy()->endOfYear();
        }

        return [
            'created' => [
                'gte' => $startDate->timestamp,
                'lte' => $endDate->timestamp,
            ],
        ];
    }

    /**
     * Map every payment that paid a Stripe invoice to that invoice, keyed by
     * payment intent ID (or charge ID for older invoice payments), so a charge
     * can take its customer's details from the invoice. Charges and payment
     * intents no longer link to their invoice, so this lists the paid invoice
     * payments for the window once, with the invoice expanded, rather than
     * asking about each charge.
     *
     * @param  array{payments: int, refunds: int, disputes: int, warnings: array<int, string>}  $imported
     * @return array<string, Invoice>
     */
    protected function fetchInvoicesByPayment(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter): array
    {
        $params = [
            'status' => 'paid',
            'expand' => ['data.invoice'],
            'created' => [
                'gte' => Carbon::createFromTimestamp($dateFilter['created']['gte'])->subDays(self::INVOICE_LOOKBACK_DAYS)->timestamp,
                'lte' => Carbon::createFromTimestamp($dateFilter['created']['lte'])->addDay()->timestamp,
            ],
        ];

        $invoices = [];

        try {
            foreach ($this->listAll(fn (array $page) => $stripe->invoicePayments->all($page), $params) as $invoicePayment) {
                $invoice = $invoicePayment->invoice;
                $paymentId = $this->stripeId($invoicePayment->payment->payment_intent ?? null)
                    ?? $this->stripeId($invoicePayment->payment->charge ?? null);

                if ($invoice instanceof Invoice && $paymentId !== null) {
                    $invoices[$paymentId] ??= $invoice;
                }
            }
        } catch (PermissionException $e) {
            Log::warning('Stripe key cannot read invoices; customer details fall back to billing details', [
                'stripe_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            $imported['warnings'][] = 'This API key cannot read invoices, so customer details came from the card\'s billing details. Give the key read access to Invoices.';
        }

        return $invoices;
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importCharges(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter, array $invoices): void
    {
        foreach ($this->listAll(fn (array $page) => $stripe->charges->all($page), $dateFilter) as $charge) {
            if ($charge->status !== 'succeeded') {
                continue;
            }

            $this->importCharge($account, $charge, $invoices);
            $imported['payments']++;
        }
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importCharge(StripeAccount $account, Charge $charge, array $invoices): void
    {
        $invoice = $this->invoiceForCharge($charge, $invoices);

        $this->importTransaction($charge->id, [
            'stripe_account_id' => $account->id,
            'type' => 'payment',
            'amount' => $charge->amount,
            'currency' => strtoupper($charge->currency),
            ...$this->customerDetails($charge, $invoice),
            'description' => ($invoice->description ?? null) ?: ($charge->description ?? 'Payment'),
            'metadata' => $this->metadata($charge->metadata),
            'transaction_date' => Carbon::createFromTimestamp($charge->created),
        ]);
    }

    /**
     * Create a transaction the first time Stripe reports it. A transaction that
     * already exists keeps its status, so a re-sync never un-ignores it or puts
     * it back in line for invoicing, and keeps anything edited by hand.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function importTransaction(string $stripeTransactionId, array $attributes): void
    {
        $transaction = StripeTransaction::query()
            ->where('stripe_transaction_id', $stripeTransactionId)
            ->first();

        if ($transaction === null) {
            StripeTransaction::create([
                ...$attributes,
                'stripe_transaction_id' => $stripeTransactionId,
                'status' => 'pending_review',
            ])->updateCompleteStatus();

            return;
        }

        $this->refreshExistingTransaction($transaction, $attributes);
    }

    /**
     * Stripe's metadata is refreshed on every row. Customer details and Stripe
     * links only fill gaps, and only while the transaction hasn't been invoiced
     * or converted, so nothing Simon typed in and nothing already on an invoice
     * changes. Amount, currency, type, date, description and status are left
     * alone.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function refreshExistingTransaction(StripeTransaction $transaction, array $attributes): void
    {
        $updates = ['metadata' => $attributes['metadata']];

        if (! $transaction->isProcessed()) {
            foreach (self::FILL_WHEN_BLANK as $field) {
                if (blank($transaction->{$field}) && filled($attributes[$field] ?? null)) {
                    $updates[$field] = $attributes[$field];
                }
            }
        }

        $transaction->update($updates);
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importRefunds(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter, array $invoices): void
    {
        $params = [...$dateFilter, 'expand' => ['data.charge']];

        foreach ($this->listAll(fn (array $page) => $stripe->refunds->all($page), $params) as $refund) {
            if ($refund->status !== 'succeeded') {
                continue;
            }

            $this->importRefund($account, $refund, $invoices);
            $imported['refunds']++;
        }
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importRefund(StripeAccount $account, $refund, array $invoices): void
    {
        $this->importTransaction($refund->id, [
            'stripe_account_id' => $account->id,
            'type' => 'refund',
            'amount' => -$refund->amount,
            'currency' => strtoupper($refund->currency),
            'stripe_charge_id' => $this->stripeId($refund->charge),
            ...$this->customerDetailsForOriginalCharge($refund->charge, $invoices),
            'description' => $refund->reason ? "Refund: {$refund->reason}" : 'Refund',
            'metadata' => $this->metadata($refund->metadata),
            'transaction_date' => Carbon::createFromTimestamp($refund->created),
        ]);
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importDisputes(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter, array $invoices): void
    {
        $params = [...$dateFilter, 'expand' => ['data.charge']];

        foreach ($this->listAll(fn (array $page) => $stripe->disputes->all($page), $params) as $dispute) {
            $this->importDispute($account, $dispute, $invoices);
            $imported['disputes']++;
        }
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function importDispute(StripeAccount $account, $dispute, array $invoices): void
    {
        $this->importTransaction($dispute->id, [
            'stripe_account_id' => $account->id,
            'type' => 'chargeback',
            'amount' => -$dispute->amount,
            'currency' => strtoupper($dispute->currency),
            'stripe_charge_id' => $this->stripeId($dispute->charge),
            ...$this->customerDetailsForOriginalCharge($dispute->charge, $invoices),
            'description' => "Dispute/Chargeback: {$dispute->reason}",
            'metadata' => [
                'status' => $dispute->status,
                'reason' => $dispute->reason,
            ],
            'transaction_date' => Carbon::createFromTimestamp($dispute->created),
        ]);
    }

    /**
     * @param  array<string, Invoice>  $invoices
     */
    protected function invoiceForCharge(Charge $charge, array $invoices): ?Invoice
    {
        $paymentIntentId = $this->stripeId($charge->payment_intent ?? null);

        return ($paymentIntentId !== null ? $invoices[$paymentIntentId] ?? null : null)
            ?? $invoices[$charge->id]
            ?? null;
    }

    /**
     * The customer as the Stripe invoice recorded them when it was finalized:
     * the business name, billing email, address and tax IDs. Without an
     * invoice, or where it leaves a field empty, the card's billing details.
     *
     * @return array{stripe_invoice_id: ?string, customer_name: ?string, customer_email: ?string, customer_address: ?string, customer_tax_ids: ?array<int, array{type: ?string, value: string}>}
     */
    protected function customerDetails(Charge $charge, ?Invoice $invoice): array
    {
        $billing = $charge->billing_details ?? null;

        return [
            'stripe_invoice_id' => $invoice?->id,
            'customer_name' => ($invoice->customer_name ?? null) ?: ($billing->name ?? null),
            'customer_email' => ($invoice->customer_email ?? null) ?: (($billing->email ?? null) ?: ($charge->receipt_email ?? null)),
            'customer_address' => $this->formatAddress($invoice->customer_address ?? null)
                ?? $this->formatAddress($billing->address ?? null),
            'customer_tax_ids' => $this->taxIds($invoice->customer_tax_ids ?? null),
        ];
    }

    /**
     * Refunds and disputes carry the customer of the charge they belong to, so
     * their credit note can be addressed and emailed. The imported charge comes
     * first, since it holds any hand edits. Failing that, the charge Stripe
     * expanded onto the refund or dispute, through its invoice when it has one.
     *
     * @param  array<string, Invoice>  $invoices
     * @return array<string, mixed>
     */
    protected function customerDetailsForOriginalCharge(Charge|string|null $charge, array $invoices): array
    {
        $chargeId = $this->stripeId($charge);

        $original = $chargeId === null ? null : StripeTransaction::query()
            ->where('stripe_transaction_id', $chargeId)
            ->first();

        if ($original !== null) {
            return $original->only([
                'stripe_invoice_id',
                'customer_name',
                'customer_email',
                'customer_address',
                'customer_tax_ids',
            ]);
        }

        if ($charge instanceof Charge) {
            return $this->customerDetails($charge, $this->invoiceForCharge($charge, $invoices));
        }

        return [];
    }

    /**
     * @param  array<int, StripeObject>|null  $taxIds
     * @return array<int, array{type: ?string, value: string}>|null
     */
    protected function taxIds(?array $taxIds): ?array
    {
        $formatted = collect($taxIds ?? [])
            ->map(fn ($taxId) => ['type' => $taxId->type ?? null, 'value' => $taxId->value ?? null])
            ->filter(fn (array $taxId) => filled($taxId['value']))
            ->values()
            ->all();

        return $formatted === [] ? null : $formatted;
    }

    /**
     * Stripe fields that can be expanded hold either an ID or the object.
     */
    protected function stripeId(StripeObject|string|null $value): ?string
    {
        return is_string($value) ? $value : ($value->id ?? null);
    }

    /**
     * Walk every page of a Stripe list endpoint, 100 at a time.
     *
     * @param  callable(array<string, mixed>): Collection  $fetchPage
     * @param  array<string, mixed>  $params
     */
    protected function listAll(callable $fetchPage, array $params): Generator
    {
        $startingAfter = null;

        do {
            $this->waitForRateLimit();

            $page = $fetchPage(array_merge(
                ['limit' => 100],
                $params,
                $startingAfter !== null ? ['starting_after' => $startingAfter] : [],
            ));

            $items = $page->data;

            foreach ($items as $item) {
                yield $item;
            }

            $startingAfter = $items === [] ? null : $items[array_key_last($items)]->id;
        } while ($page->has_more && $startingAfter !== null);
    }

    /**
     * Casting a StripeObject with (array) dumps its internals, request options
     * and API key included, so read the values through toArray(). Empty
     * metadata comes back from the SDK as a plain array.
     *
     * @param  StripeObject|array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    protected function metadata(StripeObject|array|null $metadata): array
    {
        return $metadata instanceof StripeObject ? $metadata->toArray() : ($metadata ?? []);
    }

    protected function formatAddress($address): ?string
    {
        if (! $address) {
            return null;
        }

        $parts = array_filter([
            $address->line1,
            $address->line2,
            $address->city,
            $address->state,
            $address->postal_code,
            $address->country,
        ]);

        return implode(', ', $parts) ?: null;
    }

    protected function waitForRateLimit(): void
    {
        $key = 'stripe-api-rate-limit';

        $executed = RateLimiter::attempt(
            $key,
            $this->rateLimitPerMinute,
            function () {},
            60
        );

        if (! $executed) {
            sleep(1);
            $this->waitForRateLimit();
        }
    }
}
