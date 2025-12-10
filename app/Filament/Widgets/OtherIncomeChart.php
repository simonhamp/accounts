<?php

namespace App\Filament\Widgets;

use App\Models\OtherIncome;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class OtherIncomeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Other Income by Month';

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        $income = OtherIncome::query()
            ->select(
                DB::raw('CAST(strftime("%m", income_date) AS INTEGER) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->whereYear('income_date', $year)
            ->groupBy(DB::raw('strftime("%m", income_date)'))
            ->pluck('total', 'month')
            ->toArray();

        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = isset($income[$i]) ? $income[$i] / 100 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Other Income Total',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
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
