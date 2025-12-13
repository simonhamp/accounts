<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'filename',
        'path',
        'size_bytes',
        'invoices_count',
        'bills_count',
        'stripe_transactions_count',
        'other_incomes_count',
        'people_count',
        'bank_accounts_count',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'invoices_count' => 'integer',
            'bills_count' => 'integer',
            'stripe_transactions_count' => 'integer',
            'other_incomes_count' => 'integer',
            'people_count' => 'integer',
            'bank_accounts_count' => 'integer',
        ];
    }

    public function getFormattedSizeAttribute(): string
    {
        return Number::fileSize($this->size_bytes);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->path);
    }

    public function delete(): bool
    {
        if ($this->exists()) {
            Storage::disk('local')->delete($this->path);
        }

        return parent::delete();
    }
}
