<?php

namespace App\Models;

use App\Enums\InvoiceItemUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    /** @use HasFactory<\Database\Factories\BillItemFactory> */
    use HasFactory;

    protected $fillable = [
        'bill_id',
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
            'quantity' => 'decimal:4',
            'unit_price' => 'integer',
            'total' => 'integer',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
