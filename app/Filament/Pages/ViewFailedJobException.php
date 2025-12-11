<?php

namespace App\Filament\Pages;

use App\Models\FailedJob;
use Filament\Pages\Page;

class ViewFailedJobException extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'failed-jobs/{record}';

    protected static ?string $title = 'Failed Job Exception';

    protected string $view = 'filament.pages.view-failed-job-exception';

    public FailedJob $failedJob;

    public function mount(int|string $record): void
    {
        $this->failedJob = FailedJob::findOrFail($record);
    }

    public static function canAccess(): bool
    {
        $adminEmail = config('app.admin_email');

        if (! $adminEmail) {
            return false;
        }

        return auth()->user()?->email === $adminEmail;
    }

    public function getHeading(): string
    {
        return 'Exception: '.$this->failedJob->getJobName();
    }

    public function getSubheading(): ?string
    {
        return 'Failed at '.($this->failedJob->failed_at?->format('Y-m-d H:i:s') ?? 'unknown time');
    }
}
