<?php

namespace App\Filament\Resources\People\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('address')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('city')
                    ->required(),
                TextInput::make('postal_code')
                    ->required(),
                TextInput::make('country')
                    ->required()
                    ->default('Spain'),
                TextInput::make('dni_nie')
                    ->required(),
                TextInput::make('invoice_prefix')
                    ->required(),
                TextInput::make('next_invoice_number')
                    ->required()
                    ->numeric()
                    ->default(1),
            ]);
    }
}
