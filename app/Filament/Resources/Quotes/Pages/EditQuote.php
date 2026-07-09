<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Services\QuoteService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function afterSave(): void
    {
        $this->record->recalculateTotal();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markSent')
                ->label('Mark as Sent')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark Quote as Sent')
                ->modalDescription('This will generate the PDFs (if needed) and mark the quote as sent.')
                ->visible(fn () => $this->record->canBeSent())
                ->action(function () {
                    try {
                        if (! $this->record->pdf_path) {
                            app(QuoteService::class)->generateAndStorePdf($this->record);
                        }

                        $this->record->markAsSent();

                        $this->refreshFormData(['status', 'pdf_path', 'pdf_path_en', 'generated_at']);

                        Notification::make()
                            ->success()
                            ->title('Quote marked as sent')
                            ->body('The quote is now awaiting a response.')
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to mark quote as sent')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('accept')
                ->label('Accepted')
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Quote as Accepted')
                ->modalDescription('The customer accepted this quote. You will then be able to create an invoice from it.')
                ->visible(fn () => $this->record->canBeAccepted())
                ->action(function () {
                    $this->record->markAsAccepted();

                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->success()
                        ->title('Quote accepted')
                        ->body('You can now create an invoice from this quote.')
                        ->send();
                }),

            Action::make('reject')
                ->label('Rejected')
                ->icon('heroicon-o-hand-thumb-down')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Mark Quote as Rejected')
                ->modalDescription('The customer rejected this quote.')
                ->visible(fn () => $this->record->canBeRejected())
                ->action(function () {
                    $this->record->markAsRejected();

                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->success()
                        ->title('Quote rejected')
                        ->body('The quote has been marked as rejected.')
                        ->send();
                }),

            Action::make('createInvoice')
                ->label('Create Invoice')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Create Invoice from Quote')
                ->modalDescription('This will allocate the next invoice number and copy all line items to a new invoice ready to finalize.')
                ->visible(fn () => $this->record->canCreateInvoice())
                ->action(function () {
                    try {
                        $invoice = app(QuoteService::class)->convertToInvoice($this->record);

                        Notification::make()
                            ->success()
                            ->title('Invoice created')
                            ->body("Invoice {$invoice->invoice_number} has been created from this quote.")
                            ->send();

                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to create invoice')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Action::make('viewInvoice')
                ->label('View Invoice')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn () => $this->record->invoice_id !== null)
                ->url(fn () => InvoiceResource::getUrl('edit', ['record' => $this->record->invoice_id])),

            ActionGroup::make([
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate Quote')
                    ->modalDescription('This will create a new draft quote with the same details and line items.')
                    ->action(function () {
                        try {
                            $copy = app(QuoteService::class)->duplicate($this->record);

                            Notification::make()
                                ->success()
                                ->title('Quote duplicated')
                                ->body("Quote {$copy->quote_number} has been created as a draft.")
                                ->send();

                            $this->redirect(QuoteResource::getUrl('edit', ['record' => $copy]));
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Failed to duplicate quote')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('generatePdf')
                    ->label(fn () => $this->record->pdf_path ? 'Regenerate PDFs' : 'Generate PDFs')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        try {
                            app(QuoteService::class)->generateAndStorePdf($this->record);

                            $this->refreshFormData(['pdf_path', 'pdf_path_en', 'generated_at']);

                            Notification::make()
                                ->success()
                                ->title('PDFs generated')
                                ->body('Both Spanish and English PDFs have been generated.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Failed to generate PDFs')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('downloadPdfEs')
                    ->label('Download PDF (ES)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn () => $this->record->pdf_path !== null)
                    ->url(fn () => route('quotes.download-pdf', ['quote' => $this->record, 'language' => 'es'])),

                Action::make('downloadPdfEn')
                    ->label('Download PDF (EN)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn () => $this->record->pdf_path_en !== null)
                    ->url(fn () => route('quotes.download-pdf', ['quote' => $this->record, 'language' => 'en'])),

                DeleteAction::make(),
            ]),
        ];
    }
}
