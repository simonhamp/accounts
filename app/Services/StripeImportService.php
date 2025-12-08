<?php

namespace App\Services;

use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Stripe\StripeClient;

class StripeImportService
{
    public function __construct(
        protected int $rateLimitPerMinute = 90
    ) {}

    public function syncAccount(StripeAccount $account, ?int $year = null, ?int $month = null): array
    {
        $stripe = new StripeClient($account->api_key);
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
            return [];
        }

        if ($month !== null) {
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
        StripeTransaction::updateOrCreate(
            ['stripe_transaction_id' => $charge->id],
            [
                'stripe_account_id' => $account->id,
                'type' => 'payment',
                'amount' => $charge->amount,
                'currency' => strtoupper($charge->currency),
                'customer_name' => $charge->billing_details?->name,
                'customer_email' => $charge->billing_details?->email ?? $charge->receipt_email,
                'customer_address' => $this->formatAddress($charge->billing_details?->address),
                'description' => $charge->description ?? 'Payment',
                'metadata' => (array) $charge->metadata,
                'status' => 'pending_review',
                'transaction_date' => Carbon::createFromTimestamp($charge->created),
            ]
        )->updateCompleteStatus();
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
        StripeTransaction::updateOrCreate(
            ['stripe_transaction_id' => $refund->id],
            [
                'stripe_account_id' => $account->id,
                'type' => 'refund',
                'amount' => -$refund->amount,
                'currency' => strtoupper($refund->currency),
                'description' => $refund->reason ? "Refund: {$refund->reason}" : 'Refund',
                'metadata' => (array) $refund->metadata,
                'status' => 'pending_review',
                'transaction_date' => Carbon::createFromTimestamp($refund->created),
            ]
        )->updateCompleteStatus();
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
        StripeTransaction::updateOrCreate(
            ['stripe_transaction_id' => $dispute->id],
            [
                'stripe_account_id' => $account->id,
                'type' => 'chargeback',
                'amount' => -$dispute->amount,
                'currency' => strtoupper($dispute->currency),
                'description' => "Dispute/Chargeback: {$dispute->reason}",
                'metadata' => [
                    'status' => $dispute->status,
                    'reason' => $dispute->reason,
                ],
                'status' => 'pending_review',
                'transaction_date' => Carbon::createFromTimestamp($dispute->created),
            ]
        )->updateCompleteStatus();
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
