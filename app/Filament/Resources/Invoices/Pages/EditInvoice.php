<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function afterSave(): void
    {
        // Recalculate total from line items
        $this->record->recalculateTotal();
    }

    public function regeneratePdf(): void
    {
        try {
            $invoiceService = app(InvoiceService::class);
            $invoiceService->regeneratePdf($this->record);

            $this->refreshFormData(['pdf_path', 'pdf_path_en', 'generated_at']);

            Notification::make()
                ->success()
                ->title('PDFs regenerated')
                ->body('Both Spanish and English PDFs have been regenerated.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Failed to regenerate PDFs')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label('Mark as Reviewed')
                ->icon('heroicon-o-check')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark Invoice as Reviewed')
                ->modalDescription('This will save your changes and mark the invoice as ready to be finalized.')
                ->visible(fn () => $this->record->status === InvoiceStatus::Extracted)
                ->action(function () {
                    $this->save();

                    $this->record->refresh();

                    if (! $this->record->person_id) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot mark as reviewed')
                            ->body('Please assign this invoice to a person first.')
                            ->send();

                        return;
                    }

                    $this->record->markAsReviewed();

                    Notification::make()
                        ->success()
                        ->title('Invoice marked as reviewed')
                        ->body('The invoice is now ready to be finalized.')
                        ->send();
                }),

            Action::make('finalize')
                ->label('Finalize')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Finalize Invoice')
                ->modalDescription('This will generate the invoice number and PDF. This action cannot be undone.')
                ->visible(fn () => $this->record->canBeFinalized())
                ->action(function () {
                    try {
                        $invoiceService = app(InvoiceService::class);
                        $invoiceService->finalizeImportedInvoice($this->record);

                        $this->refreshFormData(['status', 'invoice_number', 'pdf_path', 'pdf_path_en', 'generated_at']);

                        Notification::make()
                            ->success()
                            ->title('Invoice finalized')
                            ->body("Invoice {$this->record->invoice_number} is now ready to send.")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to finalize invoice')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('markSent')
                ->label('Mark as Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark Invoice as Sent')
                ->modalDescription('This will mark the invoice as sent and awaiting payment.')
                ->visible(fn () => $this->record->canBeSent())
                ->action(function () {
                    $this->record->markAsSent();

                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->success()
                        ->title('Invoice marked as sent')
                        ->body('The invoice is now awaiting payment.')
                        ->send();
                }),

            Action::make('recordPayment')
                ->label('Paid')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->canRecordPayment())
                ->form([
                    Radio::make('payment_type')
                        ->label('Payment received')
                        ->options([
                            'full' => 'Paid in full',
                            'partial' => 'Partially paid',
                        ])
                        ->default('full')
                        ->required(),
                ])
                ->action(function (array $data) {
                    if ($data['payment_type'] === 'full') {
                        $this->record->markAsPaid();

                        // Regenerate PDFs to show paid status
                        try {
                            $invoiceService = app(InvoiceService::class);
                            $invoiceService->regeneratePdf($this->record);
                        } catch (\Exception $e) {
                            // Log but don't fail - the payment status is more important
                            report($e);
                        }

                        Notification::make()
                            ->success()
                            ->title('Invoice marked as paid')
                            ->body('The invoice has been marked as fully paid and PDFs have been regenerated.')
                            ->send();
                    } else {
                        $this->record->markAsPartiallyPaid();

                        Notification::make()
                            ->success()
                            ->title('Partial payment recorded')
                            ->body('The invoice has been marked as partially paid.')
                            ->send();
                    }

                    $this->refreshFormData(['status', 'pdf_path', 'pdf_path_en', 'generated_at']);
                }),

            Action::make('writeOff')
                ->label('Write-off')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canWriteOff())
                ->form([
                    TextInput::make('write_off_amount')
                        ->label('Amount to write off (cents)')
                        ->helperText('Enter the amount in cents that will not be collected')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                ])
                ->requiresConfirmation()
                ->modalHeading('Write Off Unpaid Amount')
                ->modalDescription('This will close the invoice and record the write-off amount.')
                ->action(function (array $data) {
                    $this->record->writeOff((int) $data['write_off_amount']);

                    $this->refreshFormData(['status', 'write_off_amount']);

                    Notification::make()
                        ->success()
                        ->title('Amount written off')
                        ->body('The invoice has been closed with the write-off recorded.')
                        ->send();
                }),

            Action::make('downloadPdfEs')
                ->label('Download PDF (ES)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->record->isFinalized() && $this->record->pdf_path)
                ->url(fn () => route('invoices.download-pdf', ['invoice' => $this->record, 'language' => 'es'])),

            Action::make('downloadPdfEn')
                ->label('Download PDF (EN)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => $this->record->isFinalized() && $this->record->pdf_path_en)
                ->url(fn () => route('invoices.download-pdf', ['invoice' => $this->record, 'language' => 'en'])),

            Action::make('viewTransaction')
                ->label('View Transaction')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->visible(fn () => $this->record->hasStripeTransactions())
                ->url(fn () => StripeTransactionResource::getUrl('edit', [
                    'record' => $this->record->items()->whereNotNull('stripe_transaction_id')->first()?->stripe_transaction_id,
                ])),

            DeleteAction::make(),
        ];
    }
}
