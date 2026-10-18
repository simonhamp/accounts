<?php

namespace App\Models;

use App\Enums\CustomerTaxRegion;
use App\Enums\QuoteStatus;
use App\Enums\TaxRegime;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    /** @use HasFactory<\Database\Factories\QuoteFactory> */
    use HasFactory;

    protected $fillable = [
        'person_id',
        'customer_id',
        'invoice_id',
        'quote_number',
        'quote_date',
        'valid_until',
        'customer_name',
        'customer_address',
        'customer_tax_id',
        'total_amount',
        'tax_base_total',
        'tax_total',
        'irpf_rate',
        'irpf_amount',
        'amount_eur',
        'currency',
        'legal_notes',
        'pdf_path',
        'pdf_path_en',
        'generated_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'total_amount' => 'integer',
            'tax_base_total' => 'integer',
            'tax_total' => 'integer',
            'irpf_rate' => 'decimal:2',
            'irpf_amount' => 'integer',
            'amount_eur' => 'integer',
            'generated_at' => 'datetime',
            'status' => QuoteStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Quote $quote) {
            if ($quote->exists && $quote->items()->exists()) {
                $quote->tax_base_total = (int) $quote->items()->sum('total');
                $quote->tax_total = (int) $quote->items()->sum('tax_amount');
                $quote->irpf_amount = $quote->irpf_rate
                    ? (int) round($quote->tax_base_total * (float) $quote->irpf_rate / 100)
                    : 0;
                $quote->total_amount = $quote->tax_base_total + $quote->tax_total - $quote->irpf_amount;
            }

            if ($quote->currency && $quote->quote_date && $quote->total_amount) {
                $quote->amount_eur = app(ExchangeRateService::class)
                    ->convertToEur($quote->total_amount, $quote->currency, $quote->quote_date);
            }
        });
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
            ->groupBy(fn (QuoteItem $item) => ($item->tax_type?->value ?? 'none').':'.(string) $item->tax_rate)
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
            || $this->items->contains(fn (QuoteItem $item) => $item->tax_type !== null);
    }

    /**
     * True when a Canarias-regime issuer is quoting a customer outside the
     * Canary Islands — IGIC does not apply and the quote should state the
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

    public function getPreviewQuoteNumber(): ?string
    {
        if ($this->quote_number) {
            return $this->quote_number;
        }

        if (! $this->person) {
            return null;
        }

        return $this->person->getNextQuoteNumber();
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [QuoteStatus::Draft, QuoteStatus::Sent]);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', QuoteStatus::Accepted);
    }

    public function isDraft(): bool
    {
        return $this->status === QuoteStatus::Draft;
    }

    public function isAccepted(): bool
    {
        return $this->status === QuoteStatus::Accepted;
    }

    public function isRejected(): bool
    {
        return $this->status === QuoteStatus::Rejected;
    }

    public function isInvoiced(): bool
    {
        return $this->status === QuoteStatus::Invoiced;
    }

    public function canBeSent(): bool
    {
        return $this->status->canBeSent();
    }

    public function canBeAccepted(): bool
    {
        return $this->status->canBeAccepted();
    }

    public function canBeRejected(): bool
    {
        return $this->status->canBeRejected();
    }

    public function canCreateInvoice(): bool
    {
        return $this->status->canCreateInvoice();
    }

    public function markAsSent(): void
    {
        $this->update(['status' => QuoteStatus::Sent]);
    }

    public function markAsAccepted(): void
    {
        $this->update(['status' => QuoteStatus::Accepted]);
    }

    public function markAsRejected(): void
    {
        $this->update(['status' => QuoteStatus::Rejected]);
    }

    public function markAsInvoiced(Invoice $invoice): void
    {
        $this->update([
            'status' => QuoteStatus::Invoiced,
            'invoice_id' => $invoice->id,
        ]);
    }
}
