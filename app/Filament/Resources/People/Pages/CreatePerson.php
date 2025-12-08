<?php

namespace App\Filament\Resources\People\Pages;

use App\Filament\Resources\People\PersonResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    protected static string $resource = PersonResource::class;
}
