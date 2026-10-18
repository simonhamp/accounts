<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Send, or resend, a finalized invoice to the customer by email, through its
 * brand's mail server. Offered on the invoice edit page and in the invoices
 * table. It is disabled, with the reason as a tooltip, while the invoice
 * can't be emailed.
 */
class EmailInvoiceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'emailInvoice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(fn (Invoice $record): string => $record->sent_at ? 'Resend' : 'Send')
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->visible(fn (Invoice $record): bool => $record->isFinalized())
            ->disabled(fn (Invoice $record): bool => ! $record->canBeEmailed())
            ->tooltip(fn (Invoice $record): ?string => $record->emailBlocker())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-envelope')
            ->modalHeading(fn (Invoice $record): string => ($record->sent_at ? 'Resend' : 'Send')." {$this->documentName($record)} {$record->invoice_number}")
            ->modalDescription(fn (Invoice $record): string => $this->confirmationMessage($record))
            ->modalSubmitActionLabel(fn (Invoice $record): string => $record->sent_at ? 'Resend' : 'Send')
            ->action(function (Invoice $record): void {
                SendInvoiceEmail::dispatch($record);

                Notification::make()
                    ->success()
                    ->title('Invoice email queued')
                    ->body("{$record->invoice_number} is on its way to {$record->customer_email}.")
                    ->send();
            });
    }

    protected function confirmationMessage(Invoice $record): string
    {
        $brand = $record->brand;
        $mail = new InvoiceMail($record);
        $from = $mail->envelope()->from;

        $message = "This emails the {$this->documentName($record)} to {$record->customer_email}"
            ." as {$brand?->name}, from {$from->name} <{$from->address}>, through {$brand?->smtp_host},"
            .' with the PDF in '.($mail->language() === 'es' ? 'Spanish' : 'English').'.';

        if ($brand?->invoice_bcc) {
            $message .= " A copy goes to {$brand->invoice_bcc}.";
        }

        if ($record->sent_at) {
            $message .= " It was last sent to {$record->sent_to} on {$record->sent_at->format('j M Y, H:i')}.";
        }

        return $message;
    }

    protected function documentName(Invoice $record): string
    {
        return $record->isCreditNote() ? 'credit note' : 'invoice';
    }
}
