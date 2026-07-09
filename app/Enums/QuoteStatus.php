<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Invoiced => 'Invoiced',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'warning',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Invoiced => 'info',
        };
    }

    public function canBeSent(): bool
    {
        return $this === self::Draft;
    }

    public function canBeAccepted(): bool
    {
        return $this === self::Sent;
    }

    public function canBeRejected(): bool
    {
        return in_array($this, [self::Sent, self::Accepted]);
    }

    public function canCreateInvoice(): bool
    {
        return $this === self::Accepted;
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent]);
    }
}
