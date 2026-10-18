<?php

namespace App\Filament\Resources\Brands\Actions;

use App\Exceptions\BrandMailException;
use App\Models\Brand;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Mail\Message;
use Throwable;

/**
 * Send a short message through a brand's saved mail settings, to check them
 * before invoice emails are turned on. A failure is shown with the password
 * taken out and is not logged.
 */
class SendTestEmailAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'sendTestEmail';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Send test email')
            ->icon('heroicon-o-paper-airplane')
            ->color('gray')
            ->disabled(fn (Brand $record): bool => ! $record->hasCompleteMailSettings())
            ->tooltip(fn (Brand $record): ?string => $record->hasCompleteMailSettings()
                ? null
                : 'Save a sender name, from address and mail server first')
            ->modalHeading(fn (Brand $record): string => "Send a test email for {$record->name}")
            ->modalDescription('This uses the saved settings, so save any changes first. Invoice emails don\'t need to be turned on.')
            ->form([
                TextInput::make('to')
                    ->label('Send to')
                    ->email()
                    ->required()
                    ->default(fn () => auth()->user()?->email),
            ])
            ->modalSubmitActionLabel('Send')
            ->action(function (Brand $record, array $data): void {
                try {
                    $record->mailer()->raw($this->body($record), function (Message $message) use ($record, $data) {
                        $message->to($data['to'])
                            ->from($record->invoice_from_address, $record->invoice_sender_name)
                            ->subject("Test email for {$record->name} invoices");

                        if ($record->invoice_reply_to) {
                            $message->replyTo($record->invoice_reply_to);
                        }
                    });
                } catch (Throwable $e) {
                    Notification::make()
                        ->danger()
                        ->title('Test email failed')
                        ->body(BrandMailException::from($record, $e, "Couldn't send the test email")->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Test email sent')
                    ->body("Sent to {$data['to']} through {$record->smtp_host}. Check that it arrived.")
                    ->send();
            });
    }

    protected function body(Brand $brand): string
    {
        return "This is a test email from the accounts app, sent through {$brand->name}'s mail server ({$brand->smtp_host}:{$brand->smtp_port}).\n\n"
            ."If it arrived, {$brand->name}'s invoice emails can go out from {$brand->invoice_from_address}.\n";
    }
}
