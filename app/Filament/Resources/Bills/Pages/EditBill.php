<?php

namespace App\Filament\Resources\Bills\Pages;

use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

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

                    $wasPaidNeedsReview = $this->record->status === \App\Enums\BillStatus::PaidNeedsReview;

                    $this->record->markAsReviewed();

                    if ($wasPaidNeedsReview) {
                        Notification::make()
                            ->success()
                            ->title('Bill marked as paid')
                            ->body('The bill has been reviewed and marked as paid.')
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title('Bill marked as reviewed')
                            ->body('The bill is now ready to be marked as paid.')
                            ->send();
                    }
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

            Action::make('viewSupplier')
                ->label('View Supplier')
                ->icon('heroicon-o-building-office')
                ->color('gray')
                ->url(fn () => SupplierResource::getUrl('edit', ['record' => $this->record->supplier]))
                ->visible(fn () => $this->record->supplier_id !== null),

            Action::make('uploadAttachment')
                ->label('Upload Attachment')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Upload Attachment')
                ->modalDescription('Upload a new attachment for this bill. This will replace any existing attachment.')
                ->form([
                    FileUpload::make('attachment')
                        ->label('Attachment')
                        ->disk('local')
                        ->directory('bills')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $attachment = $data['attachment'] ?? null;

                    $newFilePath = is_array($attachment)
                        ? collect($attachment)->first()
                        : $attachment;

                    if (! $newFilePath) {
                        Notification::make()
                            ->danger()
                            ->title('Upload failed')
                            ->body('No file was uploaded.')
                            ->send();

                        return;
                    }

                    // Delete old file if it exists
                    if ($this->record->original_file_path && Storage::disk('local')->exists($this->record->original_file_path)) {
                        Storage::disk('local')->delete($this->record->original_file_path);
                    }

                    $this->record->update([
                        'original_file_path' => $newFilePath,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Attachment uploaded')
                        ->body('The attachment has been uploaded successfully.')
                        ->send();

                    $this->redirect(BillResource::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make(),
        ];
    }
}
