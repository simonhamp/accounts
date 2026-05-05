<?php

namespace App\Models;

use App\Enums\EntityType;
use App\Enums\TaxRegime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'postal_code',
        'country',
        'entity_type',
        'dni_nie',
        'cif',
        'registro_mercantil',
        'share_capital',
        'tax_regime',
        'invoice_prefix',
        'next_invoice_number',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'next_invoice_number' => 'integer',
            'entity_type' => EntityType::class,
            'tax_regime' => TaxRegime::class,
            'share_capital' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first();
    }

    public function isLegalEntity(): bool
    {
        return $this->entity_type?->isLegalEntity() ?? false;
    }

    public function taxIdentifier(): ?string
    {
        return $this->isLegalEntity() ? $this->cif : $this->dni_nie;
    }

    public function taxIdentifierLabel(): string
    {
        return $this->isLegalEntity() ? 'CIF' : 'DNI/NIE';
    }

    public function stripeAccounts(): HasMany
    {
        return $this->hasMany(StripeAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function otherIncomes(): HasMany
    {
        return $this->hasMany(OtherIncome::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function getNextInvoiceNumber(): string
    {
        $invoiceNumber = str_pad((string) $this->next_invoice_number, 5, '0', STR_PAD_LEFT);

        return "{$this->invoice_prefix}-{$invoiceNumber}";
    }

    public function incrementInvoiceNumber(): void
    {
        $this->increment('next_invoice_number');
    }
}
