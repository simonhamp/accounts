<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceItemUnit;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Original PDF')
                    ->components([
                        Placeholder::make('pdf_preview')
                            ->label('')
                            ->content(function ($record) {
                                $url = route('invoices.original-pdf', $record);

                                return new HtmlString(
                                    '<iframe src="'.$url.'" class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 600px;"></iframe>'
                                );
                            }),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record?->original_file_path && Storage::disk('local')->exists($record->original_file_path)),

                Section::make('Status')
                    ->components([
                        Placeholder::make('status_display')
                            ->label('Current Status')
                            ->content(fn ($record) => $record?->status?->label() ?? 'New'),

                        Placeholder::make('error_display')
                            ->label('Error')
                            ->content(fn ($record) => $record?->error_message)
                            ->visible(fn ($record) => $record?->status === InvoiceStatus::Failed),
                    ])
                    ->visible(fn ($record) => $record !== null),

                Section::make('Invoice Details')
                    ->components([
                        Select::make('person_id')
                            ->relationship('person', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(fn ($record) => $record?->isPending()
                                ? 'Required before finalizing'
                                : null),

                        TextInput::make('invoice_number')
                            ->helperText(fn ($record) => $record?->isPending()
                                ? 'Will be auto-generated on finalization if left empty'
                                : null),

                        DatePicker::make('invoice_date')
                            ->native(false),

                        TextInput::make('period_month')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12),

                        TextInput::make('period_year')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100),
                    ])
                    ->columns(2),

                Section::make('Customer Details')
                    ->components([
                        TextInput::make('customer_name'),

                        Select::make('selected_address')
                            ->label('Select Address')
                            ->options(function ($record) {
                                $addresses = $record?->extracted_data['all_addresses'] ?? [];

                                return array_combine($addresses, $addresses);
                            })
                            ->visible(fn ($record) => ! empty($record?->extracted_data['all_addresses']))
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                $set('customer_address', $state);
                            })
                            ->helperText('Select the customer address from extracted addresses'),

                        Textarea::make('customer_address')
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('customer_tax_id'),
                    ])
                    ->columns(2),

                Section::make('Amounts')
                    ->components([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->suffix('cents')
                            ->helperText('Amount in cents (e.g., 10000 = 100.00). Negative amounts create a Credit Note.')
                            ->live(onBlur: true),

                        TextInput::make('currency')
                            ->default('EUR')
                            ->maxLength(3),

                        Select::make('parent_invoice_id')
                            ->label('Original Invoice (for Credit Note)')
                            ->relationship('parentInvoice', 'invoice_number')
                            ->getOptionLabelFromRecordUsing(fn (Invoice $record) => "{$record->invoice_number} - {$record->customer_name}")
                            ->searchable(['invoice_number', 'customer_name'])
                            ->preload()
                            ->visible(fn ($get) => ($get('total_amount') ?? 0) < 0)
                            ->helperText('Select the invoice this credit note applies to')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Generated PDF')
                    ->components([
                        TextInput::make('pdf_path')
                            ->disabled(),

                        DateTimePicker::make('generated_at')
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record?->isFinalized()),

                Section::make('Line Items')
                    ->components([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                TextInput::make('description')
                                    ->required()
                                    ->columnSpan(2),

                                Select::make('unit')
                                    ->options(InvoiceItemUnit::class)
                                    ->default(InvoiceItemUnit::Units)
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $unitPrice = (int) ($get('unit_price') ?? 0);
                                        $quantity = (int) ($state ?? 0);
                                        $set('total', $quantity * $unitPrice);
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->suffix('cents')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = (int) ($get('quantity') ?? 0);
                                        $unitPrice = (int) ($state ?? 0);
                                        $set('total', $quantity * $unitPrice);
                                    }),

                                TextInput::make('total')
                                    ->numeric()
                                    ->suffix('cents')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(6)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Add Line Item')
                            ->itemLabel(fn (array $state): ?string => $state['description'] ?? null),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
