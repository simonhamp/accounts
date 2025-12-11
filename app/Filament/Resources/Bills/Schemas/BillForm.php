<?php

namespace App\Filament\Resources\Bills\Schemas;

use App\Enums\BillStatus;
use App\Enums\InvoiceItemUnit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class BillForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Original Document')
                    ->components([
                        Placeholder::make('document_preview')
                            ->label('')
                            ->content(function ($record) {
                                $url = route('bills.original-pdf', $record);
                                $extension = strtolower(pathinfo($record->original_file_path, PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);

                                if ($isImage) {
                                    return new HtmlString(
                                        '<img src="'.$url.'" class="rounded-lg border border-gray-200 dark:border-gray-700" style="max-width: 100%; max-height: 600px; height: auto; object-fit: contain;" />'
                                    );
                                }

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
                            ->visible(fn ($record) => $record?->status === BillStatus::Failed),
                    ])
                    ->visible(fn ($record) => $record !== null),

                Section::make('Bill Details')
                    ->components([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required(),
                                TextInput::make('tax_id')
                                    ->label('Tax ID'),
                                TextInput::make('email')
                                    ->email(),
                                Textarea::make('address'),
                            ])
                            ->helperText(fn ($record) => $record?->isPending()
                                ? 'Required before approving'
                                : null),

                        TextInput::make('bill_number')
                            ->label('Supplier Invoice Number'),

                        DatePicker::make('bill_date')
                            ->native(false),

                        DatePicker::make('due_date')
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make('Amounts')
                    ->components([
                        TextInput::make('total_amount')
                            ->numeric()
                            ->suffix('cents')
                            ->helperText('Amount in cents (e.g., 10000 = 100.00)')
                            ->live(onBlur: true),

                        TextInput::make('currency')
                            ->default('EUR')
                            ->maxLength(3),
                    ])
                    ->columns(2),

                Section::make('Notes')
                    ->components([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

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
                                    ->minValue(0.0001)
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
