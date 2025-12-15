<?php

namespace App\Filament\Resources\StripeTransactions\Pages;

use App\Enums\OtherIncomeStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\OtherIncomes\OtherIncomeResource;
use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Models\OtherIncome;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditStripeTransaction extends EditRecord
{
    protected static string $resource = StripeTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_invoice')
                ->label('Generate Invoice')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canGenerateInvoice())
                ->action(function () {
                    $invoiceService = app(InvoiceService::class);

                    try {
                        $invoice = $invoiceService->generateInvoiceForTransaction($this->record);

                        Notification::make()
                            ->success()
                            ->title('Invoice Generated')
                            ->body("Invoice {$invoice->invoice_number} has been created successfully.")
                            ->send();

                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to Generate Invoice')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('convert_to_other_income')
                ->label('Convert to Other Income')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Convert to Other Income')
                ->modalDescription('This will create an Other Income record linked to this Stripe transaction. Use this for payments that don\'t require a formal invoice (e.g., Apple Pay roll-ups, Substack earnings).')
                ->visible(fn () => $this->record->canConvertToOtherIncome())
                ->action(function () {
                    try {
                        $this->record->load('stripeAccount.person');
                        $person = $this->record->stripeAccount->person;

                        $otherIncome = OtherIncome::create([
                            'person_id' => $person->id,
                            'stripe_transaction_id' => $this->record->id,
                            'income_date' => $this->record->transaction_date,
                            'description' => $this->record->description,
                            'amount' => $this->record->amount,
                            'currency' => $this->record->currency,
                            'status' => OtherIncomeStatus::Paid,
                            'amount_paid' => $this->record->amount,
                            'paid_at' => $this->record->transaction_date,
                            'reference' => $this->record->stripe_transaction_id,
                            'notes' => "Converted from Stripe transaction: {$this->record->stripe_transaction_id}",
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Converted to Other Income')
                            ->body('The transaction has been recorded as Other Income.')
                            ->send();

                        $this->redirect(OtherIncomeResource::getUrl('edit', ['record' => $otherIncome]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Failed to Convert')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),
            Action::make('view_invoice')
                ->label('View Invoice')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->visible(fn () => $this->record->isInvoiced())
                ->url(fn () => InvoiceResource::getUrl('edit', [
                    'record' => $this->record->invoiceItem->invoice,
                ])),
            Action::make('view_other_income')
                ->label('View Other Income')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn () => $this->record->isOtherIncome())
                ->url(fn () => OtherIncomeResource::getUrl('edit', [
                    'record' => $this->record->otherIncome,
                ])),
            Action::make('view_in_stripe')
                ->label('View in Stripe')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->getStripeUrl())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        if (! $this->record->isProcessed()) {
            $this->record->updateCompleteStatus();
        }
    }

    protected function getStripeUrl(): string
    {
        $transaction = $this->record;
        $baseUrl = 'https://dashboard.stripe.com';

        return match ($transaction->type) {
            'payment' => "{$baseUrl}/payments/{$transaction->stripe_transaction_id}",
            'refund' => "{$baseUrl}/refunds/{$transaction->stripe_transaction_id}",
            'chargeback' => "{$baseUrl}/disputes/{$transaction->stripe_transaction_id}",
            default => $baseUrl,
        };
    }
}
