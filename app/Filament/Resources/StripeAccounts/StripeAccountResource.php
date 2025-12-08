<?php

namespace App\Filament\Resources\StripeAccounts;

use App\Filament\Resources\StripeAccounts\Pages\CreateStripeAccount;
use App\Filament\Resources\StripeAccounts\Pages\EditStripeAccount;
use App\Filament\Resources\StripeAccounts\Pages\ListStripeAccounts;
use App\Filament\Resources\StripeAccounts\Schemas\StripeAccountForm;
use App\Filament\Resources\StripeAccounts\Tables\StripeAccountsTable;
use App\Models\StripeAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StripeAccountResource extends Resource
{
    protected static ?string $model = StripeAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    public static function form(Schema $schema): Schema
    {
        return StripeAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StripeAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStripeAccounts::route('/'),
            'create' => CreateStripeAccount::route('/create'),
            'edit' => EditStripeAccount::route('/{record}/edit'),
        ];
    }
}
