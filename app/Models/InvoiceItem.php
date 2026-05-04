<?php

namespace App\Models;

use App\Enums\InvoiceItemUnit;
use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'stripe_transaction_id',
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
        static::saving(function (InvoiceItem $item) {
            $item->tax_amount = (int) round((int) $item->total * (float) $item->tax_rate / 100);
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function stripeTransaction(): BelongsTo
    {
        return $this->belongsTo(StripeTransaction::class);
    }
}
