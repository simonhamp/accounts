<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DownloadDatabase extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Download Database';

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
            Action::make('download')
                ->label('Download Database')
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
        ];
    }
}
