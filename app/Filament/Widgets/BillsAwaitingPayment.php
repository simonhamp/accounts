<?php

namespace App\Filament\Widgets;

use App\Enums\BillStatus;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Bill;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class BillsAwaitingPayment extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Bills Awaiting Payment';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Bill::query()
                    ->readyToPay()
                    ->with('supplier')
                    ->orderBy('due_date')
                    ->orderBy('bill_date')
            )
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->placeholder('Unknown')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bill_number')
                    ->label('Bill #')
                    ->searchable(),
                TextColumn::make('bill_date')
                    ->label('Bill Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->due_date && $record->due_date->isPast() ? 'danger' : null)
                    ->placeholder('No due date'),
                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BillStatus $state) => $state->color()),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Bill $record) => BillResource::getUrl('edit', ['record' => $record])),
                Action::make('markPaid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Bill $record) => $record->markAsPaid()),
            ])
            ->emptyStateHeading('No bills awaiting payment')
            ->emptyStateDescription('All reviewed bills have been paid.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
