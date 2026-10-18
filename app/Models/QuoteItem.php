<?php

namespace App\Models;

use App\Enums\InvoiceItemUnit;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    /** @use HasFactory<\Database\Factories\QuoteItemFactory> */
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'description',
        'unit',
        'quantity',
        'unit_price',
        'total',
        'tax_type',
        'tax_rate',
        'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'unit' => InvoiceItemUnit::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'integer',
            'total' => 'integer',
            'tax_type' => TaxType::class,
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QuoteItem $item) {
            $item->tax_amount = (int) round((int) $item->total * (float) $item->tax_rate / 100);
        });
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
