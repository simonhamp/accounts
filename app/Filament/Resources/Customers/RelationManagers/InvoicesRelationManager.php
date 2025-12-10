<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\InvoiceStatus;
use App\Models\Person;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

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
                    ->helperText('Required - determines the invoice number'),

                Placeholder::make('invoice_number_preview')
                    ->label('Invoice Number')
                    ->content(fn ($record, $get) => $record?->invoice_number
                        ?? ($get('person_id')
                            ? Person::find($get('person_id'))?->getNextInvoiceNumber()
                            : 'Select a person first'))
                    ->helperText('Auto-generated on save'),

                DatePicker::make('invoice_date')
                    ->label('Invoice Date')
                    ->required()
                    ->default(now()),

                DatePicker::make('due_date')
                    ->label('Due Date')
                    ->helperText('Leave empty for "Due on Receipt"'),

                TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->default(fn () => $this->ownerRecord->name)
                    ->required()
                    ->maxLength(255)
                    ->helperText('Pre-filled from customer, edit to override on invoice'),

                Textarea::make('customer_address')
                    ->label('Customer Address')
                    ->default(fn () => $this->ownerRecord->address)
                    ->rows(3)
                    ->helperText('Pre-filled from customer, edit to override on invoice'),

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
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->placeholder('Pending')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->date()
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
                    ->options(collect(InvoiceStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = InvoiceStatus::Reviewed->value;

                        if (! empty($data['person_id'])) {
                            $person = Person::find($data['person_id']);
                            if ($person) {
                                $data['invoice_number'] = $person->getNextInvoiceNumber();
                            }
                        }

                        return $data;
                    })
                    ->after(function ($record) {
                        if ($record->person_id) {
                            $record->person->incrementInvoiceNumber();
                        }
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('invoice_date', 'desc');
    }
}
