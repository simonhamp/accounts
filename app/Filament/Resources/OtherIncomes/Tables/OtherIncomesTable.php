<?php

namespace App\Filament\Resources\OtherIncomes\Tables;

use App\Enums\OtherIncomeStatus;
use App\Models\IncomeSource;
use App\Models\Person;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OtherIncomesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('income_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('person.name')
                    ->label('Person')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('incomeSource.name')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No source'),
                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (OtherIncomeStatus $state) => $state->color()),
                TextColumn::make('currency')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'EUR' => 'success',
                        'USD' => 'info',
                        'GBP' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reference')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(OtherIncomeStatus::class),
                SelectFilter::make('person_id')
                    ->label('Person')
                    ->options(Person::pluck('name', 'id')),
                SelectFilter::make('income_source_id')
                    ->label('Income Source')
                    ->options(IncomeSource::pluck('name', 'id')),
                SelectFilter::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'GBP' => 'GBP',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('income_date', 'desc');
    }
}
