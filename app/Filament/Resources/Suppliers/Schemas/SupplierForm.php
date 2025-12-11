<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Enums\BillingFrequency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Supplier Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label('Tax ID')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('address')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Billing Schedule')
                    ->description('Configure expected billing frequency to help track when bills are due.')
                    ->components([
                        Toggle::make('is_active')
                            ->label('Active supplier')
                            ->helperText('Active suppliers will appear in the expected bills checklist.')
                            ->default(true),

                        Select::make('billing_frequency')
                            ->label('Billing Frequency')
                            ->options(BillingFrequency::class)
                            ->default(BillingFrequency::None)
                            ->live()
                            ->helperText('How often do you expect bills from this supplier?'),

                        Select::make('billing_month')
                            ->label('Billing Month')
                            ->options([
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December',
                            ])
                            ->visible(fn ($get) => in_array($get('billing_frequency'), [BillingFrequency::Annual, BillingFrequency::Annual->value], true))
                            ->required(fn ($get) => in_array($get('billing_frequency'), [BillingFrequency::Annual, BillingFrequency::Annual->value], true))
                            ->helperText('Which month do you expect the annual bill?'),
                    ]),
            ]);
    }
}
