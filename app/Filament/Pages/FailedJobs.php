<?php

namespace App\Filament\Pages;

use App\Models\FailedJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class FailedJobs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Failed Jobs';

    protected string $view = 'filament.pages.failed-jobs';

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = FailedJob::count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canAccess(): bool
    {
        $adminEmail = config('app.admin_email');

        if (! $adminEmail) {
            return false;
        }

        return auth()->user()?->email === $adminEmail;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->defaultSort('failed_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('job_name')
                    ->label('Job')
                    ->state(fn (FailedJob $record) => $record->getJobName())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('payload', 'like', "%{$search}%");
                    }),
                TextColumn::make('queue')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('short_exception')
                    ->label('Error')
                    ->state(fn (FailedJob $record) => $record->getShortException())
                    ->limit(60)
                    ->tooltip(fn (FailedJob $record) => $record->getShortException())
                    ->wrap(),
                TextColumn::make('failed_at')
                    ->label('Failed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()
                            ->success()
                            ->title('Job queued for retry')
                            ->send();
                    }),
                Action::make('view_exception')
                    ->label('View Error')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (FailedJob $record) => ViewFailedJobException::getUrl(['record' => $record->id])),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:forget', ['id' => $record->uuid]);

                        Notification::make()
                            ->success()
                            ->title('Failed job deleted')
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retry_selected')
                        ->label('Retry Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $uuids = $records->pluck('uuid')->toArray();
                            Artisan::call('queue:retry', ['id' => $uuids]);

                            Notification::make()
                                ->success()
                                ->title(count($uuids).' job(s) queued for retry')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('delete_selected')
                        ->label('Delete Selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            foreach ($records as $record) {
                                Artisan::call('queue:forget', ['id' => $record->uuid]);
                            }

                            Notification::make()
                                ->success()
                                ->title($records->count().' job(s) deleted')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('All queue jobs are running smoothly.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry_all')
                ->label('Retry All')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to retry all failed jobs?')
                ->action(function (): void {
                    Artisan::call('queue:retry', ['id' => ['all']]);

                    Notification::make()
                        ->success()
                        ->title('All failed jobs queued for retry')
                        ->send();
                })
                ->visible(fn () => FailedJob::count() > 0),
            Action::make('flush_all')
                ->label('Delete All')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Are you sure you want to permanently delete all failed jobs? This cannot be undone.')
                ->action(function (): void {
                    Artisan::call('queue:flush');

                    Notification::make()
                        ->success()
                        ->title('All failed jobs deleted')
                        ->send();
                })
                ->visible(fn () => FailedJob::count() > 0),
        ];
    }
}
