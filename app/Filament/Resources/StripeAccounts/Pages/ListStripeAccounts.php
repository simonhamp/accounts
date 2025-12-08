<?php

namespace App\Filament\Resources\StripeAccounts\Pages;

use App\Filament\Resources\StripeAccounts\StripeAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStripeAccounts extends ListRecords
{
    protected static string $resource = StripeAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
