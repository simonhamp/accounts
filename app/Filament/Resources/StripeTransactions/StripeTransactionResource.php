<?php

namespace App\Filament\Resources\StripeTransactions;

use App\Filament\Resources\StripeTransactions\Pages\CreateStripeTransaction;
use App\Filament\Resources\StripeTransactions\Pages\EditStripeTransaction;
use App\Filament\Resources\StripeTransactions\Pages\ListStripeTransactions;
use App\Filament\Resources\StripeTransactions\Schemas\StripeTransactionForm;
use App\Filament\Resources\StripeTransactions\Tables\StripeTransactionsTable;
use App\Models\StripeTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StripeTransactionResource extends Resource
{
    protected static ?string $model = StripeTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Income';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = StripeTransaction::whereDoesntHave('invoiceItem')
            ->where('status', '!=', 'ignored')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return StripeTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StripeTransactionsTable::configure($table);
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
            'index' => ListStripeTransactions::route('/'),
            'create' => CreateStripeTransaction::route('/create'),
            'edit' => EditStripeTransaction::route('/{record}/edit'),
        ];
    }
}
