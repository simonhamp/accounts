<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StripeAccount extends Model
{
    protected $fillable = [
        'person_id',
        'account_name',
        'api_key',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
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
