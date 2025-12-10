<?php

namespace App\Models;

use App\Enums\BillStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    /** @use HasFactory<\Database\Factories\BillFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'bill_number',
        'bill_date',
        'due_date',
        'total_amount',
        'currency',
        'status',
        'original_file_path',
        'extracted_data',
        'error_message',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'integer',
            'status' => BillStatus::class,
            'extracted_data' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BillStatus::Pending,
            BillStatus::Extracted,
            BillStatus::Reviewed,
            BillStatus::PaidNeedsReview,
        ]);
    }

    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BillStatus::Extracted,
            BillStatus::PaidNeedsReview,
        ]);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', BillStatus::Paid);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', BillStatus::Failed);
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isPaid(): bool
    {
        return $this->status === BillStatus::Paid;
    }

    public function needsReview(): bool
    {
        return $this->status->needsReview();
    }

    public function canBePaid(): bool
    {
        return $this->status->canBePaid();
    }

    public function markAsExtracted(): void
    {
        $this->update(['status' => BillStatus::Extracted]);
    }

    public function markAsPaidNeedsReview(): void
    {
        $this->update(['status' => BillStatus::PaidNeedsReview]);
    }

    public function markAsReviewed(): void
    {
        if ($this->status === BillStatus::PaidNeedsReview) {
            $this->update(['status' => BillStatus::Paid]);
        } else {
            $this->update(['status' => BillStatus::Reviewed]);
        }
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => BillStatus::Paid]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => BillStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
