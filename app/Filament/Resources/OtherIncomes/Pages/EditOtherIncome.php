<?php

namespace App\Filament\Resources\OtherIncomes\Pages;

use App\Filament\Resources\OtherIncomes\OtherIncomeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOtherIncome extends EditRecord
{
    protected static string $resource = OtherIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
