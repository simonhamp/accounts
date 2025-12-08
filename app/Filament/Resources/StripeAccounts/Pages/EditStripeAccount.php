<?php

namespace App\Filament\Resources\StripeAccounts\Pages;

use App\Filament\Resources\StripeAccounts\StripeAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeAccount extends EditRecord
{
    protected static string $resource = StripeAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
