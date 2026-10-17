<?php

namespace App\Filament\Resources\StripeAccounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StripeAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->relationship('person', 'name')
                    ->required()
                    ->default(fn () => \App\Models\Person::default()?->id),
                TextInput::make('account_name')
                    ->required(),
                Textarea::make('api_key')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('last_synced_at'),

                Section::make('Invoice emails')
                    ->description('Who invoices from this account come from when they are emailed to customers, whether by the daily sync or with Send.')
                    ->components([
                        Toggle::make('email_invoices')
                            ->label('Email invoices to customers')
                            ->helperText('Each invoice the daily sync makes from a payment in the last 7 days is emailed to the customer. Older invoices are never sent automatically.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('invoice_sender_name')
                            ->label('Sender name')
                            ->placeholder('Clickback')
                            ->helperText('Shown as who the email is from. The email says the invoice is issued by the person this account belongs to.')
                            ->maxLength(255)
                            ->required(fn ($get) => (bool) $get('email_invoices')),
                        TextInput::make('invoice_from_address')
                            ->label('From address')
                            ->email()
                            ->maxLength(255)
                            ->helperText('The mail server must be allowed to send from this domain.')
                            ->required(fn ($get) => (bool) $get('email_invoices')),
                        TextInput::make('invoice_reply_to')
                            ->label('Reply-to address')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('invoice_bcc')
                            ->label('BCC address')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Gets a copy of every invoice email.'),
                        Select::make('invoice_language')
                            ->label('PDF language')
                            ->options([
                                'en' => 'English',
                                'es' => 'Spanish (Español)',
                            ])
                            ->default('en')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->helperText('The language of the attached PDF and of the email.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
