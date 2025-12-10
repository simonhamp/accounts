<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->counts('invoices')
                    ->sortable(),
                TextColumn::make('invoices_sum_total_amount')
                    ->label('Total Income')
                    ->sum('invoices', 'total_amount')
                    ->money('EUR', divideBy: 100)
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('merge')
                        ->label('Merge Customers')
                        ->icon('heroicon-o-arrows-pointing-in')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Merge Customers')
                        ->modalDescription('Select the customer to keep. All invoices from the other selected customers will be transferred to this customer, and the duplicates will be deleted.')
                        ->form(fn (Collection $records) => [
                            Select::make('target_customer_id')
                                ->label('Customer to Keep')
                                ->options($records->pluck('name', 'id'))
                                ->required()
                                ->helperText('This customer will be kept. All others will be merged into it and deleted.'),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $targetId = $data['target_customer_id'];
                            $targetCustomer = Customer::find($targetId);

                            if (! $targetCustomer) {
                                Notification::make()
                                    ->danger()
                                    ->title('Merge failed')
                                    ->body('Target customer not found.')
                                    ->send();

                                return;
                            }

                            $duplicates = $records->filter(fn ($customer) => $customer->id !== $targetId);
                            $invoicesMoved = 0;

                            foreach ($duplicates as $duplicate) {
                                // Transfer all invoices to the target customer
                                $invoicesMoved += $duplicate->invoices()->update(['customer_id' => $targetId]);

                                // Delete the duplicate
                                $duplicate->delete();
                            }

                            Notification::make()
                                ->success()
                                ->title('Customers merged')
                                ->body("Merged {$duplicates->count()} customer(s) into \"{$targetCustomer->name}\". {$invoicesMoved} invoice(s) transferred.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
