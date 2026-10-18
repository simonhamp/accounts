<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Models\Brand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        $whenEmailing = fn ($get): bool => (bool) $get('email_invoices');

        return $schema
            ->components([
                Section::make('Brand')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Clickback'),
                    ])
                    ->columnSpanFull(),

                Section::make('Invoice emails')
                    ->description('Who invoice emails for this brand come from.')
                    ->components([
                        Toggle::make('email_invoices')
                            ->label('Email invoices to customers')
                            ->helperText('Each invoice the daily sync makes from a payment in the last 7 days is emailed to the customer, and invoices can be sent by hand. Older invoices are never sent automatically. While this is off, no invoice for this brand can be emailed.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('invoice_sender_name')
                            ->label('Sender name')
                            ->placeholder('Clickback')
                            ->helperText('Shown as who the email is from. The email says the invoice is issued by the invoice\'s person.')
                            ->maxLength(255)
                            ->required($whenEmailing),
                        TextInput::make('invoice_from_address')
                            ->label('From address')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Must be on a domain the mail server below may send for.')
                            ->required($whenEmailing),
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
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'es' => 'Spanish (Español)',
                            ])
                            ->default('en')
                            ->selectablePlaceholder(false)
                            ->required()
                            ->helperText('The language of the email and of the attached PDF.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Mail server')
                    ->description('Invoice emails for this brand go out through this SMTP server, not the app\'s default mailer.')
                    ->components([
                        TextInput::make('smtp_host')
                            ->label('Host')
                            ->placeholder('smtp.eu.mailgun.org')
                            ->maxLength(255)
                            ->required($whenEmailing),
                        TextInput::make('smtp_port')
                            ->label('Port')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('587')
                            ->required($whenEmailing),
                        Select::make('smtp_scheme')
                            ->label('Encryption')
                            ->options([
                                'smtps' => 'smtps: TLS from the start (usually port 465)',
                                'smtp' => 'smtp: STARTTLS when offered (usually port 587)',
                            ])
                            ->placeholder('Automatic: smtps on port 465, smtp otherwise'),
                        TextInput::make('smtp_username')
                            ->label('Username')
                            ->maxLength(255)
                            ->required($whenEmailing),
                        TextInput::make('smtp_password')
                            ->label('Password')
                            ->password()
                            ->autocomplete('new-password')
                            ->maxLength(255)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->placeholder(fn (?Brand $record): ?string => filled($record?->smtp_password) ? 'Saved. Leave blank to keep it.' : null)
                            ->helperText('Stored encrypted and never shown again.')
                            ->required(fn ($get, ?Brand $record): bool => (bool) $get('email_invoices') && blank($record?->smtp_password)),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
