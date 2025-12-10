<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Enums\BillStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BillsRelationManager extends RelationManager
{
    protected static string $relationship = 'bills';

    protected static ?string $recordTitleAttribute = 'bill_number';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('bill_number')
                    ->label('Bill Number')
                    ->maxLength(255),
                DatePicker::make('bill_date')
                    ->label('Bill Date')
                    ->required(),
                DatePicker::make('due_date')
                    ->label('Due Date'),
                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->required()
                    ->numeric()
                    ->prefix('EUR')
                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),
                Select::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'GBP' => 'GBP',
                    ])
                    ->default('EUR')
                    ->required(),
                Select::make('status')
                    ->options(collect(BillStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    ))
                    ->default(BillStatus::Reviewed->value)
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'Unknown')
                    ->sortable(),
                TextColumn::make('bill_number')
                    ->label('Bill #')
                    ->placeholder('N/A')
                    ->searchable(),
                TextColumn::make('bill_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BillStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('bill_date', 'desc');
    }
}
