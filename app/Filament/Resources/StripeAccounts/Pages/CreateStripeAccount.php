<?php

namespace App\Filament\Resources\StripeAccounts\Pages;

use App\Filament\Resources\StripeAccounts\StripeAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStripeAccount extends CreateRecord
{
    protected static string $resource = StripeAccountResource::class;
}
