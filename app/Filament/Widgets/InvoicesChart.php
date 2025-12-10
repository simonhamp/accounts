<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\DB;

class InvoicesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Invoices by Month';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $year = $this->pageFilters['year'] ?? date('Y');

        $invoices = Invoice::query()
            ->select(
                DB::raw('CAST(strftime("%m", invoice_date) AS INTEGER) as month'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereYear('invoice_date', $year)
            ->groupBy(DB::raw('strftime("%m", invoice_date)'))
            ->pluck('total', 'month')
            ->toArray();

        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = isset($invoices[$i]) ? $invoices[$i] / 100 : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Invoices Total',
                    'data' => $data,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
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
