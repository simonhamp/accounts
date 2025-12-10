<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Services\InvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                TextColumn::make('customer_name')
                    ->searchable(),
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
