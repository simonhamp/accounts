<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label('Mark as Reviewed')
                ->icon('heroicon-o-check')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Mark Bill as Reviewed')
                ->modalDescription('This will save your changes and mark the bill as ready to be paid.')
                ->visible(fn () => $this->record->needsReview())
                ->action(function () {
                    $this->save();

                    $this->record->refresh();

                    if (! $this->record->supplier_id) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot mark as reviewed')
                            ->body('Please assign this bill to a supplier first.')
                            ->send();

                        return;
                    }

                    $this->record->markAsReviewed();

                    Notification::make()
                        ->success()
                        ->title('Bill marked as reviewed')
                        ->body('The bill is now ready to be marked as paid.')
                        ->send();
                }),

            Action::make('markPaid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Bill as Paid')
                ->modalDescription('This will mark the bill as paid.')
                ->visible(fn () => $this->record->canBePaid())
                ->action(function () {
                    $this->record->markAsPaid();

                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->success()
                        ->title('Bill marked as paid')
                        ->body("Bill {$this->record->bill_number} has been marked as paid.")
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
