<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
                            ->body("Invoice {$this->record->invoice_number} has been finalized.")
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to finalize invoice')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('regeneratePdf')
                ->label('Regenerate PDF')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate PDF')
                ->modalDescription('This will regenerate both Spanish and English PDFs with the current invoice data.')
                ->visible(fn () => $this->record->isFinalized())
                ->action(function () {
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
