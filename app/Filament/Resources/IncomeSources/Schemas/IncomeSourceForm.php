<?php

namespace App\Filament\Resources\IncomeSources\Schemas;

use App\Enums\BillingFrequency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IncomeSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Income Source Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Income Schedule')
                    ->description('Configure expected income frequency to help track when income is due.')
                    ->components([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Active sources will appear in the monthly checklist.')
                            ->default(true),

                        Select::make('billing_frequency')
                            ->label('Income Frequency')
                            ->options(BillingFrequency::class)
                            ->default(BillingFrequency::None)
                            ->live()
                            ->helperText('How often do you expect income from this source?'),

                        Select::make('billing_month')
                            ->label('Income Month')
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
                            ->helperText('Which month do you expect the annual income?'),
                    ]),
            ]);
    }
}
