<?php

namespace App\Filament\Resources\StripeTransactions\Tables;

use App\Enums\OtherIncomeStatus;
use App\Models\OtherIncome;
use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
                TextColumn::make('processed')
                    ->label('Processed')
                    ->badge()
                    ->getStateUsing(function (StripeTransaction $record): ?string {
                        if ($record->isInvoiced()) {
                            return 'Invoiced';
                        }
                        if ($record->isOtherIncome()) {
                            return 'Other Income';
                        }

                        return null;
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'Invoiced' => 'success',
                        'Other Income' => 'warning',
                        default => 'gray',
                    }),
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
                TernaryFilter::make('processed')
                    ->label('Processed')
                    ->placeholder('All transactions')
                    ->trueLabel('Processed only')
                    ->falseLabel('Not processed only')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereHas('invoiceItem')->orWhereHas('otherIncome')),
                        false: fn (Builder $query) => $query->whereDoesntHave('invoiceItem')->whereDoesntHave('otherIncome'),
                    ),
                SelectFilter::make('type')
                    ->options([
                        'payment' => 'Payment',
                        'refund' => 'Refund',
                        'chargeback' => 'Chargeback',
                        'fee' => 'Fee',
                    ]),
                Filter::make('transaction_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('transaction_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('transaction_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('generate_invoice')
                        ->label('Generate Invoice')
                        ->icon('heroicon-o-document-text')
                        ->requiresConfirmation()
                        ->color('success')
                        ->visible(fn (StripeTransaction $record) => $record->canGenerateInvoice())
                        ->action(function (StripeTransaction $record) {
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
                    Action::make('convert_to_other_income')
                        ->label('Other Income')
                        ->icon('heroicon-o-banknotes')
                        ->requiresConfirmation()
                        ->modalHeading('Convert to Other Income')
                        ->modalDescription('This will create an Other Income record. Use this for payments without formal invoices.')
                        ->color('warning')
                        ->visible(fn (StripeTransaction $record) => $record->canConvertToOtherIncome())
                        ->action(function (StripeTransaction $record) {
                            try {
                                $record->load('stripeAccount.person');
                                $person = $record->stripeAccount->person;

                                OtherIncome::create([
                                    'person_id' => $person->id,
                                    'stripe_transaction_id' => $record->id,
                                    'income_date' => $record->transaction_date,
                                    'description' => $record->description,
                                    'amount' => $record->amount,
                                    'currency' => $record->currency,
                                    'status' => OtherIncomeStatus::Paid,
                                    'amount_paid' => $record->amount,
                                    'paid_at' => $record->transaction_date,
                                    'reference' => $record->stripe_transaction_id,
                                    'notes' => "Converted from Stripe transaction: {$record->stripe_transaction_id}",
                                ]);

                                Notification::make()
                                    ->success()
                                    ->title('Converted to Other Income')
                                    ->body('The transaction has been recorded as Other Income.')
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Failed to Convert')
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
                        ->visible(fn (StripeTransaction $record) => ! $record->isIgnored() && ! $record->isProcessed())
                        ->action(function (StripeTransaction $record) {
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
                        ->visible(fn (StripeTransaction $record) => $record->isIgnored())
                        ->action(function (StripeTransaction $record) {
                            $record->updateCompleteStatus();

                            Notification::make()
                                ->success()
                                ->title('Transaction Restored')
                                ->body('Status updated based on completeness.')
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_invoices')
                        ->label('Generate Invoices')
                        ->icon('heroicon-o-document-text')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Invoices')
                        ->modalDescription(function (Collection $records) {
                            $ineligible = $records->filter(fn ($r) => ! $r->canGenerateInvoice());

                            if ($ineligible->isNotEmpty()) {
                                return 'Some selected transactions are ignored or already processed. Generation is all-or-nothing, so no invoices will be created until the selection only contains transactions that can be invoiced.';
                            }

                            $count = $records->count();

                            return "{$count} invoice(s) will be generated in date order. If any one fails, none will be created.";
                        })
                        ->color('success')
                        ->action(function (Collection $records) {
                            $ineligible = $records->filter(fn ($r) => ! $r->canGenerateInvoice());

                            if ($ineligible->isNotEmpty()) {
                                Notification::make()
                                    ->danger()
                                    ->title('No invoices generated')
                                    ->body('Some selected transactions are ignored or already processed. Remove them from the selection and try again.')
                                    ->send();

                                return;
                            }

                            $sorted = $records->sortBy('transaction_date')->values();
                            $selectedIds = $sorted->pluck('id')->all();

                            $blocked = $sorted->first(fn ($r) => $r->hasPriorUnprocessedTransactions($selectedIds));

                            if ($blocked) {
                                Notification::make()
                                    ->danger()
                                    ->title('No invoices generated')
                                    ->body('There are earlier unprocessed transactions outside this selection. Process or ignore them first, or include them in the selection.')
                                    ->send();

                                return;
                            }

                            $invoiceService = app(InvoiceService::class);

                            try {
                                DB::transaction(function () use ($sorted, $invoiceService) {
                                    foreach ($sorted as $record) {
                                        $invoiceService->generateInvoiceForTransaction($record);
                                    }
                                });
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('No invoices generated')
                                    ->body('Generation failed and was rolled back: '.$e->getMessage())
                                    ->send();

                                return;
                            }

                            $count = $sorted->count();

                            Notification::make()
                                ->success()
                                ->title('Invoices Generated')
                                ->body("{$count} invoice(s) created successfully.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Ignore')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Ignore Transactions')
                        ->modalDescription(function (Collection $records) {
                            $eligibleCount = $records->filter(fn ($r) => ! $r->isIgnored() && ! $r->isProcessed())->count();

                            if ($eligibleCount === 0) {
                                return 'None of the selected transactions can be ignored (already ignored or processed).';
                            }

                            return "{$eligibleCount} transaction(s) will be marked as ignored and excluded from processing.";
                        })
                        ->action(function (Collection $records) {
                            $count = 0;

                            foreach ($records as $record) {
                                if (! $record->isIgnored() && ! $record->isProcessed()) {
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
