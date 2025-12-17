<?php

namespace App\Models;

use App\Enums\OtherIncomeStatus;
use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtherIncome extends Model
{
    /** @use HasFactory<\Database\Factories\OtherIncomeFactory> */
    use HasFactory;

    protected $fillable = [
        'person_id',
        'income_source_id',
        'stripe_transaction_id',
        'income_date',
        'description',
        'amount',
        'amount_eur',
        'currency',
        'status',
        'amount_paid',
        'bank_account_id',
        'paid_at',
        'reference',
        'original_file_path',
        'source_filename',
        'extracted_data',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'integer',
            'amount_eur' => 'integer',
            'amount_paid' => 'integer',
            'status' => OtherIncomeStatus::class,
            'paid_at' => 'date',
            'extracted_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OtherIncome $income) {
            // Calculate EUR equivalent
            if ($income->currency && $income->income_date && $income->amount) {
                $income->amount_eur = app(ExchangeRateService::class)
                    ->convertToEur($income->amount, $income->currency, $income->income_date);
            }
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function incomeSource(): BelongsTo
    {
        return $this->belongsTo(IncomeSource::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function stripeTransaction(): BelongsTo
    {
        return $this->belongsTo(StripeTransaction::class);
    }

    public function isFromStripe(): bool
    {
        return $this->stripe_transaction_id !== null;
    }

    public function hasOriginalFile(): bool
    {
        return ! empty($this->original_file_path);
    }

    public function isFromCsv(): bool
    {
        return ! empty($this->source_filename);
    }

    public function markAsPaid(?int $amountPaid = null, ?int $bankAccountId = null, ?string $paidAt = null): void
    {
        $this->update([
            'amount_paid' => $amountPaid ?? $this->amount,
            'bank_account_id' => $bankAccountId,
            'paid_at' => $paidAt ?? now(),
            'status' => OtherIncomeStatus::Paid,
        ]);
    }

    public function getOutstandingAmount(): int
    {
        return max(0, $this->amount - ($this->amount_paid ?? 0));
    }

    public function getOverpaymentAmount(): int
    {
        return max(0, ($this->amount_paid ?? 0) - $this->amount);
    }

    public function isPaid(): bool
    {
        return $this->status === OtherIncomeStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OtherIncomeStatus::Pending;
    }

    public function isPartialPayment(): bool
    {
        return $this->isPaid() && $this->amount_paid < $this->amount;
    }

    public function isOverpayment(): bool
    {
        return $this->isPaid() && $this->amount_paid > $this->amount;
    }

    public function isExactPayment(): bool
    {
        return $this->isPaid() && $this->amount_paid === $this->amount;
    }
}
