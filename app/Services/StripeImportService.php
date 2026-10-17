<?php

namespace App\Services;

use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Stripe\StripeClient;
use Stripe\StripeObject;

class StripeImportService
{
    /**
     * Fields a re-sync may fill in on an existing transaction when they are
     * still empty. Anything already set is left as it is.
     *
     * @var array<int, string>
     */
    protected const FILL_WHEN_BLANK = [
        'customer_name',
        'customer_email',
        'customer_address',
    ];

    public function __construct(
        protected int $rateLimitPerMinute = 90
    ) {}

    public function syncAccount(StripeAccount $account, ?int $year = null, ?int $month = null): array
    {
        $stripe = app(StripeClient::class, ['config' => $account->api_key]);
        $imported = [
            'payments' => 0,
            'refunds' => 0,
            'disputes' => 0,
        ];

        $dateFilter = $this->buildDateFilter($year, $month);

        $this->importCharges($stripe, $account, $imported, $dateFilter);
        $this->importRefunds($stripe, $account, $imported, $dateFilter);
        $this->importDisputes($stripe, $account, $imported, $dateFilter);

        $account->update(['last_synced_at' => now()]);

        return $imported;
    }

    protected function buildDateFilter(?int $year, ?int $month): array
    {
        if ($year === null) {
            // Default to last 7 days for scheduled syncs
            $startDate = now()->subDays(7)->startOfDay();
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

    protected function importCharges(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter = []): void
    {
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $this->waitForRateLimit();

            $params = array_merge(['limit' => 100], $dateFilter);

            if ($startingAfter !== null) {
                $params['starting_after'] = $startingAfter;
            }

            $charges = $stripe->charges->all($params);

            foreach ($charges->data as $charge) {
                if ($charge->status !== 'succeeded') {
                    continue;
                }

                $this->importCharge($account, $charge);
                $imported['payments']++;
            }

            $hasMore = $charges->has_more;
            if ($hasMore) {
                if (count($charges->data) > 0) {
                    $lastCharge = end($charges->data);
                    $startingAfter = $lastCharge->id;
                } else {
                    $hasMore = false;
                }
            }
        }
    }

    protected function importCharge(StripeAccount $account, $charge): void
    {
        $this->importTransaction($charge->id, [
            'stripe_account_id' => $account->id,
            'type' => 'payment',
            'amount' => $charge->amount,
            'currency' => strtoupper($charge->currency),
            'customer_name' => $charge->billing_details?->name,
            'customer_email' => $charge->billing_details?->email ?? $charge->receipt_email,
            'customer_address' => $this->formatAddress($charge->billing_details?->address),
            'description' => $charge->description ?? 'Payment',
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
     * Stripe's metadata is refreshed on every row. Customer details only fill
     * gaps, and only while the transaction hasn't been invoiced or converted,
     * so nothing Simon typed in and nothing already on an invoice changes.
     * Amount, currency, type, date, description and status are left alone.
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

    protected function importRefunds(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter = []): void
    {
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $this->waitForRateLimit();

            $params = array_merge(['limit' => 100], $dateFilter);

            if ($startingAfter !== null) {
                $params['starting_after'] = $startingAfter;
            }

            $refunds = $stripe->refunds->all($params);

            foreach ($refunds->data as $refund) {
                if ($refund->status !== 'succeeded') {
                    continue;
                }

                $this->importRefund($account, $refund);
                $imported['refunds']++;
            }

            $hasMore = $refunds->has_more;
            if ($hasMore) {
                if (count($refunds->data) > 0) {
                    $lastRefund = end($refunds->data);
                    $startingAfter = $lastRefund->id;
                } else {
                    $hasMore = false;
                }
            }
        }
    }

    protected function importRefund(StripeAccount $account, $refund): void
    {
        $this->importTransaction($refund->id, [
            'stripe_account_id' => $account->id,
            'type' => 'refund',
            'amount' => -$refund->amount,
            'currency' => strtoupper($refund->currency),
            'description' => $refund->reason ? "Refund: {$refund->reason}" : 'Refund',
            'metadata' => $this->metadata($refund->metadata),
            'transaction_date' => Carbon::createFromTimestamp($refund->created),
        ]);
    }

    protected function importDisputes(StripeClient $stripe, StripeAccount $account, array &$imported, array $dateFilter = []): void
    {
        $hasMore = true;
        $startingAfter = null;

        while ($hasMore) {
            $this->waitForRateLimit();

            $params = array_merge(['limit' => 100], $dateFilter);

            if ($startingAfter !== null) {
                $params['starting_after'] = $startingAfter;
            }

            $disputes = $stripe->disputes->all($params);

            foreach ($disputes->data as $dispute) {
                $this->importDispute($account, $dispute);
                $imported['disputes']++;
            }

            $hasMore = $disputes->has_more;
            if ($hasMore) {
                if (count($disputes->data) > 0) {
                    $lastDispute = end($disputes->data);
                    $startingAfter = $lastDispute->id;
                } else {
                    $hasMore = false;
                }
            }
        }
    }

    protected function importDispute(StripeAccount $account, $dispute): void
    {
        $this->importTransaction($dispute->id, [
            'stripe_account_id' => $account->id,
            'type' => 'chargeback',
            'amount' => -$dispute->amount,
            'currency' => strtoupper($dispute->currency),
            'description' => "Dispute/Chargeback: {$dispute->reason}",
            'metadata' => [
                'status' => $dispute->status,
                'reason' => $dispute->reason,
            ],
            'transaction_date' => Carbon::createFromTimestamp($dispute->created),
        ]);
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
