<?php

namespace App\Exceptions;

use DomainException;

class InvoiceOrderingException extends DomainException
{
    public static function dateBeforePrevious(string $invoiceDate, string $previousInvoiceNumber, string $previousDate): self
    {
        return new self(
            "Invoice date ({$invoiceDate}) cannot be earlier than the previous invoice {$previousInvoiceNumber} dated {$previousDate}. "
            .'Spanish invoicing rules require numerical and date order.'
        );
    }

    public static function dateAfterNext(string $invoiceDate, string $nextInvoiceNumber, string $nextDate): self
    {
        return new self(
            "Invoice date ({$invoiceDate}) cannot be later than the subsequent invoice {$nextInvoiceNumber} dated {$nextDate}."
        );
    }

    public static function cannotDeleteFinalized(string $invoiceNumber): self
    {
        return new self(
            "Invoice {$invoiceNumber} cannot be deleted because it has been finalized. "
            .'Issue a credit note instead.'
        );
    }

    public static function simplifiedAboveThreshold(string $invoiceNumber, int $amountEurCents, int $thresholdCents): self
    {
        $amount = number_format($amountEurCents / 100, 2);
        $threshold = number_format($thresholdCents / 100, 2);

        return new self(
            "Invoice {$invoiceNumber} cannot be marked as simplified: total of €{$amount} exceeds the €{$threshold} simplified-invoice threshold."
        );
    }

    public static function fullInvoiceMissingCustomerDetails(string $invoiceNumber, array $missing): self
    {
        $missingList = implode(', ', $missing);

        return new self(
            "Invoice {$invoiceNumber} requires full customer details ({$missingList}) before it can be finalized. "
            .'Spanish invoicing rules require these for any invoice over €400 unless issued as a simplified invoice.'
        );
    }

    public static function cannotDeleteWithGap(string $invoiceNumber, string $latestNumber): self
    {
        return new self(
            "Invoice {$invoiceNumber} cannot be deleted because it would leave a gap. "
            ."Only the most recent invoice ({$latestNumber}) can be deleted to preserve sequential numbering."
        );
    }
}
