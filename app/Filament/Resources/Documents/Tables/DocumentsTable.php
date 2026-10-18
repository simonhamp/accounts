<?php

namespace App\Filament\Resources\Documents\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        $currentYear = (int) date('Y');
        $years = array_combine(
            range($currentYear, 2023),
            range($currentYear, 2023)
        );

        return $table
            ->columns([
                TextColumn::make('person.name')
                    ->label('Person')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->searchable()
                    ->placeholder('No description'),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn ($state) => $state
                        ? \Carbon\Carbon::create()->month((int) $state)->translatedFormat('F')
                        : null)
                    ->placeholder('All year')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('person_id')
                    ->label('Person')
                    ->relationship('person', 'name'),
                SelectFilter::make('year')
                    ->options($years),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => route('documents.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
