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
                DB::raw('CAST(strftime("%m", bill_date) AS INTEGER) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('bill_date', $year)
            ->groupBy(DB::raw('strftime("%m", bill_date)'))
            ->pluck('total', 'month')
            ->toArray();

        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = isset($bills[$i]) ? $bills[$i] / 100 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Bills Total',
                    'data' => $data,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)',
                    'borderColor' => 'rgb(239, 68, 68)',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
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
