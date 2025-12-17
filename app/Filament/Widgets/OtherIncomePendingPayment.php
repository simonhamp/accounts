<?php

namespace App\Filament\Widgets;

use App\Enums\OtherIncomeStatus;
use App\Filament\Resources\OtherIncomes\OtherIncomeResource;
use App\Models\OtherIncome;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OtherIncomePendingPayment extends TableWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Other Income Pending Payment';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OtherIncome::query()
                    ->where('status', OtherIncomeStatus::Pending)
                    ->with('person')
                    ->orderBy('income_date')
            )
            ->columns([
                TextColumn::make('person.name')
                    ->label('Person')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('income_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('reference')
                    ->label('Reference')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (OtherIncome $record) => OtherIncomeResource::getUrl('edit', ['record' => $record])),
                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Paid')
                    ->modalDescription('This will mark the other income as paid.')
                    ->action(function (OtherIncome $record) {
                        $record->markAsPaid();

                        Notification::make()
                            ->success()
                            ->title('Marked as paid')
                            ->body('The income has been marked as paid.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No pending other income')
            ->emptyStateDescription('All other income has been marked as paid.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
