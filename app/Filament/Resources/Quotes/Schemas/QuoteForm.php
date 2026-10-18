<?php

namespace App\Filament\Resources\Quotes\Schemas;

use App\Enums\CustomerTaxRegion;
use App\Enums\InvoiceItemUnit;
use App\Enums\TaxType;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QuoteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Status')
                    ->components([
                        Placeholder::make('status_display')
                            ->label('Current Status')
                            ->content(fn ($record) => $record?->status?->label() ?? 'New'),

                        Placeholder::make('invoice_display')
                            ->label('Invoice')
                            ->content(fn ($record) => $record?->invoice?->invoice_number)
                            ->visible(fn ($record) => $record?->invoice_id !== null),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record !== null),

                Section::make('Quote Details')
                    ->components([
                        Select::make('person_id')
                            ->relationship('person', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->default(fn () => \App\Models\Person::default()?->id)
                            ->helperText('Required - determines the quote number'),

                        Placeholder::make('quote_number_preview')
                            ->label('Quote Number')
                            ->content(fn ($record, $get) => $record?->quote_number
                                ?? ($get('person_id')
                                    ? \App\Models\Person::find($get('person_id'))?->getNextQuoteNumber()
                                    : 'Select a person first'))
                            ->helperText('Auto-generated on save based on selected person'),

                        DatePicker::make('quote_date')
                            ->native(false)
                            ->required()
                            ->default(now()),

                        DatePicker::make('valid_until')
                            ->native(false)
                            ->helperText('Leave empty if the quote has no expiry date'),
                    ])
                    ->columns(2),

                Section::make('Customer Details')
                    ->components([
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state) {
                                    $customer = Customer::find($state);
                                    if ($customer) {
                                        if (empty($get('customer_name'))) {
                                            $set('customer_name', $customer->name);
                                        }
                                        if (empty($get('customer_address'))) {
                                            $set('customer_address', $customer->address);
                                        }
                                        if (empty($get('customer_tax_id')) && $customer->tax_id) {
                                            $set('customer_tax_id', $customer->tax_id);
                                        }
                                    }
                                }
                            })
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                Textarea::make('address')
                                    ->rows(3),
                                TextInput::make('tax_id')
                                    ->label('Tax ID / NIF / CIF')
                                    ->maxLength(255),
                                TextInput::make('country_code')
                                    ->label('Country code (ISO)')
                                    ->maxLength(2)
                                    ->helperText('e.g. ES, GB, FR'),
                                Select::make('tax_region')
                                    ->label('Tax region')
                                    ->options(CustomerTaxRegion::class)
                                    ->placeholder('Unknown / not set'),
                            ])
                            ->hintAction(
                                Action::make('viewCustomer')
                                    ->label('View Customer')
                                    ->icon('heroicon-o-eye')
                                    ->url(fn ($get) => CustomerResource::getUrl('edit', ['record' => $get('customer_id')]))
                                    ->openUrlInNewTab()
                                    ->visible(fn ($get) => ! empty($get('customer_id')))
                            )
                            ->helperText('Select a customer to pre-fill details below'),

                        TextInput::make('customer_name')
                            ->helperText('Leave empty to use customer record name'),

                        Textarea::make('customer_address')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Leave empty to use customer record address'),

                        TextInput::make('customer_tax_id'),
                    ])
                    ->columns(2),

                Section::make('Amounts')
                    ->components([
                        Select::make('currency')
                            ->options([
                                'EUR' => 'EUR - Euro',
                                'USD' => 'USD - US Dollar',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('EUR')
                            ->required(),

                        Placeholder::make('total_amount_display')
                            ->label('Total Amount')
                            ->content(function ($record) {
                                if (! $record?->exists) {
                                    return 'Will be calculated from line items';
                                }

                                $total = $record->items()->sum('total');
                                $currency = $record->currency ?? 'EUR';

                                return number_format($total / 100, 2).' '.$currency;
                            })
                            ->helperText('Automatically calculated from line items'),
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
                    ->visible(fn ($record) => $record?->pdf_path !== null),

                Section::make('Tax & Withholding')
                    ->components([
                        TextInput::make('irpf_rate')
                            ->label('IRPF Withholding Rate (%)')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Leave empty if no IRPF withholding applies. Common rates: 7%, 15%, 19%.'),

                        Textarea::make('legal_notes')
                            ->label('Legal notes / Tax clauses')
                            ->rows(2)
                            ->helperText('e.g. "Operación exenta de IVA según artículo 20 LIVA" or "Inversión del sujeto pasivo"')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(fn ($record) => ! $record?->irpf_rate && ! $record?->legal_notes),

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
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $unitPrice = (int) ($get('unit_price') ?? 0);
                                        $quantity = (float) ($state ?? 0);
                                        $set('total', (int) round($quantity * $unitPrice));
                                    }),

                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->suffix('cents')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $quantity = (float) ($get('quantity') ?? 0);
                                        $unitPrice = (int) ($state ?? 0);
                                        $set('total', (int) round($quantity * $unitPrice));
                                    }),

                                TextInput::make('total')
                                    ->numeric()
                                    ->suffix('cents')
                                    ->disabled()
                                    ->dehydrated(),

                                Select::make('tax_type')
                                    ->options(TaxType::class)
                                    ->placeholder('No tax')
                                    ->columnSpan(2),

                                TextInput::make('tax_rate')
                                    ->label('Tax %')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->default(0)
                                    ->columnSpan(2),
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
