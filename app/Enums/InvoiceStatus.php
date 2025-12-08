<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Extracted = 'extracted';
    case Reviewed = 'reviewed';
    case Finalized = 'finalized';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Extraction',
            self::Extracted => 'Awaiting Review',
            self::Reviewed => 'Ready to Finalize',
            self::Finalized => 'Finalized',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Extracted => 'warning',
            self::Reviewed => 'info',
            self::Finalized => 'success',
            self::Failed => 'danger',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Pending, self::Extracted, self::Reviewed]);
    }

    public function canBeFinalized(): bool
    {
        return $this === self::Reviewed;
    }
}
