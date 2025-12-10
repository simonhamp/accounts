<?php

namespace App\Enums;

enum BillingFrequency: string
{
    case None = 'none';
    case Monthly = 'monthly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No regular billing',
            self::Monthly => 'Monthly',
            self::Annual => 'Annual',
        };
    }

    public function hasRegularBilling(): bool
    {
        return $this !== self::None;
    }
}
