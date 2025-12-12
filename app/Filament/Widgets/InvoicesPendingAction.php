<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class InvoicesPendingAction extends TableWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Invoices Pending Action';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->whereIn('status', [InvoiceStatus::ReadyToSend, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
                    ->with(['customer', 'person'])
                    ->orderBy('invoice_date')
            )
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InvoiceStatus $state) => $state->color())
                    ->formatStateUsing(fn (InvoiceStatus $state) => $state->label()),
                TextColumn::make('person.name')
                    ->label('Person')
                    ->placeholder('Unassigned')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->label('Invoice #')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->label('Invoice Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Invoice $record) => InvoiceResource::getUrl('edit', ['record' => $record])),
                Action::make('markSent')
                    ->label('Mark Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Invoice as Sent')
                    ->modalDescription('This will mark the invoice as sent and awaiting payment.')
                    ->visible(fn (Invoice $record) => $record->canBeSent())
                    ->action(function (Invoice $record) {
                        $record->markAsSent();

                        Notification::make()
                            ->success()
                            ->title('Invoice marked as sent')
                            ->body("Invoice {$record->invoice_number} is now awaiting payment.")
                            ->send();
                    }),
                Action::make('recordPayment')
                    ->label('Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record) => $record->canRecordPayment())
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
                    ->action(function (Invoice $record, array $data) {
                        if ($data['payment_type'] === 'full') {
                            $record->markAsPaid();

                            // Regenerate PDFs with paid stamp
                            $invoiceService = app(InvoiceService::class);
                            $invoiceService->generatePdf($record);
                            $invoiceService->generatePdf($record, 'en');

                            $status = 'paid';
                        } else {
                            $record->markAsPartiallyPaid();
                            $status = 'partially paid';
                        }

                        Notification::make()
                            ->success()
                            ->title('Payment recorded')
                            ->body("Invoice {$record->invoice_number} marked as {$status}.")
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No invoices pending action')
            ->emptyStateDescription('All invoices have been sent and paid.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
