<?php

namespace App\Filament\Resources\People\RelationManagers;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $recordTitleAttribute = 'quote_number';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'Unknown')
                    ->sortable(),
                TextColumn::make('quote_number')
                    ->label('Quote #')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('quote_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->placeholder('No expiry')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(QuoteStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
            ])
            ->recordActions([
                Action::make('edit')
                    ->url(fn ($record) => QuoteResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('quote_date', 'desc');
    }
}
