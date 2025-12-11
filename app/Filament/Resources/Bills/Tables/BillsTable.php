<?php

namespace App\Filament\Resources\Bills\Tables;

use App\Enums\BillStatus;
use App\Models\Person;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BillsTable
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
                TextColumn::make('supplier.name')
                    ->placeholder('Unknown Supplier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('person.name')
                    ->label('Person')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bill_number')
                    ->label('Invoice #')
                    ->placeholder('N/A')
                    ->searchable(),
                TextColumn::make('bill_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable()
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
                    ->options(collect(BillStatus::cases())->mapWithKeys(
                        fn ($status) => [$status->value => $status->label()]
                    )),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('bill_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('From'),
                        DatePicker::make('until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('bill_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('bill_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('markPaid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Bill as Paid')
                        ->modalDescription('This will mark the bill as paid.')
                        ->visible(fn ($record) => $record->canBePaid())
                        ->action(function ($record) {
                            $record->markAsPaid();

                            Notification::make()
                                ->success()
                                ->title('Bill marked as paid')
                                ->body("Bill {$record->bill_number} has been marked as paid.")
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignPerson')
                        ->label('Assign to Person')
                        ->icon('heroicon-o-user')
                        ->form([
                            Select::make('person_id')
                                ->label('Person')
                                ->options(Person::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $records->each(fn ($record) => $record->update(['person_id' => $data['person_id']]));

                            Notification::make()
                                ->success()
                                ->title('Bills assigned')
                                ->body($records->count().' bill(s) have been assigned.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('markPaid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Bills as Paid')
                        ->modalDescription(function (Collection $records) {
                            $payableCount = $records->filter(fn ($record) => $record->canBePaid())->count();

                            if ($payableCount === 0) {
                                return 'None of the selected bills can be marked as paid. Bills must be in "Reviewed" status and have a supplier assigned.';
                            }

                            $skippedCount = $records->count() - $payableCount;
                            $message = "{$payableCount} bill(s) will be marked as paid.";

                            if ($skippedCount > 0) {
                                $message .= " {$skippedCount} bill(s) will be skipped as they cannot be marked as paid.";
                            }

                            return $message;
                        })
                        ->action(function (Collection $records) {
                            $paid = 0;

                            $payableRecords = $records->filter(fn ($record) => $record->canBePaid());

                            foreach ($payableRecords as $record) {
                                $record->markAsPaid();
                                $paid++;
                            }

                            if ($paid > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Bills marked as paid')
                                    ->body("{$paid} bill(s) have been marked as paid.")
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
