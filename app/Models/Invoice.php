<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'parent_invoice_id',
        'invoice_number',
        'invoice_date',
        'period_month',
        'period_year',
        'customer_name',
        'customer_address',
        'customer_tax_id',
        'total_amount',
        'currency',
        'pdf_path',
        'pdf_path_en',
        'generated_at',
        'status',
        'original_file_path',
        'extracted_data',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'period_month' => 'integer',
            'period_year' => 'integer',
            'total_amount' => 'integer',
            'generated_at' => 'datetime',
            'status' => InvoiceStatus::class,
            'extracted_data' => 'array',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function parentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isCreditNote(): bool
    {
        return $this->total_amount < 0;
    }

    public function stripeTransactions(): HasManyThrough
    {
        return $this->hasManyThrough(
            StripeTransaction::class,
            InvoiceItem::class,
            'invoice_id',
            'id',
            'id',
            'stripe_transaction_id'
        );
    }

    public function hasStripeTransactions(): bool
    {
        return $this->items()->whereNotNull('stripe_transaction_id')->exists();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Pending,
            InvoiceStatus::Extracted,
            InvoiceStatus::Reviewed,
        ]);
    }

    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Finalized);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Failed);
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isFinalized(): bool
    {
        return $this->status === InvoiceStatus::Finalized;
    }

    public function canBeFinalized(): bool
    {
        return $this->status->canBeFinalized();
    }

    public function markAsExtracted(): void
    {
        $this->update(['status' => InvoiceStatus::Extracted]);
    }

    public function markAsReviewed(): void
    {
        $this->update(['status' => InvoiceStatus::Reviewed]);
    }

    public function markAsFinalized(): void
    {
        $this->update(['status' => InvoiceStatus::Finalized]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => InvoiceStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
