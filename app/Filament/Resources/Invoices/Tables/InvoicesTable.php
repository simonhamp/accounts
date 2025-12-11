<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => $state?->color() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? 'Unknown')
                    ->sortable(),
                TextColumn::make('person.name')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->placeholder('Pending')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_month')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('period_year')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Invoice Customer Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_tax_id')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pdf_path')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('generated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('status')
                    ->options(collect(InvoiceStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('invoice_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('invoice_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('invoice_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('viewTransaction')
                        ->label('View Transaction')
                        ->icon('heroicon-o-banknotes')
                        ->visible(fn ($record) => $record->hasStripeTransactions())
                        ->url(fn ($record) => StripeTransactionResource::getUrl('edit', [
                            'record' => $record->items()->whereNotNull('stripe_transaction_id')->first()?->stripe_transaction_id,
                        ])),
                    Action::make('finalize')
                        ->label('Finalize')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Finalize Invoice')
                        ->modalDescription('This will generate the invoice number and PDF. This action cannot be undone.')
                        ->visible(fn ($record) => $record->canBeFinalized())
                        ->action(function ($record) {
                            try {
                                $invoiceService = app(InvoiceService::class);
                                $invoiceService->finalizeImportedInvoice($record);

                                Notification::make()
                                    ->success()
                                    ->title('Invoice finalized')
                                    ->body("Invoice {$record->invoice_number} has been finalized.")
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
                        ->visible(fn ($record) => $record->canBeSent())
                        ->action(function ($record) {
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
                        ->visible(fn ($record) => $record->canRecordPayment())
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
                        ->action(function ($record, array $data) {
                            if ($data['payment_type'] === 'full') {
                                $record->markAsPaid();
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
                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn ($record) => $record->isFinalized() && $record->pdf_path)
                        ->form([
                            Radio::make('language')
                                ->label('Language')
                                ->options([
                                    'es' => 'Spanish (Español)',
                                    'en' => 'English',
                                ])
                                ->default('es')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $language = $data['language'];

                            return redirect()->to(
                                route('invoices.download-pdf', ['invoice' => $record, 'language' => $language])
                            );
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Invoices as Sent')
                        ->modalDescription(function (Collection $records) {
                            $sendableCount = $records->filter(fn ($record) => $record->canBeSent())->count();

                            if ($sendableCount === 0) {
                                return 'None of the selected invoices can be marked as sent. Invoices must be in "Ready to Send" status.';
                            }

                            $skippedCount = $records->count() - $sendableCount;
                            $message = "{$sendableCount} invoice(s) will be marked as sent.";

                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} invoice(s) will be skipped as they are not ready to send.";
                            }

                            return $message;
                        })
                        ->action(function (Collection $records) {
                            $sent = 0;

                            $sendableRecords = $records->filter(fn ($record) => $record->canBeSent());

                            foreach ($sendableRecords as $record) {
                                $record->markAsSent();
                                $sent++;
                            }

                            if ($sent > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Invoices marked as sent')
                                    ->body("{$sent} invoice(s) have been marked as sent.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('recordPayment')
                        ->label('Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->modalHeading('Record Payment')
                        ->modalDescription(function (Collection $records) {
                            $payableCount = $records->filter(fn ($record) => $record->canRecordPayment())->count();

                            if ($payableCount === 0) {
                                return 'None of the selected invoices can record payment. Invoices must be in "Sent" or "Partially Paid" status.';
                            }

                            $skippedCount = $records->count() - $payableCount;
                            $message = "{$payableCount} invoice(s) will be updated.";

                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} invoice(s) will be skipped.";
                            }

                            return $message;
                        })
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
                        ->action(function (Collection $records, array $data) {
                            $updated = 0;

                            $payableRecords = $records->filter(fn ($record) => $record->canRecordPayment());

                            foreach ($payableRecords as $record) {
                                if ($data['payment_type'] === 'full') {
                                    $record->markAsPaid();
                                } else {
                                    $record->markAsPartiallyPaid();
                                }
                                $updated++;
                            }

                            if ($updated > 0) {
                                $status = $data['payment_type'] === 'full' ? 'paid' : 'partially paid';
                                Notification::make()
                                    ->success()
                                    ->title('Payment recorded')
                                    ->body("{$updated} invoice(s) marked as {$status}.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('download_pdfs')
                        ->label('Download PDFs')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->form([
                            Radio::make('language')
                                ->label('Language')
                                ->options([
                                    'es' => 'Spanish (Español)',
                                    'en' => 'English',
                                ])
                                ->default('es')
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): StreamedResponse {
                            $language = $data['language'];
                            $invoicesWithPdfs = $records->filter(function ($record) use ($language) {
                                $path = $language === 'en' ? $record->pdf_path_en : $record->pdf_path;

                                return $record->isFinalized() && $path && Storage::exists($path);
                            });

                            if ($invoicesWithPdfs->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No PDFs available')
                                    ->body('None of the selected invoices have generated PDFs.')
                                    ->send();

                                return response()->streamDownload(function () {}, 'empty.txt');
                            }

                            $zipFileName = 'invoices-'.($language === 'en' ? 'english' : 'spanish').'-'.now()->format('Y-m-d-His').'.zip';
                            $tempPath = storage_path('app/temp/'.$zipFileName);

                            if (! is_dir(storage_path('app/temp'))) {
                                mkdir(storage_path('app/temp'), 0755, true);
                            }

                            $zip = new ZipArchive;
                            $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

                            foreach ($invoicesWithPdfs as $invoice) {
                                $pdfPath = $language === 'en' ? $invoice->pdf_path_en : $invoice->pdf_path;
                                $fileName = $invoice->invoice_number.($language === 'en' ? '-en' : '').'.pdf';
                                $zip->addFromString($fileName, Storage::get($pdfPath));
                            }

                            $zip->close();

                            return response()->streamDownload(function () use ($tempPath) {
                                readfile($tempPath);
                                unlink($tempPath);
                            }, $zipFileName, [
                                'Content-Type' => 'application/zip',
                            ]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
