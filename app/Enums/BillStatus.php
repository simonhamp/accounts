<?php

namespace App\Enums;

enum BillStatus: string
{
    case Pending = 'pending';
    case Extracted = 'extracted';
    case Reviewed = 'reviewed';
    case Paid = 'paid';
    case PaidNeedsReview = 'paid_needs_review';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Extraction',
            self::Extracted => 'Awaiting Review',
            self::Reviewed => 'Ready to Pay',
            self::Paid => 'Paid',
            self::PaidNeedsReview => 'Paid - Needs Review',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Extracted => 'warning',
            self::PaidNeedsReview => 'warning',
            self::Reviewed => 'info',
            self::Paid => 'success',
            self::Failed => 'danger',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Pending, self::Extracted, self::Reviewed, self::PaidNeedsReview]);
    }

    public function needsReview(): bool
    {
        return in_array($this, [self::Extracted, self::PaidNeedsReview]);
    }

    public function canBePaid(): bool
    {
        return $this === self::Reviewed;
    }
}
