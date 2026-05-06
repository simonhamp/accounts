<?php

namespace App\Enums;

enum TaxRegime: string
{
    case PeninsulaBaleares = 'peninsula_baleares';
    case Canarias = 'canarias';
    case CeutaMelilla = 'ceuta_melilla';

    public function label(): string
    {
        return match ($this) {
            self::PeninsulaBaleares => 'Península y Baleares (IVA)',
            self::Canarias => 'Canarias (IGIC)',
            self::CeutaMelilla => 'Ceuta y Melilla (IPSI)',
        };
    }

    public function defaultTaxType(): TaxType
    {
        return match ($this) {
            self::PeninsulaBaleares => TaxType::Iva,
            self::Canarias => TaxType::Igic,
            self::CeutaMelilla => TaxType::Ipsi,
        };
    }
}
