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

    public function otherIncome(): HasOne
    {
        return $this->hasOne(OtherIncome::class);
    }

    public function isInvoiced(): bool
    {
        return $this->invoiceItem()->exists();
    }

    public function isOtherIncome(): bool
    {
        return $this->otherIncome()->exists();
    }

    public function isProcessed(): bool
    {
        return $this->isInvoiced() || $this->isOtherIncome();
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
        // Customer name is no longer required: sub-€400 transactions can be
        // issued as simplified invoices without full customer details. The
        // invoice generation flow classifies and validates threshold-vs-details
        // when the invoice is created.
        return ! empty($this->description)
            && $this->amount !== null
            && ! empty($this->currency);
    }

    public function canGenerateInvoice(): bool
    {
        return ! $this->isIgnored() && ! $this->isProcessed();
    }

    /**
     * True when an earlier transaction belonging to the same person still
     * needs handling — i.e. it isn't ignored and hasn't been turned into an
     * invoice or other-income record. Optionally treat the given IDs as
     * already accounted for (e.g. members of a bulk selection).
     *
     * @param  array<int>  $excludeIds
     */
    public function hasPriorUnprocessedTransactions(array $excludeIds = []): bool
    {
        $personId = $this->stripeAccount?->person_id;

        if ($personId === null || $this->transaction_date === null) {
            return false;
        }

        return static::query()
            ->whereHas('stripeAccount', fn ($q) => $q->where('person_id', $personId))
            ->where('status', '!=', 'ignored')
            ->whereDoesntHave('invoiceItem')
            ->whereDoesntHave('otherIncome')
            ->where(function ($q) {
                $q->where('transaction_date', '<', $this->transaction_date)
                    ->orWhere(function ($q2) {
                        $q2->where('transaction_date', '=', $this->transaction_date)
                            ->where('id', '<', $this->id);
                    });
            })
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->exists();
    }

    public function canConvertToOtherIncome(): bool
    {
        return $this->isReady() && ! $this->isProcessed();
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
