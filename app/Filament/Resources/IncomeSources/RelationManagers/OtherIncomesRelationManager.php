<?php

namespace App\Filament\Resources\IncomeSources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OtherIncomesRelationManager extends RelationManager
{
    protected static string $relationship = 'otherIncomes';

    protected static ?string $title = 'Income Records';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('income_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('person.name')
                    ->label('Person')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('reference')
                    ->limit(20)
                    ->toggleable(),
            ])
            ->defaultSort('income_date', 'desc');
    }
}
