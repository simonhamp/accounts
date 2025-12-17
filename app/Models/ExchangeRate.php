<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'date',
        'from_currency',
        'to_currency',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'rate' => 'decimal:6',
        ];
    }

    /**
     * Scope to find a rate for a specific date and currency.
     */
    public function scopeForDateAndCurrency(Builder $query, Carbon $date, string $currency): Builder
    {
        return $query->whereDate('date', $date)
            ->where('from_currency', $currency)
            ->where('to_currency', 'EUR');
    }

    /**
     * Scope to find the most recent rate for a currency on or before a date.
     */
    public function scopeLatestForCurrency(Builder $query, Carbon $date, string $currency): Builder
    {
        return $query->where('from_currency', $currency)
            ->where('to_currency', 'EUR')
            ->whereDate('date', '<=', $date)
            ->orderBy('date', 'desc');
    }
}
