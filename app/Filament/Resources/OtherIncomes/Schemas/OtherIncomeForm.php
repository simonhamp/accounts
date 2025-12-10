<?php

namespace App\Filament\Resources\OtherIncomes\Schemas;

use App\Enums\OtherIncomeStatus;
use App\Models\BankAccount;
use App\Models\IncomeSource;
use App\Models\Person;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class OtherIncomeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Income Details')
                    ->components([
                        Select::make('person_id')
                            ->label('Person')
                            ->options(Person::pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Select::make('income_source_id')
                            ->label('Income Source')
                            ->options(IncomeSource::active()->pluck('name', 'id'))
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Textarea::make('description')
                                    ->rows(2),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return IncomeSource::create([
                                    'name' => $data['name'],
                                    'description' => $data['description'] ?? null,
                                    'is_active' => true,
                                ])->id;
                            }),
                        DatePicker::make('income_date')
                            ->label('Date')
                            ->required()
                            ->default(now()),
                        TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Amount')
                    ->components([
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
                                'EUR' => 'EUR - Euro',
                                'USD' => 'USD - US Dollar',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('EUR')
                            ->required()
                            ->live(),
                        TextInput::make('reference')
                            ->label('Reference')
                            ->helperText('Transaction ID, payout ID, etc.')
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Section::make('Payment')
                    ->description('Track the actual payment received for this income.')
                    ->components([
                        Select::make('status')
                            ->label('Status')
                            ->options(OtherIncomeStatus::class)
                            ->default(OtherIncomeStatus::Pending)
                            ->required()
                            ->live(),
                        TextInput::make('amount_paid')
                            ->label('Amount Paid')
                            ->numeric()
                            ->prefix(fn ($get) => match ($get('currency')) {
                                'USD' => '$',
                                'GBP' => '£',
                                default => '€',
                            })
                            ->helperText('Leave blank if same as expected amount')
                            ->afterStateHydrated(function ($state, $set) {
                                if ($state) {
                                    $set('amount_paid', number_format($state / 100, 2, '.', ''));
                                }
                            })
                            ->dehydrateStateUsing(fn ($state) => $state ? (int) round((float) $state * 100) : null),
                        Select::make('bank_account_id')
                            ->label('Paid Into Account')
                            ->options(BankAccount::active()->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Select bank account'),
                        DatePicker::make('paid_at')
                            ->label('Date Received')
                            ->placeholder('Select date'),
                    ])
                    ->columns(4)
                    ->collapsible(),

                Section::make('Source Document')
                    ->description('Upload the original PDF or note the CSV filename this income was imported from.')
                    ->components([
                        FileUpload::make('original_file_path')
                            ->label('Original File (PDF)')
                            ->disk('local')
                            ->directory('other-income-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                        TextInput::make('source_filename')
                            ->label('Source Filename')
                            ->helperText('If imported from CSV, the original filename.')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->collapsible(),

                Section::make('Original PDF')
                    ->components([
                        Placeholder::make('pdf_preview')
                            ->label('')
                            ->content(function ($record) {
                                $url = route('other-incomes.original-pdf', $record);

                                return new HtmlString(
                                    '<iframe src="'.$url.'" class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 600px;"></iframe>'
                                );
                            }),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => $record?->original_file_path && Storage::disk('local')->exists($record->original_file_path)),

                Section::make('Additional Information')
                    ->components([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Fieldset::make('Extracted Data')
                            ->components([
                                Textarea::make('extracted_data_display')
                                    ->label('')
                                    ->disabled()
                                    ->rows(4)
                                    ->afterStateHydrated(function ($state, $set, $record) {
                                        if ($record && $record->extracted_data) {
                                            $set('extracted_data_display', json_encode($record->extracted_data, JSON_PRETTY_PRINT));
                                        }
                                    })
                                    ->columnSpanFull(),
                            ])
                            ->visible(fn ($record) => $record && ! empty($record->extracted_data)),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
