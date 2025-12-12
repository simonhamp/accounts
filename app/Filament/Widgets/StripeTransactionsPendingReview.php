<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use App\Models\StripeTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StripeTransactionsPendingReview extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Stripe Transactions Pending Review';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StripeTransaction::query()
                    ->where('status', 'pending_review')
                    ->with('stripeAccount')
                    ->orderBy('transaction_date', 'desc')
            )
            ->columns([
                TextColumn::make('stripeAccount.account_name')
                    ->label('Account')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'payment' => 'success',
                        'refund' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->placeholder('Missing')
                    ->searchable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->placeholder('Missing')
                    ->searchable(),
                TextColumn::make('description')
                    ->placeholder('Missing')
                    ->limit(30),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('transaction_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Review')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (StripeTransaction $record) => StripeTransactionResource::getUrl('edit', ['record' => $record])),
                Action::make('markReady')
                    ->label('Mark Ready')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (StripeTransaction $record) => $record->isComplete())
                    ->action(function (StripeTransaction $record) {
                        $record->markAsReady();

                        Notification::make()
                            ->success()
                            ->title('Transaction marked as ready')
                            ->send();
                    }),
                Action::make('ignore')
                    ->label('Ignore')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Ignore Transaction')
                    ->modalDescription('This transaction will be excluded from invoice generation.')
                    ->action(function (StripeTransaction $record) {
                        $record->markAsIgnored();

                        Notification::make()
                            ->success()
                            ->title('Transaction ignored')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No transactions pending review')
            ->emptyStateDescription('All Stripe transactions have been reviewed.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
