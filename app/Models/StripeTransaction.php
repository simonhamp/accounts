<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StripeTransaction extends Model
{
    use HasFactory;

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
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'metadata' => 'array',
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

    public function isInvoiced(): bool
    {
        return $this->invoiceItem()->exists();
    }

    public function isIgnored(): bool
    {
        return $this->status === 'ignored';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isComplete(): bool
    {
        return ! empty($this->customer_name)
            && ! empty($this->description)
            && $this->amount !== null
            && ! empty($this->currency);
    }

    public function canGenerateInvoice(): bool
    {
        return $this->isReady() && ! $this->isInvoiced();
    }

    public function updateCompleteStatus(): void
    {
        if ($this->isIgnored()) {
            return;
        }

        $this->update([
            'status' => $this->isComplete() ? 'ready' : 'pending_review',
        ]);
    }

    public function markAsIgnored(): void
    {
        $this->update(['status' => 'ignored']);
    }

    public function markAsReady(): void
    {
        $this->update(['status' => 'ready']);
    }
}
