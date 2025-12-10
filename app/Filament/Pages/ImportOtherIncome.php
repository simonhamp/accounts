<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessOtherIncomeImport;
use App\Models\IncomeSource;
use App\Models\OtherIncome;
use App\Models\Person;
use App\Services\OtherIncomeExtractionService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportOtherIncome extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return 'Income';
    }

    public static function getNavigationLabel(): string
    {
        return 'Import Other Income';
    }

    protected static ?string $title = 'Import Other Income';

    protected string $view = 'filament.pages.import-other-income';

    public ?array $pdfData = [];

    public ?array $csvData = [];

    public ?array $csvPreviewData = null;

    public ?string $csvPath = null;

    public ?string $originalFilename = null;

    public ?string $suggestedSourceName = null;

    public function mount(): void
    {
        $this->pdfForm->fill();
        $this->csvForm->fill();
    }

    protected function getForms(): array
    {
        return [
            'pdfForm',
            'csvForm',
        ];
    }

    public function pdfForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload Income Documents')
                    ->description('Upload PDF documents for income extraction (payslips, payout statements, etc.). They will be queued for AI extraction and can be reviewed afterward.')
                    ->components([
                        Select::make('person_id')
                            ->label('Person')
                            ->options(Person::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Select the person this income belongs to'),
                        Select::make('income_source_id')
                            ->label('Income Source (Optional)')
                            ->options(IncomeSource::active()->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Pre-select an income source, or let AI detect it'),
                        FileUpload::make('pdfs')
                            ->label('Income PDFs')
                            ->acceptedFileTypes(['application/pdf'])
                            ->multiple()
                            ->maxFiles(20)
                            ->maxSize(10240)
                            ->disk('local')
                            ->directory('other-income-documents')
                            ->required()
                            ->helperText('Upload up to 20 PDF files at once (max 10MB each)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('pdfData');
    }

    public function csvForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Upload Payout CSV')
                    ->description('Upload a CSV file from your payout provider (LemonSqueezy, GitHub Sponsors, affiliates, etc.). AI will analyze the file structure and extract income records.')
                    ->components([
                        Select::make('person_id')
                            ->label('Person')
                            ->options(Person::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->helperText('Select the person this income belongs to'),
                        Select::make('income_source_id')
                            ->label('Income Source')
                            ->options(IncomeSource::active()->pluck('name', 'id'))
                            ->searchable()
                            ->createOptionForm([
                                \Filament\Forms\Components\TextInput::make('name')
                                    ->label('Source Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Enter a name for this new income source'),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return IncomeSource::create([
                                    'name' => $data['name'],
                                    'is_active' => true,
                                ])->id;
                            })
                            ->helperText(fn () => $this->suggestedSourceName
                                ? "Suggested: {$this->suggestedSourceName} (based on filename)"
                                : 'Select an existing source, or type to create a new one'),
                        FileUpload::make('csv')
                            ->label('Payout CSV')
                            ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                            ->maxSize(5120)
                            ->disk('local')
                            ->directory('other-income-csv')
                            ->required()
                            ->helperText('Upload your payout CSV file (max 5MB)')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->statePath('csvData');
    }

    public function queuePdfs(): void
    {
        $data = $this->pdfForm->getState();

        if (empty($data['pdfs'])) {
            Notification::make()
                ->danger()
                ->title('Please upload at least one PDF file')
                ->send();

            return;
        }

        $queued = 0;

        foreach ($data['pdfs'] as $pdfPath) {
            $otherIncome = OtherIncome::create([
                'person_id' => $data['person_id'],
                'income_source_id' => $data['income_source_id'] ?? null,
                'income_date' => now(),
                'description' => 'Pending extraction...',
                'amount' => 0,
                'currency' => 'EUR',
                'original_file_path' => $pdfPath,
            ]);

            ProcessOtherIncomeImport::dispatch($otherIncome);
            $queued++;
        }

        $this->pdfForm->fill();

        Notification::make()
            ->success()
            ->title("{$queued} document(s) queued for processing")
            ->body('You can review them in Other Income once extraction is complete.')
            ->send();
    }

    public function analyzeCsv(): void
    {
        $data = $this->csvForm->getState();

        if (empty($data['csv'])) {
            Notification::make()
                ->danger()
                ->title('Please upload a CSV file')
                ->send();

            return;
        }

        $this->csvPath = $data['csv'];
        $fullPath = Storage::disk('local')->path($this->csvPath);
        $this->originalFilename = basename($this->csvPath);

        if (! file_exists($fullPath)) {
            Notification::make()
                ->danger()
                ->title('CSV file not found')
                ->send();

            return;
        }

        try {
            $csvContent = $this->parseCsv($fullPath);

            if (empty($csvContent['headers']) || empty($csvContent['rows'])) {
                Notification::make()
                    ->danger()
                    ->title('CSV file appears to be empty or invalid')
                    ->send();

                return;
            }

            $extractionService = app(OtherIncomeExtractionService::class);
            $extracted = $extractionService->extractFromCsvData(
                $csvContent['headers'],
                $csvContent['rows'],
                $this->originalFilename
            );

            $this->csvPreviewData = [
                'platform' => $extracted['platform_detected'] ?? 'Unknown',
                'source_suggestion' => $extracted['income_source_suggestion'] ?? null,
                'currency' => $extracted['currency_fixed'] ?? 'Various',
                'total_count' => $extracted['total_count'] ?? count($extracted['payouts'] ?? []),
                'total_amount' => $extracted['total_amount'] ?? 0,
                'payouts' => $extracted['payouts'] ?? [],
                'raw_extraction' => $extracted,
            ];

            // Set the suggested source name from AI analysis or filename
            $this->suggestedSourceName = $extracted['income_source_suggestion']
                ?? $this->guessSourceFromFilename($this->originalFilename);

            // If no source is manually selected, try to find a matching existing source
            if (empty($data['income_source_id']) && $this->suggestedSourceName) {
                $matchingSource = IncomeSource::query()
                    ->where('name', 'like', '%'.$this->suggestedSourceName.'%')
                    ->orWhere('name', 'like', '%'.str_replace(['-', '_', ' '], '%', $this->suggestedSourceName).'%')
                    ->first();

                if ($matchingSource) {
                    $this->csvData['income_source_id'] = $matchingSource->id;
                }
            }

            Notification::make()
                ->success()
                ->title('CSV analyzed successfully')
                ->body("Found {$this->csvPreviewData['total_count']} payout records")
                ->send();
        } catch (\Exception $e) {
            Log::error('CSV analysis failed', [
                'error' => $e->getMessage(),
                'file' => $this->originalFilename,
            ]);

            Notification::make()
                ->danger()
                ->title('Failed to analyze CSV')
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function guessSourceFromFilename(string $filename): ?string
    {
        // Remove extension and common suffixes
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[-_]?(payouts?|transactions?|export|report|data|\d{4}[-_]?\d{2}[-_]?\d{2}|\d+)$/i', '', $name);
        $name = trim($name, '-_ ');

        if (empty($name)) {
            return null;
        }

        // Convert to title case and clean up
        $name = str_replace(['-', '_'], ' ', $name);
        $name = ucwords(strtolower($name));

        return $name ?: null;
    }

    public function importCsvRecords(): void
    {
        if (! $this->csvPreviewData || empty($this->csvPreviewData['payouts'])) {
            Notification::make()
                ->danger()
                ->title('No records to import')
                ->send();

            return;
        }

        $data = $this->csvForm->getState();
        $personId = $data['person_id'];
        $incomeSourceId = $data['income_source_id'];

        // If no source selected, use the suggested source name to create one
        if (! $incomeSourceId && $this->suggestedSourceName) {
            $source = IncomeSource::firstOrCreate(
                ['name' => $this->suggestedSourceName],
                ['is_active' => true]
            );
            $incomeSourceId = $source->id;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($this->csvPreviewData['payouts'] as $payout) {
            if (empty($payout['amount']) || $payout['amount'] <= 0) {
                $skipped++;

                continue;
            }

            $reference = $payout['reference'] ?? null;

            if ($reference) {
                $existing = OtherIncome::where('reference', $reference)
                    ->where('person_id', $personId)
                    ->exists();

                if ($existing) {
                    $skipped++;

                    continue;
                }
            }

            OtherIncome::create([
                'person_id' => $personId,
                'income_source_id' => $incomeSourceId,
                'income_date' => $payout['income_date'] ?? now()->format('Y-m-d'),
                'description' => $payout['description'] ?? 'CSV Import',
                'amount' => $payout['amount'],
                'currency' => $payout['currency'] ?? 'EUR',
                'reference' => $reference,
                'source_filename' => $this->originalFilename,
                'extracted_data' => $payout,
            ]);

            $imported++;
        }

        $this->resetCsvForm();

        $message = "{$imported} record(s) imported successfully";
        if ($skipped > 0) {
            $message .= " ({$skipped} skipped as duplicates or zero amounts)";
        }

        Notification::make()
            ->success()
            ->title('Import complete')
            ->body($message)
            ->send();
    }

    public function resetCsvForm(): void
    {
        $this->csvPreviewData = null;
        $this->csvPath = null;
        $this->originalFilename = null;
        $this->suggestedSourceName = null;
        $this->csvForm->fill();
    }

    protected function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \Exception('Could not open CSV file');
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \Exception('Could not read CSV headers');
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }
}
