<?php

namespace App\Models;

use App\Enums\BillingFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncomeSource extends Model
{
    /** @use HasFactory<\Database\Factories\IncomeSourceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'billing_frequency',
        'billing_month',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'billing_frequency' => BillingFrequency::class,
            'billing_month' => 'integer',
        ];
    }

    public function otherIncomes(): HasMany
    {
        return $this->hasMany(OtherIncome::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithRegularBilling(Builder $query): Builder
    {
        return $query->whereNot('billing_frequency', BillingFrequency::None);
    }

    public function scopeExpectingIncomeInMonth(Builder $query, int $month): Builder
    {
        return $query->active()
            ->withRegularBilling()
            ->where(function (Builder $q) use ($month) {
                $q->where('billing_frequency', BillingFrequency::Monthly)
                    ->orWhere(function (Builder $q) use ($month) {
                        $q->where('billing_frequency', BillingFrequency::Annual)
                            ->where('billing_month', $month);
                    });
            });
    }

    public function hasRegularBilling(): bool
    {
        return $this->billing_frequency->hasRegularBilling();
    }

    public function isExpectingIncomeInMonth(int $month): bool
    {
        if (! $this->is_active || ! $this->hasRegularBilling()) {
            return false;
        }

        if ($this->billing_frequency === BillingFrequency::Monthly) {
            return true;
        }

        return $this->billing_frequency === BillingFrequency::Annual
            && $this->billing_month === $month;
    }

    public function getBillingMonthName(): ?string
    {
        if ($this->billing_month === null) {
            return null;
        }

        return date('F', mktime(0, 0, 0, $this->billing_month, 1));
    }
}
