<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\Actions\SendTestEmailAction;
use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendTestEmailAction::make(),
            DeleteAction::make(),
        ];
    }
}
