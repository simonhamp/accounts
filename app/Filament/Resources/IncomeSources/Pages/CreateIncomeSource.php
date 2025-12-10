<?php

namespace App\Filament\Resources\IncomeSources\Pages;

use App\Filament\Resources\IncomeSources\IncomeSourceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncomeSource extends CreateRecord
{
    protected static string $resource = IncomeSourceResource::class;
}
