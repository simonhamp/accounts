<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\InvoiceItemUnit;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
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
                            ->required()
                            ->live()
                            ->helperText('Required - determines the invoice number'),

                        Placeholder::make('invoice_number_preview')
                            ->label('Invoice Number')
                            ->content(fn ($record, $get) => $record?->invoice_number
                                ?? ($get('person_id')
                                    ? \App\Models\Person::find($get('person_id'))?->getNextInvoiceNumber()
                                    : 'Select a person first'))
                            ->helperText('Auto-generated on save based on selected person'),

                        DatePicker::make('invoice_date')
                            ->native(false)
                            ->required()
                            ->default(now()),

                        DatePicker::make('due_date')
                            ->native(false)
                            ->helperText('Leave empty for "Due on Receipt"'),
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
                                        // Only pre-populate if fields are empty
                                        if (empty($get('customer_name'))) {
                                            $set('customer_name', $customer->name);
                                        }
                                        if (empty($get('customer_address'))) {
                                            $set('customer_address', $customer->address);
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
                            ])
                            ->hintAction(
                                Action::make('viewCustomer')
                                    ->label('View Customer')
                                    ->icon('heroicon-o-eye')
                                    ->modalHeading(fn ($get) => Customer::find($get('customer_id'))?->name ?? 'Customer Details')
                                    ->modalContent(function ($get) {
                                        $customer = Customer::find($get('customer_id'));
                                        if (! $customer) {
                                            return new HtmlString('<p class="text-gray-500">No customer selected</p>');
                                        }

                                        return new HtmlString(
                                            '<div class="space-y-4">'.
                                            '<div><strong class="text-gray-500 dark:text-gray-400">Name:</strong><br>'
                                                .e($customer->name).'</div>'.
                                            '<div><strong class="text-gray-500 dark:text-gray-400">Email:</strong><br>'
                                                .($customer->email ? e($customer->email) : '<span class="text-gray-400">Not set</span>').'</div>'.
                                            '<div><strong class="text-gray-500 dark:text-gray-400">Address:</strong><br>'
                                                .($customer->address ? nl2br(e($customer->address)) : '<span class="text-gray-400">Not set</span>').'</div>'.
                                            '<div><strong class="text-gray-500 dark:text-gray-400">Total Invoices:</strong><br>'
                                                .$customer->invoices()->count().'</div>'.
                                            '</div>'
                                        );
                                    })
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Close')
                                    ->visible(fn ($get) => ! empty($get('customer_id')))
                            )
                            ->helperText('Select a customer to pre-fill details below'),

                        TextInput::make('customer_name')
                            ->helperText('Leave empty to use customer record name'),

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

                        Select::make('parent_invoice_id')
                            ->label('Original Invoice (for Credit Note)')
                            ->relationship('parentInvoice', 'invoice_number')
                            ->getOptionLabelFromRecordUsing(fn (Invoice $record) => "{$record->invoice_number} - {$record->customer_name}")
                            ->searchable(['invoice_number', 'customer_name'])
                            ->preload()
                            ->visible(fn ($record) => $record?->total_amount < 0)
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
