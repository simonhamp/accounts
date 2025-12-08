<?php

namespace App\Filament\Resources\StripeAccounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class StripeAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('person_id')
                    ->relationship('person', 'name')
                    ->required(),
                TextInput::make('account_name')
                    ->required(),
                Textarea::make('api_key')
                    ->required()
                    ->columnSpanFull(),
                DateTimePicker::make('last_synced_at'),
            ]);
    }
}
