<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BillsAwaitingPayment;
use App\Filament\Widgets\BillsChart;
use App\Filament\Widgets\InvoicesChart;
use App\Filament\Widgets\InvoicesPendingAction;
use App\Filament\Widgets\NetIncomeStats;
use App\Filament\Widgets\OtherIncomeChart;
use App\Filament\Widgets\PersonEarningsChart;
use App\Filament\Widgets\PersonExpensesChart;
use App\Filament\Widgets\StripeTransactionsPendingReview;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getWidgets(): array
    {
        return [
            NetIncomeStats::class,
            InvoicesChart::class,
            BillsChart::class,
            OtherIncomeChart::class,
            PersonEarningsChart::class,
            PersonExpensesChart::class,
            StripeTransactionsPendingReview::class,
            InvoicesPendingAction::class,
            BillsAwaitingPayment::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $currentYear = (int) date('Y');
        $years = collect(range($currentYear, 2023))
            ->mapWithKeys(fn ($year) => [(string) $year => (string) $year])
            ->all();

        return $schema
            ->components([
                Select::make('year')
                    ->label('Year')
                    ->options($years)
                    ->default((string) $currentYear),
            ]);
    }
}
