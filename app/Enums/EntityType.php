<?php

namespace App\Enums;

enum EntityType: string
{
    case Individual = 'individual';
    case SociedadLimitada = 'sociedad_limitada';
    case SociedadAnonima = 'sociedad_anonima';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual / Autónomo',
            self::SociedadLimitada => 'Sociedad Limitada (S.L.)',
            self::SociedadAnonima => 'Sociedad Anónima (S.A.)',
        };
    }

    public function isLegalEntity(): bool
    {
        return $this !== self::Individual;
    }

    public function legalSuffix(): ?string
    {
        return match ($this) {
            self::SociedadLimitada => 'S.L.',
            self::SociedadAnonima => 'S.A.',
            self::Individual => null,
        };
    }
}
