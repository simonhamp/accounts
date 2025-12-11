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
        'customer_id',
        'parent_invoice_id',
        'bank_account_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'period_month',
        'period_year',
        'customer_name',
        'customer_address',
        'customer_tax_id',
        'total_amount',
        'write_off_amount',
        'currency',
        'pdf_path',
        'pdf_path_en',
        'generated_at',
        'status',
        'original_file_path',
        'extracted_data',
        'error_message',
        'current_state_hash',
        'generated_state_hash',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'period_month' => 'integer',
            'period_year' => 'integer',
            'total_amount' => 'integer',
            'write_off_amount' => 'integer',
            'generated_at' => 'datetime',
            'status' => InvoiceStatus::class,
            'extracted_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice) {
            // Calculate period from invoice_date
            if ($invoice->invoice_date) {
                $invoice->period_month = $invoice->invoice_date->month;
                $invoice->period_year = $invoice->invoice_date->year;
            }

            // Calculate total from line items (only if items exist and total wasn't explicitly set)
            if ($invoice->exists && $invoice->items()->exists()) {
                $invoice->total_amount = $invoice->items()->sum('total');
            }

            // Update current state hash
            $invoice->current_state_hash = $invoice->computeStateHash();
        });
    }

    public function computeStateHash(): string
    {
        $items = $this->exists
            ? $this->items()->orderBy('id')->get(['description', 'quantity', 'unit_price', 'total'])->toArray()
            : [];

        $state = [
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_address' => $this->customer_address,
            'customer_tax_id' => $this->customer_tax_id,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'bank_account_id' => $this->bank_account_id,
            'currency' => $this->currency,
            'items' => $items,
        ];

        return hash('sha256', json_encode($state));
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('total');
        $this->saveQuietly();
    }

    public function getPreviewInvoiceNumber(): ?string
    {
        if ($this->invoice_number) {
            return $this->invoice_number;
        }

        if (! $this->person) {
            return null;
        }

        return $this->person->getNextInvoiceNumber();
    }

    public function isDueOnReceipt(): bool
    {
        return $this->due_date === null;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
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
        return $query->where('status', InvoiceStatus::ReadyToSend);
    }

    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Paid);
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
        return in_array($this->status, [
            InvoiceStatus::ReadyToSend,
            InvoiceStatus::Sent,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ]);
    }

    public function isReadyToSend(): bool
    {
        return $this->status === InvoiceStatus::ReadyToSend;
    }

    public function isAwaitingPayment(): bool
    {
        return in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid]);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === InvoiceStatus::PartiallyPaid;
    }

    public function canBeFinalized(): bool
    {
        return $this->status->canBeFinalized();
    }

    public function canBeSent(): bool
    {
        return $this->status->canBeSent();
    }

    public function canRecordPayment(): bool
    {
        return $this->status->canRecordPayment();
    }

    public function canWriteOff(): bool
    {
        return $this->status->canWriteOff();
    }

    public function hasBeenModifiedSinceGeneration(): bool
    {
        // If no PDF has been generated yet, no modification to report
        if (! $this->generated_at) {
            return false;
        }

        // If we have hashes, compare them
        if ($this->generated_state_hash) {
            return $this->current_state_hash !== $this->generated_state_hash;
        }

        // Fallback for invoices generated before hash tracking was added
        return $this->updated_at->gt($this->generated_at);
    }

    public function markStateAsGenerated(): void
    {
        $this->update(['generated_state_hash' => $this->current_state_hash]);
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
        $this->update(['status' => InvoiceStatus::ReadyToSend]);
    }

    public function markAsSent(): void
    {
        $this->update(['status' => InvoiceStatus::Sent]);
    }

    public function markAsPartiallyPaid(): void
    {
        $this->update(['status' => InvoiceStatus::PartiallyPaid]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => InvoiceStatus::Paid]);
    }

    public function writeOff(int $amount): void
    {
        $this->update([
            'write_off_amount' => $amount,
            'status' => InvoiceStatus::Paid,
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => InvoiceStatus::Failed,
            'error_message' => $message,
        ]);
    }

    public function hasPaymentDetails(): bool
    {
        return $this->bank_account_id !== null;
    }
}
