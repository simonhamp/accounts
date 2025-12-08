<?php

namespace App\Filament\Resources\StripeTransactions\Pages;

use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStripeTransactions extends ListRecords
{
    protected static string $resource = StripeTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
