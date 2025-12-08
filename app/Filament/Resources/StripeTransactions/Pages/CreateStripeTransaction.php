<?php

namespace App\Filament\Resources\StripeTransactions\Pages;

use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStripeTransaction extends CreateRecord
{
    protected static string $resource = StripeTransactionResource::class;
}
