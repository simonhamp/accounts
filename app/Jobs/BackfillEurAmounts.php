<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Services\ExchangeRateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BackfillEurAmounts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600; // 1 hour max

    public int $tries = 1;

    public function __construct(
        public bool $force = false
    ) {}

    public function handle(ExchangeRateService $exchangeRateService): void
    {
        Log::info('Starting EUR amounts backfill job', ['force' => $this->force]);

        $stats = [
            'invoices' => ['updated' => 0, 'skipped' => 0],
            'bills' => ['updated' => 0, 'skipped' => 0],
            'other_income' => ['updated' => 0, 'skipped' => 0],
        ];

        // Update Invoices
        $this->updateInvoices($exchangeRateService, $stats);

        // Update Bills
        $this->updateBills($exchangeRateService, $stats);

        // Update Other Income
        $this->updateOtherIncome($exchangeRateService, $stats);

        Log::info('EUR amounts backfill job completed', $stats);
    }

    private function updateInvoices(ExchangeRateService $exchangeRateService, array &$stats): void
    {
        $query = Invoice::query()
            ->whereNotNull('invoice_date')
            ->whereNotNull('currency')
            ->whereNotNull('total_amount');

        if (! $this->force) {
            $query->whereNull('amount_eur');
        }

        $query->chunk(100, function ($invoices) use ($exchangeRateService, &$stats) {
            foreach ($invoices as $invoice) {
                $amountEur = $exchangeRateService->convertToEur(
                    $invoice->total_amount,
                    $invoice->currency,
                    $invoice->invoice_date
                );

                if ($amountEur !== null) {
                    $invoice->updateQuietly(['amount_eur' => $amountEur]);
                    $stats['invoices']['updated']++;
                } else {
                    $stats['invoices']['skipped']++;
                    Log::warning('Could not convert invoice to EUR', [
                        'invoice_id' => $invoice->id,
                        'currency' => $invoice->currency,
                        'date' => $invoice->invoice_date->toDateString(),
                    ]);
                }
            }
        });
    }

    private function updateBills(ExchangeRateService $exchangeRateService, array &$stats): void
    {
        $query = Bill::query()
            ->whereNotNull('bill_date')
            ->whereNotNull('currency')
            ->whereNotNull('total_amount');

        if (! $this->force) {
            $query->whereNull('amount_eur');
        }

        $query->chunk(100, function ($bills) use ($exchangeRateService, &$stats) {
            foreach ($bills as $bill) {
                $amountEur = $exchangeRateService->convertToEur(
                    $bill->total_amount,
                    $bill->currency,
                    $bill->bill_date
                );

                if ($amountEur !== null) {
                    $bill->updateQuietly(['amount_eur' => $amountEur]);
                    $stats['bills']['updated']++;
                } else {
                    $stats['bills']['skipped']++;
                    Log::warning('Could not convert bill to EUR', [
                        'bill_id' => $bill->id,
                        'currency' => $bill->currency,
                        'date' => $bill->bill_date->toDateString(),
                    ]);
                }
            }
        });
    }

    private function updateOtherIncome(ExchangeRateService $exchangeRateService, array &$stats): void
    {
        $query = OtherIncome::query()
            ->whereNotNull('income_date')
            ->whereNotNull('currency')
            ->whereNotNull('amount');

        if (! $this->force) {
            $query->whereNull('amount_eur');
        }

        $query->chunk(100, function ($incomes) use ($exchangeRateService, &$stats) {
            foreach ($incomes as $income) {
                $amountEur = $exchangeRateService->convertToEur(
                    $income->amount,
                    $income->currency,
                    $income->income_date
                );

                if ($amountEur !== null) {
                    $income->updateQuietly(['amount_eur' => $amountEur]);
                    $stats['other_income']['updated']++;
                } else {
                    $stats['other_income']['skipped']++;
                    Log::warning('Could not convert other income to EUR', [
                        'other_income_id' => $income->id,
                        'currency' => $income->currency,
                        'date' => $income->income_date->toDateString(),
                    ]);
                }
            }
        });
    }
}
