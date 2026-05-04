<?php

namespace App\Models;

use App\Enums\CustomerTaxRegion;
use App\Enums\InvoiceStatus;
use App\Enums\TaxRegime;
use App\Services\ExchangeRateService;
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
        'tax_base_total',
        'tax_total',
        'irpf_rate',
        'irpf_amount',
        'legal_notes',
        'write_off_amount',
        'amount_eur',
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
            'tax_base_total' => 'integer',
            'tax_total' => 'integer',
            'irpf_rate' => 'decimal:2',
            'irpf_amount' => 'integer',
            'write_off_amount' => 'integer',
            'amount_eur' => 'integer',
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

            // Calculate totals from line items
            if ($invoice->exists && $invoice->items()->exists()) {
                $invoice->tax_base_total = (int) $invoice->items()->sum('total');
                $invoice->tax_total = (int) $invoice->items()->sum('tax_amount');
                $invoice->irpf_amount = $invoice->irpf_rate
                    ? (int) round($invoice->tax_base_total * (float) $invoice->irpf_rate / 100)
                    : 0;
                $invoice->total_amount = $invoice->tax_base_total + $invoice->tax_total - $invoice->irpf_amount;
            }

            // Calculate EUR equivalent
            if ($invoice->currency && $invoice->invoice_date && $invoice->total_amount) {
                $invoice->amount_eur = app(ExchangeRateService::class)
                    ->convertToEur($invoice->total_amount, $invoice->currency, $invoice->invoice_date);
            }

            // Update current state hash
            $invoice->current_state_hash = $invoice->computeStateHash();
        });
    }

    public function computeStateHash(): string
    {
        $items = $this->exists
            ? $this->items()
                ->orderBy('id')
                ->get(['description', 'quantity', 'unit_price', 'total', 'tax_type', 'tax_rate', 'tax_amount'])
                ->toArray()
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
            'irpf_rate' => $this->irpf_rate,
            'legal_notes' => $this->legal_notes,
            'items' => $items,
        ];

        return hash('sha256', json_encode($state));
    }

    public function recalculateTotal(): void
    {
        $this->tax_base_total = (int) $this->items()->sum('total');
        $this->tax_total = (int) $this->items()->sum('tax_amount');
        $this->irpf_amount = $this->irpf_rate
            ? (int) round($this->tax_base_total * (float) $this->irpf_rate / 100)
            : 0;
        $this->total_amount = $this->tax_base_total + $this->tax_total - $this->irpf_amount;
        $this->saveQuietly();
    }

    /**
     * Group line items by (tax_type, tax_rate) for the totals breakdown.
     *
     * @return \Illuminate\Support\Collection<int, array{type: \App\Enums\TaxType|null, rate: float, base: int, tax: int}>
     */
    public function taxBreakdown(): \Illuminate\Support\Collection
    {
        return $this->items
            ->groupBy(fn (InvoiceItem $item) => ($item->tax_type?->value ?? 'none').':'.(string) $item->tax_rate)
            ->map(fn ($group) => [
                'type' => $group->first()->tax_type,
                'rate' => (float) $group->first()->tax_rate,
                'base' => (int) $group->sum('total'),
                'tax' => (int) $group->sum('tax_amount'),
            ])
            ->values();
    }

    public function hasTaxBreakdown(): bool
    {
        return $this->tax_total !== 0
            || ($this->irpf_amount ?? 0) !== 0
            || $this->items->contains(fn (InvoiceItem $item) => $item->tax_type !== null);
    }

    /**
     * True when a Canarias-regime issuer is invoicing a customer outside the
     * Canary Islands — IGIC does not apply and the invoice must state the
     * "Operación no sujeta a IGIC. Inversión del sujeto pasivo" clause.
     */
    public function requiresIgicReverseChargeNote(): bool
    {
        if ($this->person?->tax_regime !== TaxRegime::Canarias) {
            return false;
        }

        $region = $this->customer?->tax_region;

        return $region !== null && $region !== CustomerTaxRegion::Canarias;
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
        return $query->whereIn('status', [
            InvoiceStatus::ReadyToSend,
            InvoiceStatus::Sent,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ]);
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
