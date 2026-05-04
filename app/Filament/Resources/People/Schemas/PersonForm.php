<?php

namespace App\Filament\Resources\People\Schemas;

use App\Enums\EntityType;
use App\Enums\TaxRegime;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                Select::make('entity_type')
                    ->options(EntityType::class)
                    ->default(EntityType::Individual)
                    ->required()
                    ->live(),
                TextInput::make('dni_nie')
                    ->label('DNI/NIE')
                    ->visible(fn ($get) => $get('entity_type') === EntityType::Individual->value),
                TextInput::make('cif')
                    ->label('CIF')
                    ->visible(fn ($get) => in_array($get('entity_type'), [
                        EntityType::SociedadLimitada->value,
                        EntityType::SociedadAnonima->value,
                    ])),
                Textarea::make('registro_mercantil')
                    ->label('Registro Mercantil')
                    ->helperText('e.g. Inscrita en el Registro Mercantil de Las Palmas, Tomo X, Folio Y, Hoja Z')
                    ->columnSpanFull()
                    ->visible(fn ($get) => in_array($get('entity_type'), [
                        EntityType::SociedadLimitada->value,
                        EntityType::SociedadAnonima->value,
                    ])),
                TextInput::make('share_capital')
                    ->label('Share Capital (cents)')
                    ->numeric()
                    ->helperText('Capital social desembolsado, in cents (e.g. 300000 = 3.000€)')
                    ->visible(fn ($get) => in_array($get('entity_type'), [
                        EntityType::SociedadLimitada->value,
                        EntityType::SociedadAnonima->value,
                    ])),
                Select::make('tax_regime')
                    ->options(TaxRegime::class)
                    ->default(TaxRegime::PeninsulaBaleares)
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
