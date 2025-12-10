<?php

namespace App\Filament\Resources\StripeTransactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StripeTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->components([
                        Select::make('stripe_account_id')
                            ->relationship('stripeAccount', 'account_name')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('stripe_transaction_id')
                            ->disabled(),
                        TextInput::make('type')
                            ->disabled(),
                        TextInput::make('amount')
                            ->numeric()
                            ->suffix('cents')
                            ->disabled(),
                        TextInput::make('currency')
                            ->disabled(),
                        DateTimePicker::make('transaction_date')
                            ->disabled(),
                        TextInput::make('status')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Customer Details')
                    ->description(fn ($record) => $record?->isInvoiced()
                        ? 'This transaction has been invoiced and cannot be edited.'
                        : 'Edit customer details below. Changes will mark the transaction as ready for invoicing.')
                    ->components([
                        TextInput::make('customer_name')
                            ->disabled(fn ($record) => $record?->isInvoiced()),
                        TextInput::make('customer_email')
                            ->email()
                            ->disabled(fn ($record) => $record?->isInvoiced()),
                        Textarea::make('customer_address')
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record?->isInvoiced()),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record?->isInvoiced()),
                    ])
                    ->columns(2),
            ]);
    }
}
