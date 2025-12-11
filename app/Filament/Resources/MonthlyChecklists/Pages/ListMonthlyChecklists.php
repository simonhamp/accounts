<?php

namespace App\Filament\Resources\MonthlyChecklists\Pages;

use App\Filament\Resources\MonthlyChecklists\MonthlyChecklistResource;
use App\Models\MonthlyChecklist;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListMonthlyChecklists extends ListRecords
{
    protected static string $resource = MonthlyChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate Current Month')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn () => ! MonthlyChecklist::currentMonth()->exists())
                ->action(function () {
                    Artisan::call('checklist:generate');

                    Notification::make()
                        ->success()
                        ->title('Checklist generated')
                        ->body('The checklist for the current month has been generated.')
                        ->send();

                    $this->redirect(MonthlyChecklistResource::getUrl('view', [
                        'record' => MonthlyChecklist::currentMonth()->first(),
                    ]));
                }),
            Action::make('viewCurrent')
                ->label('View Current Month')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn () => MonthlyChecklist::currentMonth()->exists())
                ->url(fn () => MonthlyChecklistResource::getUrl('view', [
                    'record' => MonthlyChecklist::currentMonth()->first(),
                ])),
        ];
    }
}
