<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerTaxRegion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Textarea::make('address')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('tax_id')
                    ->label('Tax ID / NIF / CIF')
                    ->maxLength(255),
                TextInput::make('country_code')
                    ->label('Country code (ISO)')
                    ->maxLength(2)
                    ->helperText('e.g. ES, GB, FR'),
                Select::make('tax_region')
                    ->label('Tax region')
                    ->options(CustomerTaxRegion::class)
                    ->placeholder('Unknown / not set')
                    ->helperText('Drives IGIC vs reverse-charge handling on invoices'),
            ])
            ->columns(2);
    }
}
