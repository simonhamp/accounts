<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StripeAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'account_name',
        'api_key',
        'email_invoices',
        'invoice_sender_name',
        'invoice_from_address',
        'invoice_reply_to',
        'invoice_bcc',
        'invoice_language',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'email_invoices' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StripeTransaction::class);
    }
}
