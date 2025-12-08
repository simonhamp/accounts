<?php

namespace App\Filament\Resources\StripeTransactions\Tables;

use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StripeTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('stripeAccount.id')
                    ->searchable(),
                TextColumn::make('stripe_transaction_id')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                IconColumn::make('is_complete')
                    ->boolean(),
                TextColumn::make('transaction_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_complete')
                    ->label('Complete')
                    ->placeholder('All transactions')
                    ->trueLabel('Complete only')
                    ->falseLabel('Incomplete only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_complete', true),
                        false: fn (Builder $query) => $query->where('is_complete', false),
                    ),
                SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'ready' => 'Ready',
                        'invoiced' => 'Invoiced',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'payment' => 'Payment',
                        'refund' => 'Refund',
                        'chargeback' => 'Chargeback',
                        'fee' => 'Fee',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('generate_invoice')
                    ->label('Generate Invoice')
                    ->icon('heroicon-o-document-text')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function ($record) {
                        $invoiceService = app(InvoiceService::class);

                        try {
                            $invoice = $invoiceService->generateInvoiceForTransaction($record);

                            Notification::make()
                                ->success()
                                ->title('Invoice Generated')
                                ->body("Invoice {$invoice->invoice_number} has been created successfully.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Failed to Generate Invoice')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-text')
                        ->requiresConfirmation()
                        ->color('success')
                        ->action(function (Collection $records) {
                            $invoiceService = app(InvoiceService::class);
                            $success = 0;
                            $failed = 0;
                            $errors = [];

                            foreach ($records as $record) {
                                try {
                                    if (! $record->is_complete) {
                                        $failed++;
                                        $errors[] = "Transaction {$record->stripe_transaction_id} is incomplete";

                                        continue;
                                    }

                                    $invoiceService->generateInvoiceForTransaction($record);
                                    $success++;
                                } catch (\Exception $e) {
                                    $failed++;
                                    $errors[] = $e->getMessage();
                                }
                            }

                            if ($success > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Invoices Generated')
                                    ->body("{$success} invoice(s) created successfully.")
                                    ->send();
                            }

                            if ($failed > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Some Invoices Failed')
                                    ->body("{$failed} transaction(s) could not be invoiced: ".implode(', ', array_slice($errors, 0, 3)))
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
