<?php

namespace App\Filament\Resources\OtherIncomes;

use App\Filament\Resources\OtherIncomes\Pages\CreateOtherIncome;
use App\Filament\Resources\OtherIncomes\Pages\EditOtherIncome;
use App\Filament\Resources\OtherIncomes\Pages\ListOtherIncomes;
use App\Filament\Resources\OtherIncomes\Schemas\OtherIncomeForm;
use App\Filament\Resources\OtherIncomes\Tables\OtherIncomesTable;
use App\Models\OtherIncome;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OtherIncomeResource extends Resource
{
    protected static ?string $model = OtherIncome::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Other Income';

    protected static ?string $modelLabel = 'Other Income';

    protected static ?string $pluralModelLabel = 'Other Income';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Income';
    }

    public static function form(Schema $schema): Schema
    {
        return OtherIncomeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OtherIncomesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOtherIncomes::route('/'),
            'create' => CreateOtherIncome::route('/create'),
            'edit' => EditOtherIncome::route('/{record}/edit'),
        ];
    }
}
