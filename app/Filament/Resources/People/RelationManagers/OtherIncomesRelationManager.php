<?php

namespace App\Filament\Resources\People\RelationManagers;

use App\Models\IncomeSource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OtherIncomesRelationManager extends RelationManager
{
    protected static string $relationship = 'otherIncomes';

    protected static ?string $title = 'Other Income';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('income_source_id')
                    ->label('Income Source')
                    ->options(IncomeSource::active()->pluck('name', 'id'))
                    ->searchable(),
                DatePicker::make('income_date')
                    ->label('Date')
                    ->required()
                    ->default(now()),
                TextInput::make('description')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->required()
                    ->numeric()
                    ->prefix(fn ($get) => match ($get('currency')) {
                        'USD' => '$',
                        'GBP' => '£',
                        default => '€',
                    })
                    ->live(onBlur: true)
                    ->afterStateHydrated(function ($state, $set) {
                        if ($state) {
                            $set('amount', number_format($state / 100, 2, '.', ''));
                        }
                    })
                    ->dehydrateStateUsing(fn ($state) => (int) round((float) $state * 100)),
                Select::make('currency')
                    ->options([
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                        'GBP' => 'GBP',
                    ])
                    ->default('EUR')
                    ->required()
                    ->live(),
                TextInput::make('reference')
                    ->label('Reference')
                    ->maxLength(255),
                FileUpload::make('original_file_path')
                    ->label('PDF Document')
                    ->disk('local')
                    ->directory('other-income-documents')
                    ->acceptedFileTypes(['application/pdf'])
                    ->downloadable()
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('income_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('incomeSource.name')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No source'),
                TextColumn::make('description')
                    ->limit(30)
                    ->searchable(),
                TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable()
                    ->alignEnd(),
                IconColumn::make('has_document')
                    ->label('Doc')
                    ->icon(fn ($record) => $record->hasOriginalFile() ? 'heroicon-o-document' : null)
                    ->color('gray'),
            ])
            ->defaultSort('income_date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
