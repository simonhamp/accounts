<?php

namespace App\Models;

use App\Enums\CustomerTaxRegion;
use App\Enums\InvoiceStatus;
use App\Enums\TaxRegime;
use App\Exceptions\InvoiceOrderingException;
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

    /**
     * Threshold in cents (€400.00) above which a simplified invoice is no
     * longer permitted under RD 1619/2012. Note: some sectors (retail,
     * restaurants, transport) qualify for a higher €3,000 threshold.
     */
    public const SIMPLIFIED_THRESHOLD_EUR_CENTS = 40000;

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
        'is_simplified',
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
            'is_simplified' => 'boolean',
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

            // Reallocate invoice number when an existing invoice is moved to a
            // different Person whose prefix differs. The old number is
            // abandoned (gap accepted) — we don't touch the previous Person's
            // counter or any other invoices.
            $invoice->reallocateNumberIfPersonChanged();

            // Enforce numerical/date ordering for newly-numbered invoices.
            $invoice->assertDateOrdering();

            // Calculate EUR equivalent
            if ($invoice->currency && $invoice->invoice_date && $invoice->total_amount) {
                $invoice->amount_eur = app(ExchangeRateService::class)
                    ->convertToEur($invoice->total_amount, $invoice->currency, $invoice->invoice_date);
            }

            $invoice->assertSimplifiedThreshold();
            $invoice->assertCustomerDetailsForFinalization();

            // Update current state hash
            $invoice->current_state_hash = $invoice->computeStateHash();
        });

        static::deleting(function (Invoice $invoice) {
            $invoice->assertDeletable();

            // When the most recent invoice in a series is deleted, roll back
            // the Person's counter so the next allocation reuses the number.
            // Skip when the invoice's prefix doesn't match the Person's
            // current prefix — that means the invoice was moved here from a
            // different series, so its number isn't tied to this counter.
            if ($invoice->person_id && $invoice->invoice_number) {
                $person = $invoice->person;
                $prefix = $invoice->invoicePrefix();

                if ($person && $prefix && $prefix === $person->invoice_prefix) {
                    $highest = static::query()
                        ->where('person_id', $invoice->person_id)
                        ->where('invoice_number', 'like', $prefix.'-%')
                        ->orderByDesc('invoice_number')
                        ->first();

                    if ($highest && $highest->id === $invoice->id) {
                        $person->decrement('next_invoice_number');
                    }
                }
            }
        });
    }

    /**
     * Extract the series prefix from this invoice's number (everything before
     * the final hyphen). Date ordering is enforced only within the same series.
     */
    public function invoicePrefix(): ?string
    {
        if (! $this->invoice_number) {
            return null;
        }
        $pos = strrpos($this->invoice_number, '-');

        return $pos === false ? null : substr($this->invoice_number, 0, $pos);
    }

    /**
     * Allocate a fresh invoice_number from the new Person's series when an
     * existing invoice is reassigned to a Person with a different prefix.
     * Does nothing for new (unsaved) invoices, invoices that don't yet have
     * a number, or moves between Persons that share a prefix.
     */
    public function reallocateNumberIfPersonChanged(): void
    {
        if (! $this->exists || ! $this->isDirty('person_id')) {
            return;
        }

        if (! $this->invoice_number || ! $this->person_id) {
            return;
        }

        $newPerson = Person::find($this->person_id);

        if (! $newPerson || ! $newPerson->invoice_prefix) {
            return;
        }

        if ($this->invoicePrefix() === $newPerson->invoice_prefix) {
            return;
        }

        $this->invoice_number = $newPerson->allocateNextInvoiceNumber();
    }

    /**
     * @throws InvoiceOrderingException
     */
    public function assertDateOrdering(): void
    {
        if (! $this->person_id || ! $this->invoice_number || ! $this->invoice_date) {
            return;
        }

        $prefix = $this->invoicePrefix();
        if (! $prefix) {
            return;
        }

        $previous = static::query()
            ->where('person_id', $this->person_id)
            ->where('invoice_number', 'like', $prefix.'-%')
            ->where('invoice_number', '<', $this->invoice_number)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->orderByDesc('invoice_number')
            ->first();

        if ($previous && $this->invoice_date->lt($previous->invoice_date)) {
            throw InvoiceOrderingException::dateBeforePrevious(
                $this->invoice_date->format('Y-m-d'),
                $previous->invoice_number,
                $previous->invoice_date->format('Y-m-d'),
            );
        }

        $next = static::query()
            ->where('person_id', $this->person_id)
            ->where('invoice_number', 'like', $prefix.'-%')
            ->where('invoice_number', '>', $this->invoice_number)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
            ->orderBy('invoice_number')
            ->first();

        if ($next && $this->invoice_date->gt($next->invoice_date)) {
            throw InvoiceOrderingException::dateAfterNext(
                $this->invoice_date->format('Y-m-d'),
                $next->invoice_number,
                $next->invoice_date->format('Y-m-d'),
            );
        }
    }

    public function effectiveAmountEur(): int
    {
        return (int) ($this->amount_eur ?? $this->total_amount);
    }

    public function isAboveSimplifiedThreshold(): bool
    {
        return abs($this->effectiveAmountEur()) > self::SIMPLIFIED_THRESHOLD_EUR_CENTS;
    }

    /**
     * Returns the list of full-invoice customer fields that are missing.
     * Used to flag full invoices over €400 lacking required details.
     *
     * @return array<int, string>
     */
    public function missingFullInvoiceCustomerFields(): array
    {
        $missing = [];

        if (empty($this->customer_name)) {
            $missing[] = 'customer name';
        }
        if (empty($this->customer_address)) {
            $missing[] = 'customer address';
        }
        if (empty($this->customer_tax_id)) {
            $missing[] = 'customer tax ID';
        }

        return $missing;
    }

    /**
     * @throws InvoiceOrderingException
     */
    public function assertSimplifiedThreshold(): void
    {
        if (! $this->is_simplified) {
            return;
        }

        if ($this->isAboveSimplifiedThreshold()) {
            throw InvoiceOrderingException::simplifiedAboveThreshold(
                $this->invoice_number ?? 'unnumbered',
                $this->effectiveAmountEur(),
                self::SIMPLIFIED_THRESHOLD_EUR_CENTS,
            );
        }
    }

    /**
     * Block finalization of full invoices over the €400 threshold when
     * customer details are incomplete. Pending/draft invoices are allowed
     * through so the user can save partial progress.
     *
     * @throws InvoiceOrderingException
     */
    public function assertCustomerDetailsForFinalization(): void
    {
        if ($this->is_simplified) {
            return;
        }

        if (! $this->isAboveSimplifiedThreshold()) {
            return;
        }

        $finalizedStatuses = [
            InvoiceStatus::ReadyToSend,
            InvoiceStatus::Sent,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
        ];

        if (! in_array($this->status, $finalizedStatuses, strict: true)) {
            return;
        }

        $missing = $this->missingFullInvoiceCustomerFields();

        if (! empty($missing)) {
            throw InvoiceOrderingException::fullInvoiceMissingCustomerDetails(
                $this->invoice_number ?? 'unnumbered',
                $missing,
            );
        }
    }

    /**
     * @throws InvoiceOrderingException
     */
    public function assertDeletable(): void
    {
        if ($this->isFinalized()) {
            throw InvoiceOrderingException::cannotDeleteFinalized($this->invoice_number ?? 'unknown');
        }

        // Pending/extracted/reviewed invoices without a number can be deleted freely.
        if (! $this->invoice_number || ! $this->person_id) {
            return;
        }

        $prefix = $this->invoicePrefix();
        if (! $prefix) {
            return;
        }

        $highest = static::query()
            ->where('person_id', $this->person_id)
            ->where('invoice_number', 'like', $prefix.'-%')
            ->orderByDesc('invoice_number')
            ->first();

        if ($highest && $highest->id !== $this->id) {
            throw InvoiceOrderingException::cannotDeleteWithGap(
                $this->invoice_number,
                $highest->invoice_number,
            );
        }
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
            'person_id' => $this->person_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_address' => $this->customer_address,
            'customer_tax_id' => $this->customer_tax_id,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'bank_account_id' => $this->bank_account_id,
            'currency' => $this->currency,
            'irpf_rate' => $this->irpf_rate,
            'is_simplified' => (bool) $this->is_simplified,
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

    public function needsTaxId(): bool
    {
        // Invoices over €400 should have customer tax ID
        $threshold = 40000; // €400 in cents

        return ($this->amount_eur ?? 0) > $threshold && empty($this->customer_tax_id);
    }
}
