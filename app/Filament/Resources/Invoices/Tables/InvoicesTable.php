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
                    Action::make('regenerate')
                        ->label('Regenerate PDF')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->visible(fn ($record) => $record->isFinalized())
                        ->action(function ($record) {
                            $invoiceService = app(InvoiceService::class);
                            $invoiceService->regeneratePdf($record);

                            Notification::make()
                                ->success()
                                ->title('Invoice regenerated')
                                ->body("Invoice {$record->invoice_number} PDF has been regenerated.")
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
                    BulkAction::make('finalize')
                        ->label('Finalize')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Finalize Invoices')
                        ->modalDescription(function (Collection $records) {
                            $finalizableCount = $records->filter(fn ($record) => $record->canBeFinalized())->count();

                            if ($finalizableCount === 0) {
                                return 'None of the selected invoices can be finalized. Invoices must be in "Reviewed" status and assigned to a person.';
                            }

                            $skippedCount = $records->count() - $finalizableCount;
                            $message = "{$finalizableCount} invoice(s) will be finalized. This will generate invoice numbers and PDFs. This action cannot be undone.";

                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} invoice(s) will be skipped as they cannot be finalized.";
                            }

                            return $message;
                        })
                        ->action(function (Collection $records) {
                            $invoiceService = app(InvoiceService::class);
                            $finalized = 0;
                            $failed = 0;

                            $finalizableRecords = $records->filter(fn ($record) => $record->canBeFinalized());

                            foreach ($finalizableRecords as $record) {
                                try {
                                    $invoiceService->finalizeImportedInvoice($record);
                                    $finalized++;
                                } catch (\Exception $e) {
                                    $failed++;
                                }
                            }

                            if ($finalized > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Invoices finalized')
                                    ->body("{$finalized} invoice(s) have been finalized.")
                                    ->send();
                            }

                            if ($failed > 0) {
                                Notification::make()
                                    ->danger()
                                    ->title('Some invoices failed')
                                    ->body("{$failed} invoice(s) could not be finalized.")
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
