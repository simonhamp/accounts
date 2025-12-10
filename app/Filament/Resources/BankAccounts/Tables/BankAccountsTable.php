<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Account Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('currency')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'EUR' => 'success',
                        'USD' => 'info',
                        'GBP' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('account_number')
                    ->label('Account #')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('iban')
                    ->label('IBAN')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('other_incomes_count')
                    ->label('Payments')
                    ->counts('otherIncomes')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All accounts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
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
            ->defaultSort('name');
    }
}
