<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

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
