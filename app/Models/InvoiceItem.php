<?php

namespace App\Models;

use App\Enums\InvoiceItemUnit;
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
    ];

    protected function casts(): array
    {
        return [
            'unit' => InvoiceItemUnit::class,
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
        ];
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
