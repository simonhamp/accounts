<?php

namespace App\Filament\Resources\StripeTransactions\Pages;

use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use Filament\Resources\Pages\ListRecords;

class ListStripeTransactions extends ListRecords
{
    protected static string $resource = StripeTransactionResource::class;
}
