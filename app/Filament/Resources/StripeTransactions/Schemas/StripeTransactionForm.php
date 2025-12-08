<?php

namespace App\Filament\Resources\StripeTransactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StripeTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('stripe_account_id')
                    ->relationship('stripeAccount', 'id')
                    ->required(),
                TextInput::make('stripe_transaction_id')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required(),
                TextInput::make('customer_name'),
                TextInput::make('customer_email')
                    ->email(),
                Textarea::make('customer_address')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending_review'),
                Toggle::make('is_complete')
                    ->required(),
                DateTimePicker::make('transaction_date')
                    ->required(),
            ]);
    }
}
