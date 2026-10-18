<?php

namespace App\Filament\Resources\Quotes\Tables;

use App\Enums\QuoteStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Services\QuoteService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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

class QuotesTable
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
                    ->searchable(),
                TextColumn::make('quote_number')
                    ->searchable(),
                TextColumn::make('quote_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->placeholder('No expiry')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Quote Customer Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->placeholder('—')
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
                    ->options(collect(QuoteStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('quote_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('quote_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('quote_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('markSent')
                        ->label('Mark as Sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Quote as Sent')
                        ->modalDescription('This will generate the PDFs (if needed) and mark the quote as sent.')
                        ->visible(fn ($record) => $record->canBeSent())
                        ->action(function ($record) {
                            try {
                                if (! $record->pdf_path) {
                                    app(QuoteService::class)->generateAndStorePdf($record);
                                }

                                $record->markAsSent();

                                Notification::make()
                                    ->success()
                                    ->title('Quote marked as sent')
                                    ->body("Quote {$record->quote_number} is now awaiting a response.")
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
                        ->visible(fn ($record) => $record->canBeAccepted())
                        ->action(function ($record) {
                            $record->markAsAccepted();

                            Notification::make()
                                ->success()
                                ->title('Quote accepted')
                                ->body("Quote {$record->quote_number} has been marked as accepted.")
                                ->send();
                        }),
                    Action::make('reject')
                        ->label('Rejected')
                        ->icon('heroicon-o-hand-thumb-down')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Quote as Rejected')
                        ->modalDescription('The customer rejected this quote.')
                        ->visible(fn ($record) => $record->canBeRejected())
                        ->action(function ($record) {
                            $record->markAsRejected();

                            Notification::make()
                                ->success()
                                ->title('Quote rejected')
                                ->body("Quote {$record->quote_number} has been marked as rejected.")
                                ->send();
                        }),
                    Action::make('createInvoice')
                        ->label('Create Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Create Invoice from Quote')
                        ->modalDescription('This will allocate the next invoice number and copy all line items to a new invoice ready to finalize.')
                        ->visible(fn ($record) => $record->canCreateInvoice())
                        ->action(function ($record) {
                            try {
                                $invoice = app(QuoteService::class)->convertToInvoice($record);

                                Notification::make()
                                    ->success()
                                    ->title('Invoice created')
                                    ->body("Invoice {$invoice->invoice_number} has been created from quote {$record->quote_number}.")
                                    ->send();

                                return redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
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
                        ->visible(fn ($record) => $record->invoice_id !== null)
                        ->url(fn ($record) => InvoiceResource::getUrl('edit', ['record' => $record->invoice_id])),
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Duplicate Quote')
                        ->modalDescription('This will create a new draft quote with the same details and line items.')
                        ->action(function ($record) {
                            try {
                                $copy = app(QuoteService::class)->duplicate($record);

                                Notification::make()
                                    ->success()
                                    ->title('Quote duplicated')
                                    ->body("Quote {$copy->quote_number} has been created as a draft.")
                                    ->send();

                                return redirect(QuoteResource::getUrl('edit', ['record' => $copy]));
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Failed to duplicate quote')
                                    ->body($e->getMessage())
                                    ->send();
                            }
                        }),
                    Action::make('generatePdf')
                        ->label(fn ($record) => $record->pdf_path ? 'Regenerate PDFs' : 'Generate PDFs')
                        ->icon('heroicon-o-arrow-path')
                        ->color('gray')
                        ->action(function ($record) {
                            try {
                                app(QuoteService::class)->generateAndStorePdf($record);

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
                    Action::make('previewPdf')
                        ->label('Preview PDF')
                        ->icon('heroicon-o-document')
                        ->color('gray')
                        ->visible(fn ($record) => $record->pdf_path_en !== null)
                        ->modalHeading(fn ($record) => "Quote {$record->quote_number}")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalWidth('7xl')
                        ->modalContent(fn ($record) => view('filament.actions.pdf-preview', [
                            'url' => route('quotes.show-pdf', ['quote' => $record, 'language' => 'en']),
                            'isImage' => false,
                        ])),
                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn ($record) => $record->pdf_path !== null)
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
                            return redirect()->to(
                                route('quotes.download-pdf', ['quote' => $record, 'language' => $data['language']])
                            );
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
