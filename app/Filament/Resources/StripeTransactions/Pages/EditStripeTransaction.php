<?php

namespace App\Filament\Resources\StripeTransactions\Pages;

use App\Filament\Resources\StripeTransactions\StripeTransactionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStripeTransaction extends EditRecord
{
    protected static string $resource = StripeTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_in_stripe')
                ->label('View in Stripe')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->getStripeUrl())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function getStripeUrl(): string
    {
        $transaction = $this->record;
        $baseUrl = 'https://dashboard.stripe.com';

        return match ($transaction->type) {
            'payment' => "{$baseUrl}/payments/{$transaction->stripe_transaction_id}",
            'refund' => "{$baseUrl}/refunds/{$transaction->stripe_transaction_id}",
            'chargeback' => "{$baseUrl}/disputes/{$transaction->stripe_transaction_id}",
            default => $baseUrl,
        };
    }
}
