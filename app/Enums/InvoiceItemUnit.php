<?php

namespace App\Enums;

enum InvoiceItemUnit: string
{
    case Days = 'days';
    case Hours = 'hours';
    case Units = 'units';

    public function label(): string
    {
        return match ($this) {
            self::Days => 'Days',
            self::Hours => 'Hours',
            self::Units => 'Units',
        };
    }

    public function labelEs(): string
    {
        return match ($this) {
            self::Days => 'Días',
            self::Hours => 'Horas',
            self::Units => 'Unidades',
        };
    }
}
