<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetIncomeStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        // Get all currencies used across all three models
        $currencies = collect()
            ->merge(Invoice::finalized()->whereYear('invoice_date', $year)->distinct()->pluck('currency'))
            ->merge(OtherIncome::whereYear('income_date', $year)->distinct()->pluck('currency'))
            ->merge(Bill::whereYear('bill_date', $year)->distinct()->pluck('currency'))
            ->unique()
            ->filter()
            ->sort()
            ->values();

        // Calculate totals per currency
        $invoiceTotals = Invoice::finalized()
            ->whereYear('invoice_date', $year)
            ->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $otherIncomeTotals = OtherIncome::query()
            ->whereYear('income_date', $year)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $billTotals = Bill::query()
            ->whereYear('bill_date', $year)
            ->selectRaw('currency, SUM(total_amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        // Build stats for each currency
        return $currencies->map(function (string $currency) use ($invoiceTotals, $otherIncomeTotals, $billTotals) {
            $invoices = $invoiceTotals->get($currency, 0);
            $otherIncome = $otherIncomeTotals->get($currency, 0);
            $bills = $billTotals->get($currency, 0);

            $net = ($invoices + $otherIncome) - $bills;

            return Stat::make(
                label: "Net Income ({$currency})",
                value: $this->formatAmount($net, $currency),
            )
                ->description($this->buildDescription($invoices, $otherIncome, $bills, $currency))
                ->color($net >= 0 ? 'success' : 'danger');
        })->toArray();
    }

    protected function formatAmount(int $cents, string $currency): string
    {
        $amount = $cents / 100;
        $symbol = match ($currency) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => $currency.' ',
        };

        $formatted = number_format(abs($amount), 2);

        return ($cents < 0 ? '-' : '').$symbol.$formatted;
    }

    protected function buildDescription(int $invoices, int $otherIncome, int $bills, string $currency): string
    {
        $symbol = match ($currency) {
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => '',
        };

        $invoiceFormatted = $symbol.number_format($invoices / 100, 0);
        $otherFormatted = $symbol.number_format($otherIncome / 100, 0);
        $billsFormatted = $symbol.number_format($bills / 100, 0);

        return "{$invoiceFormatted} invoices + {$otherFormatted} other − {$billsFormatted} bills";
    }
}
