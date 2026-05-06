<?php

namespace App\Enums;

enum CustomerTaxRegion: string
{
    case Canarias = 'canarias';
    case PeninsulaBaleares = 'peninsula_baleares';
    case CeutaMelilla = 'ceuta_melilla';
    case EuropeanUnion = 'eu';
    case NonEu = 'non_eu';

    public function label(): string
    {
        return match ($this) {
            self::Canarias => 'Canary Islands',
            self::PeninsulaBaleares => 'Mainland Spain & Balearic Islands',
            self::CeutaMelilla => 'Ceuta & Melilla',
            self::EuropeanUnion => 'European Union (excl. Spain)',
            self::NonEu => 'Outside the EU',
        };
    }
}
