<?php

namespace App\Filament\Pages;

use App\Models\DatabaseBackup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class DownloadDatabase extends Page implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Database Backups';

    protected string $view = 'filament.pages.download-database';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function canAccess(): bool
    {
        $adminEmail = config('app.admin_email');

        if (! $adminEmail) {
            return false;
        }

        return auth()->user()?->email === $adminEmail;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_current')
                ->label('Download Current Database')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    $path = config('database.connections.sqlite.database');

                    if (! file_exists($path)) {
                        throw new \Exception('Database file not found.');
                    }

                    $filename = 'database-backup-'.now()->format('Y-m-d-His').'.sqlite';

                    return response()->download($path, $filename);
                }),
            Action::make('create_backup')
                ->label('Create Backup Now')
                ->icon('heroicon-o-plus-circle')
                ->color('gray')
                ->action(function () {
                    Artisan::call('db:backup');

                    Notification::make()
                        ->title('Backup created successfully')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(DatabaseBackup::query()->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
                TextColumn::make('formatted_size')
                    ->label('Size'),
                TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('bills_count')
                    ->label('Bills')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('stripe_transactions_count')
                    ->label('Stripe Txns')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('other_incomes_count')
                    ->label('Other Income')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('people_count')
                    ->label('People')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('bank_accounts_count')
                    ->label('Bank Accounts')
                    ->numeric()
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (DatabaseBackup $record) {
                        if (! $record->exists()) {
                            Notification::make()
                                ->title('Backup file not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        return response()->download($record->full_path, $record->filename);
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (DatabaseBackup $record) => $record->delete()),
            ])
            ->emptyStateHeading('No backups yet')
            ->emptyStateDescription('Backups are created automatically every night at 2:00 AM. You can also create one manually using the button above.')
            ->emptyStateIcon('heroicon-o-circle-stack');
    }
}
