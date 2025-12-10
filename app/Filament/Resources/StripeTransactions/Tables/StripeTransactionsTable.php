<?php

namespace App\Filament\Resources\StripeTransactions\Tables;

use App\Models\StripeAccount;
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
                TextColumn::make('stripeAccount.account_name')
                    ->label('Account')
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
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_review' => 'warning',
                        'ready' => 'success',
                        'ignored' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_review' => 'Pending Review',
                        'ready' => 'Ready',
                        'ignored' => 'Ignored',
                        default => ucfirst($state),
                    }),
                IconColumn::make('invoiced')
                    ->label('Invoiced')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isInvoiced()),
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
                SelectFilter::make('stripe_account_id')
                    ->label('Account')
                    ->options(fn () => StripeAccount::pluck('account_name', 'id')->toArray()),
                SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'ready' => 'Ready',
                        'ignored' => 'Ignored',
                    ]),
                TernaryFilter::make('invoiced')
                    ->label('Invoiced')
                    ->placeholder('All transactions')
                    ->trueLabel('Invoiced only')
                    ->falseLabel('Not invoiced only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('invoiceItem'),
                        false: fn (Builder $query) => $query->whereDoesntHave('invoiceItem'),
                    ),
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
                    ->visible(fn ($record) => $record->canGenerateInvoice())
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
                Action::make('ignore')
                    ->label('Ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Ignore Transaction')
                    ->modalDescription('This transaction will be excluded from invoice generation.')
                    ->visible(fn ($record) => ! $record->isIgnored() && ! $record->isInvoiced())
                    ->action(function ($record) {
                        $record->markAsIgnored();

                        Notification::make()
                            ->success()
                            ->title('Transaction Ignored')
                            ->send();
                    }),
                Action::make('unignore')
                    ->label('Unignore')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->visible(fn ($record) => $record->isIgnored())
                    ->action(function ($record) {
                        $record->updateCompleteStatus();

                        Notification::make()
                            ->success()
                            ->title('Transaction Restored')
                            ->body('Status updated based on completeness.')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-text')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Invoices')
                        ->modalDescription(function (Collection $records) {
                            $eligibleCount = $records->filter(fn ($r) => $r->canGenerateInvoice())->count();

                            if ($eligibleCount === 0) {
                                return 'None of the selected transactions can be invoiced. Transactions must be "Ready" and not already invoiced.';
                            }

                            $skippedCount = $records->count() - $eligibleCount;
                            $message = "{$eligibleCount} transaction(s) will be invoiced.";

                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} transaction(s) will be skipped (not ready, ignored, or already invoiced).";
                            }

                            return $message;
                        })
                        ->color('success')
                        ->action(function (Collection $records) {
                            $invoiceService = app(InvoiceService::class);
                            $success = 0;
                            $failed = 0;
                            $errors = [];

                            $eligibleRecords = $records->filter(fn ($r) => $r->canGenerateInvoice());

                            foreach ($eligibleRecords as $record) {
                                try {
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
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Ignore')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Ignore Transactions')
                        ->modalDescription(function (Collection $records) {
                            $eligibleCount = $records->filter(fn ($r) => ! $r->isIgnored() && ! $r->isInvoiced())->count();

                            if ($eligibleCount === 0) {
                                return 'None of the selected transactions can be ignored (already ignored or invoiced).';
                            }

                            return "{$eligibleCount} transaction(s) will be marked as ignored and excluded from invoice generation.";
                        })
                        ->action(function (Collection $records) {
                            $count = 0;

                            foreach ($records as $record) {
                                if (! $record->isIgnored() && ! $record->isInvoiced()) {
                                    $record->markAsIgnored();
                                    $count++;
                                }
                            }

                            if ($count > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Transactions Ignored')
                                    ->body("{$count} transaction(s) marked as ignored.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }
}
