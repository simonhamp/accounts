<?php

namespace App\Filament\Resources\MonthlyChecklists\Tables;

use App\Filament\Resources\MonthlyChecklists\MonthlyChecklistResource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MonthlyChecklistsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_name')
                    ->label('Month')
                    ->sortable(['period_year', 'period_month'])
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereRaw("DATE_FORMAT(CONCAT(period_year, '-', period_month, '-01'), '%M %Y') LIKE ?", ["%{$search}%"]);
                    }),
                IconColumn::make('completed_at')
                    ->label('Complete')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isComplete()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('toggleComplete')
                    ->label(fn ($record) => $record->isComplete() ? 'Mark Incomplete' : 'Mark Complete')
                    ->icon(fn ($record) => $record->isComplete() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->isComplete() ? 'gray' : 'success')
                    ->action(function ($record) {
                        if ($record->isComplete()) {
                            $record->markAsIncomplete();
                        } else {
                            $record->markAsComplete();
                        }
                    }),
                ViewAction::make()
                    ->url(fn ($record) => MonthlyChecklistResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('period_year', 'desc')
            ->defaultSort('period_month', 'desc');
    }
}
