<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class BillsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Bills by Month';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        $bills = Bill::query()
            ->select(
                'currency',
                DB::raw('CAST(strftime("%m", bill_date) AS INTEGER) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('bill_date', $year)
            ->groupBy('currency', DB::raw('strftime("%m", bill_date)'))
            ->get();

        $currencies = $bills->pluck('currency')->unique()->sort()->values();

        $datasets = $currencies->map(function (string $currency) use ($bills) {
            $currencyData = $bills->where('currency', $currency)->pluck('total', 'month');

            $data = [];
            for ($i = 1; $i <= 12; $i++) {
                $data[] = isset($currencyData[$i]) ? $currencyData[$i] / 100 : 0;
            }

            $colors = $this->getCurrencyColors($currency);

            return [
                'label' => $currency,
                'data' => $data,
                'backgroundColor' => $colors['background'],
                'borderColor' => $colors['border'],
            ];
        })->toArray();

        return [
            'datasets' => $datasets,
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getCurrencyColors(string $currency): array
    {
        return match ($currency) {
            'EUR' => ['background' => 'rgba(59, 130, 246, 0.5)', 'border' => 'rgb(59, 130, 246)'],
            'USD' => ['background' => 'rgba(34, 197, 94, 0.5)', 'border' => 'rgb(34, 197, 94)'],
            'GBP' => ['background' => 'rgba(168, 85, 247, 0.5)', 'border' => 'rgb(168, 85, 247)'],
            default => ['background' => 'rgba(107, 114, 128, 0.5)', 'border' => 'rgb(107, 114, 128)'],
        };
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
