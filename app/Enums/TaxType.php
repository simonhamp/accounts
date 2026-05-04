<?php

namespace App\Enums;

enum TaxType: string
{
    case Iva = 'iva';
    case Igic = 'igic';
    case Ipsi = 'ipsi';
    case Exempt = 'exempt';
    case ReverseCharge = 'reverse_charge';
    case NotSubject = 'not_subject';

    public function label(): string
    {
        return match ($this) {
            self::Iva => 'IVA',
            self::Igic => 'IGIC',
            self::Ipsi => 'IPSI',
            self::Exempt => 'Exento',
            self::ReverseCharge => 'Inversión del sujeto pasivo',
            self::NotSubject => 'No sujeto',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::Iva => 'VAT',
            self::Igic => 'IGIC',
            self::Ipsi => 'IPSI',
            self::Exempt => 'Exempt',
            self::ReverseCharge => 'Reverse charge',
            self::NotSubject => 'Not subject',
        };
    }

    public function isTaxable(): bool
    {
        return in_array($this, [self::Iva, self::Igic, self::Ipsi]);
    }
}
