<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class PersonEarningsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Earnings by Person';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        // Get invoice earnings
        $invoiceEarnings = Invoice::query()
            ->select(
                'person_id',
                'currency',
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('invoice_date', $year)
            ->whereNotNull('person_id')
            ->groupBy('person_id', 'currency')
            ->get();

        // Get other income earnings
        $otherIncomeEarnings = OtherIncome::query()
            ->select(
                'person_id',
                'currency',
                DB::raw('SUM(amount) as total')
            )
            ->whereYear('income_date', $year)
            ->whereNotNull('person_id')
            ->groupBy('person_id', 'currency')
            ->get();

        // Combine both earnings collections
        $earnings = $invoiceEarnings->concat($otherIncomeEarnings);

        // Get all unique person IDs from both sources
        $personIds = $earnings->pluck('person_id')->unique()->filter()->values();

        // Load persons
        $personsMap = Person::whereIn('id', $personIds)->pluck('name', 'id')->toArray();

        // Get unique currencies
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

        // Aggregate totals by person and currency (sum invoices + other income)
        $aggregated = [];
        foreach ($earnings as $earning) {
            $key = $earning->person_id.'-'.$earning->currency;
            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'person_id' => $earning->person_id,
                    'currency' => $earning->currency,
                    'total' => 0,
                ];
            }
            $aggregated[$key]['total'] += $earning->total;
        }

        // Build datasets - one per currency
        $datasets = [];
        $colorIndex = 0;

        foreach ($currencies as $currency) {
            $data = [];

            foreach (array_keys($personsMap) as $personId) {
                $key = $personId.'-'.$currency;
                $data[] = isset($aggregated[$key]) ? $aggregated[$key]['total'] / 100 : 0;
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
