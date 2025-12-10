<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Enums\BillingFrequency;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('billing_frequency')
                    ->label('Billing')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'None')
                    ->color(fn ($state) => match ($state) {
                        BillingFrequency::Monthly => 'success',
                        BillingFrequency::Annual => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('billing_month')
                    ->label('Month')
                    ->formatStateUsing(fn ($record) => $record->getBillingMonthName())
                    ->placeholder('-')
                    ->visible(fn ($livewire) => true),
                TextColumn::make('tax_id')
                    ->label('Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bills_count')
                    ->label('Bills')
                    ->counts('bills')
                    ->sortable(),
                TextColumn::make('bills_sum_total_amount')
                    ->label('Total Spend')
                    ->sum('bills', 'total_amount')
                    ->money('EUR', divideBy: 100)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All suppliers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                SelectFilter::make('billing_frequency')
                    ->label('Billing Frequency')
                    ->options(BillingFrequency::class),
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
