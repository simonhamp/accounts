<?php

namespace App\Filament\Resources\OtherIncomes\Pages;

use App\Filament\Resources\OtherIncomes\OtherIncomeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOtherIncomes extends ListRecords
{
    protected static string $resource = OtherIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
