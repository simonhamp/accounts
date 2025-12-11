<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Enums\BillStatus;
use App\Filament\Resources\Bills\BillResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = BillStatus::Reviewed;

        return $data;
    }
}
