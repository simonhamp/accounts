<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Pending = 'pending';
    case Extracted = 'extracted';
    case Reviewed = 'reviewed';
    case ReadyToSend = 'finalized'; // Keep 'finalized' value for backwards compatibility
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Extraction',
            self::Extracted => 'Awaiting Review',
            self::Reviewed => 'Ready to Finalize',
            self::ReadyToSend => 'Ready to Send',
            self::Sent => 'Awaiting Payment',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Extracted => 'warning',
            self::Reviewed => 'info',
            self::ReadyToSend => 'success',
            self::Sent => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
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

    public function canBeSent(): bool
    {
        return $this === self::ReadyToSend;
    }

    public function canRecordPayment(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid]);
    }

    public function canWriteOff(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid]);
    }
}
