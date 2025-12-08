<?php

namespace App\Models;

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
        'dni_nie',
        'invoice_prefix',
        'next_invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'next_invoice_number' => 'integer',
        ];
    }

    public function stripeAccounts(): HasMany
    {
        return $this->hasMany(StripeAccount::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
