<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        $currentYear = (int) date('Y');
        $years = array_combine(
            range($currentYear, 2023),
            range($currentYear, 2023)
        );

        return $schema
            ->components([
                Section::make('Document Details')
                    ->components([
                        Select::make('person_id')
                            ->label('Person')
                            ->relationship('person', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => \App\Models\Person::default()?->id),

                        FileUpload::make('file_path')
                            ->label('File')
                            ->disk('local')
                            ->directory('documents')
                            ->maxSize(10240)
                            ->required()
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames()
                            ->storeFileNamesIn('original_filename'),

                        TextInput::make('original_filename')
                            ->label('Original Filename')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn ($record) => $record !== null),

                        Select::make('year')
                            ->label('Year')
                            ->options($years)
                            ->default($currentYear)
                            ->required(),

                        Select::make('month')
                            ->label('Month')
                            ->options([
                                1 => 'January',
                                2 => 'February',
                                3 => 'March',
                                4 => 'April',
                                5 => 'May',
                                6 => 'June',
                                7 => 'July',
                                8 => 'August',
                                9 => 'September',
                                10 => 'October',
                                11 => 'November',
                                12 => 'December',
                            ])
                            ->placeholder('All year')
                            ->helperText('Optional. Leave empty for documents that apply to the whole year.'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->helperText('Optional description of what this document contains'),
                    ]),

                Section::make('Preview')
                    ->components([
                        Placeholder::make('file_preview')
                            ->label('')
                            ->content(function ($record) {
                                if (! $record?->file_path) {
                                    return null;
                                }

                                $extension = strtolower(pathinfo($record->file_path, PATHINFO_EXTENSION));
                                $url = route('documents.download', $record);

                                if ($extension === 'pdf') {
                                    return new HtmlString(
                                        '<iframe src="'.$url.'" class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 600px;"></iframe>'
                                    );
                                }

                                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    return new HtmlString(
                                        '<img src="'.$url.'" class="rounded-lg border border-gray-200 dark:border-gray-700" style="max-width: 100%; max-height: 600px; height: auto; object-fit: contain;" />'
                                    );
                                }

                                return new HtmlString(
                                    '<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">'.
                                    '<p class="text-gray-500">Preview not available for this file type</p>'.
                                    '<a href="'.$url.'" class="text-primary-600 hover:underline mt-2 inline-block">Download file</a>'.
                                    '</div>'
                                );
                            }),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record?->file_path && Storage::disk('local')->exists($record->file_path)),
            ]);
    }
}
