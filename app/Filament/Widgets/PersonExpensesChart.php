<?php

namespace App\Filament\Widgets;

use App\Models\Bill;
use App\Models\Person;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PersonExpensesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Expenses by Person';

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        // Get bill expenses (all bills regardless of status)
        $expenses = Bill::query()
            ->select(
                'person_id',
                'currency',
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('bill_date', $year)
            ->whereNotNull('person_id')
            ->groupBy('person_id', 'currency')
            ->get();

        // Get all unique person IDs
        $personIds = $expenses->pluck('person_id')->unique()->filter()->values();

        if ($personIds->isEmpty()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Load persons
        $personsMap = Person::whereIn('id', $personIds)->pluck('name', 'id')->toArray();

        // Get unique currencies
        $currencies = $expenses->pluck('currency')->unique()->sort()->values()->toArray();

        // Color palette for currencies
        $colorPalette = [
            'EUR' => ['bg' => 'rgba(239, 68, 68, 0.5)', 'border' => 'rgb(239, 68, 68)'],
            'USD' => ['bg' => 'rgba(249, 115, 22, 0.5)', 'border' => 'rgb(249, 115, 22)'],
            'GBP' => ['bg' => 'rgba(168, 85, 247, 0.5)', 'border' => 'rgb(168, 85, 247)'],
        ];
        $defaultColors = [
            ['bg' => 'rgba(236, 72, 153, 0.5)', 'border' => 'rgb(236, 72, 153)'],
            ['bg' => 'rgba(20, 184, 166, 0.5)', 'border' => 'rgb(20, 184, 166)'],
            ['bg' => 'rgba(234, 179, 8, 0.5)', 'border' => 'rgb(234, 179, 8)'],
        ];

        // Build datasets - one per currency
        $datasets = [];
        $colorIndex = 0;

        foreach ($currencies as $currency) {
            $data = [];

            foreach (array_keys($personsMap) as $personId) {
                $expense = $expenses->first(fn ($e) => $e->person_id === $personId && $e->currency === $currency);
                $data[] = $expense ? $expense->total / 100 : 0;
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
            'labels' => array_values($personsMap),
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
