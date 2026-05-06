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

    /**
     * Atomically allocate the next invoice number, locking the Person row to
     * prevent concurrent saves from picking the same number.
     *
     * Must be called inside a database transaction.
     */
    public function allocateNextInvoiceNumber(): string
    {
        $locked = static::query()->lockForUpdate()->findOrFail($this->id);
        $number = str_pad((string) $locked->next_invoice_number, 5, '0', STR_PAD_LEFT);
        $allocated = "{$locked->invoice_prefix}-{$number}";

        $locked->increment('next_invoice_number');
        $this->next_invoice_number = $locked->next_invoice_number;

        return $allocated;
    }
}
