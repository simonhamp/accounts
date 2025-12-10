<?php

namespace App\Filament\Resources\IncomeSources\Pages;

use App\Filament\Resources\IncomeSources\IncomeSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIncomeSources extends ListRecords
{
    protected static string $resource = IncomeSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
