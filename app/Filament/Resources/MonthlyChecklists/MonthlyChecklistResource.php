<?php

namespace App\Filament\Resources\MonthlyChecklists;

use App\Filament\Resources\MonthlyChecklists\Pages\ListMonthlyChecklists;
use App\Filament\Resources\MonthlyChecklists\Pages\ViewMonthlyChecklist;
use App\Filament\Resources\MonthlyChecklists\Tables\MonthlyChecklistsTable;
use App\Models\MonthlyChecklist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MonthlyChecklistResource extends Resource
{
    protected static ?string $model = MonthlyChecklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Monthly Checklists';

    protected static ?string $modelLabel = 'Monthly Checklist';

    protected static ?string $pluralModelLabel = 'Monthly Checklists';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        if (! MonthlyChecklist::currentMonth()->exists()) {
            return 'New';
        }

        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        if (! MonthlyChecklist::currentMonth()->exists()) {
            return 'info';
        }

        return null;
    }

    public static function table(Table $table): Table
    {
        return MonthlyChecklistsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMonthlyChecklists::route('/'),
            'view' => ViewMonthlyChecklist::route('/{record}'),
        ];
    }
}
