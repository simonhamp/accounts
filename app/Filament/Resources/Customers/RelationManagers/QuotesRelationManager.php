<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'quotes';

    protected static ?string $recordTitleAttribute = 'quote_number';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->label('Person')
                    ->relationship('person', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->default(fn () => Person::default()?->id)
                    ->helperText('Required - determines the quote number'),

                Placeholder::make('quote_number_preview')
                    ->label('Quote Number')
                    ->content(fn ($record, $get) => $record?->quote_number
                        ?? ($get('person_id')
                            ? Person::find($get('person_id'))?->getNextQuoteNumber()
                            : 'Select a person first'))
                    ->helperText('Auto-generated on save'),

                DatePicker::make('quote_date')
                    ->label('Quote Date')
                    ->required()
                    ->default(now()),

                DatePicker::make('valid_until')
                    ->label('Valid Until')
                    ->helperText('Leave empty if the quote has no expiry date'),

                TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->default(fn () => $this->ownerRecord->name)
                    ->required()
                    ->maxLength(255)
                    ->helperText('Pre-filled from customer, edit to override on quote'),

                Textarea::make('customer_address')
                    ->label('Customer Address')
                    ->default(fn () => $this->ownerRecord->address)
                    ->rows(3)
                    ->helperText('Pre-filled from customer, edit to override on quote'),

                Select::make('currency')
                    ->options([
                        'EUR' => 'EUR - Euro',
                        'USD' => 'USD - US Dollar',
                        'GBP' => 'GBP - British Pound',
                    ])
                    ->default('EUR')
                    ->required(),
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
                TextColumn::make('quote_number')
                    ->label('Quote #')
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
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(QuoteStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = QuoteStatus::Draft->value;

                        if (! empty($data['person_id'])) {
                            $person = Person::find($data['person_id']);
                            if ($person) {
                                $data['quote_number'] = $person->getNextQuoteNumber();
                            }
                        }

                        return $data;
                    })
                    ->after(function ($record) {
                        if ($record->person_id) {
                            $record->person->increment('next_quote_number');
                        }
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->url(fn ($record) => QuoteResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('quote_date', 'desc');
    }
}
