<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PersonEarningsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Earnings by Person';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        $earnings = Invoice::query()
            ->select(
                'person_id',
                'currency',
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('invoice_date', $year)
            ->whereNotNull('person_id')
            ->groupBy('person_id', 'currency')
            ->with('person:id,name')
            ->get();

        // Get unique persons and currencies
        $persons = $earnings->pluck('person.name', 'person_id')->unique()->filter()->toArray();
        $currencies = $earnings->pluck('currency')->unique()->sort()->values()->toArray();

        // Color palette for currencies
        $colorPalette = [
            'EUR' => ['bg' => 'rgba(59, 130, 246, 0.5)', 'border' => 'rgb(59, 130, 246)'],
            'USD' => ['bg' => 'rgba(34, 197, 94, 0.5)', 'border' => 'rgb(34, 197, 94)'],
            'GBP' => ['bg' => 'rgba(249, 115, 22, 0.5)', 'border' => 'rgb(249, 115, 22)'],
        ];
        $defaultColors = [
            ['bg' => 'rgba(168, 85, 247, 0.5)', 'border' => 'rgb(168, 85, 247)'],
            ['bg' => 'rgba(236, 72, 153, 0.5)', 'border' => 'rgb(236, 72, 153)'],
            ['bg' => 'rgba(20, 184, 166, 0.5)', 'border' => 'rgb(20, 184, 166)'],
        ];

        // Build datasets - one per currency
        $datasets = [];
        $colorIndex = 0;

        foreach ($currencies as $currency) {
            $data = [];

            foreach (array_keys($persons) as $personId) {
                $earning = $earnings->first(fn ($e) => $e->person_id === $personId && $e->currency === $currency);
                $data[] = $earning ? $earning->total / 100 : 0;
            }

            $colors = $colorPalette[$currency] ?? ($defaultColors[$colorIndex++ % count($defaultColors)] ?? $defaultColors[0]);

            $datasets[] = [
                'label' => $currency,
                'data' => $data,
                'backgroundColor' => $colors['bg'],
                'borderColor' => $colors['border'],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_values($persons),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'stacked' => false,
                ],
                'y' => [
                    'stacked' => false,
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
