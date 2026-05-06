<?php

namespace App\Filament\Resources\People\Schemas;

use App\Enums\EntityType;
use App\Enums\TaxRegime;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PersonForm
{
    /**
     * Normalize the form-state value to an EntityType enum, since Filament
     * may pass either the enum instance (when filled from a cast model) or
     * the string value (when the Select dehydrates a freshly-picked option).
     */
    private static function entityType(callable $get): ?EntityType
    {
        $value = $get('entity_type');

        if ($value instanceof EntityType) {
            return $value;
        }

        return is_string($value) ? EntityType::tryFrom($value) : null;
    }

    private static function isLegalEntity(callable $get): bool
    {
        return in_array(self::entityType($get), [
            EntityType::SociedadLimitada,
            EntityType::SociedadAnonima,
        ], strict: true);
    }

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
                    ->visible(fn (callable $get) => self::entityType($get) === EntityType::Individual),
                TextInput::make('cif')
                    ->label('CIF')
                    ->visible(fn (callable $get) => self::isLegalEntity($get)),
                Textarea::make('registro_mercantil')
                    ->label('Registro Mercantil')
                    ->helperText('e.g. Inscrita en el Registro Mercantil de Las Palmas, Tomo X, Folio Y, Hoja Z')
                    ->columnSpanFull()
                    ->visible(fn (callable $get) => self::isLegalEntity($get)),
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
                Toggle::make('is_default')
                    ->label('Default issuer')
                    ->helperText('Pre-selected when creating invoices, importing bills, Stripe transactions and other income. Only one person should be the default.'),
            ]);
    }
}
