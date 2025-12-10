<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Details')
                    ->components([
                        TextInput::make('name')
                            ->label('Account Name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name to identify this account'),
                        TextInput::make('bank_name')
                            ->label('Bank Name')
                            ->maxLength(255),
                        Select::make('currency')
                            ->options([
                                'EUR' => 'EUR - Euro',
                                'USD' => 'USD - US Dollar',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('EUR')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive accounts will not appear in the account dropdown.')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Account Numbers')
                    ->description('Enter the relevant account identifiers for this bank account.')
                    ->components([
                        TextInput::make('account_number')
                            ->label('Account Number')
                            ->maxLength(255),
                        TextInput::make('sort_code')
                            ->label('Sort Code')
                            ->maxLength(255)
                            ->placeholder('00-00-00'),
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->maxLength(255),
                        TextInput::make('swift_bic')
                            ->label('SWIFT/BIC')
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Notes')
                    ->components([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
