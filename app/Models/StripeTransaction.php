<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StripeTransaction extends Model
{
    protected $fillable = [
        'stripe_account_id',
        'stripe_transaction_id',
        'type',
        'amount',
        'currency',
        'customer_name',
        'customer_email',
        'customer_address',
        'description',
        'metadata',
        'status',
        'is_complete',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
            'is_complete' => 'boolean',
            'transaction_date' => 'datetime',
        ];
    }

    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(StripeAccount::class);
    }

    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    public function checkIsComplete(): bool
    {
        return ! empty($this->customer_name)
            && ! empty($this->description)
            && $this->amount !== null
            && ! empty($this->currency);
    }

    public function updateCompleteStatus(): void
    {
        $isComplete = $this->checkIsComplete();

        $this->update([
            'is_complete' => $isComplete,
            'status' => $isComplete ? 'ready' : 'pending_review',
        ]);
    }
}
