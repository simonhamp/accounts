<?php

namespace App\Jobs;

use App\Models\IncomeSource;
use App\Models\OtherIncome;
use App\Services\OtherIncomeExtractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessOtherIncomeImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public OtherIncome $otherIncome
    ) {}

    public function handle(OtherIncomeExtractionService $extractionService): void
    {
        if (! $this->otherIncome->original_file_path) {
            Log::warning('OtherIncome import: No original file path', [
                'other_income_id' => $this->otherIncome->id,
            ]);

            return;
        }

        $pdfPath = Storage::disk('local')->path($this->otherIncome->original_file_path);

        if (! file_exists($pdfPath)) {
            Log::error('OtherIncome import: PDF file not found', [
                'other_income_id' => $this->otherIncome->id,
                'path' => $pdfPath,
            ]);

            return;
        }

        try {
            $extracted = $extractionService->extractFromPdf($pdfPath);

            $incomeSource = $this->findOrCreateIncomeSource($extracted['income_source_suggestion'] ?? null);

            $incomeDate = ! empty($extracted['income_date'])
                ? \Carbon\Carbon::parse($extracted['income_date'])
                : now();

            $this->otherIncome->update([
                'income_source_id' => $incomeSource?->id,
                'income_date' => $incomeDate,
                'description' => $extracted['description'] ?? 'Imported income',
                'amount' => $extracted['amount'] ?? 0,
                'currency' => $extracted['currency'] ?? 'EUR',
                'reference' => $extracted['reference'] ?? null,
                'extracted_data' => $extracted,
            ]);

            Log::info('OtherIncome extraction completed', [
                'other_income_id' => $this->otherIncome->id,
                'description' => $extracted['description'] ?? 'unknown',
                'amount' => $extracted['amount'] ?? 0,
            ]);
        } catch (Throwable $e) {
            Log::error('OtherIncome extraction failed', [
                'other_income_id' => $this->otherIncome->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('OtherIncome import job failed permanently', [
            'other_income_id' => $this->otherIncome->id,
            'error' => $exception->getMessage(),
        ]);

        $this->otherIncome->update([
            'notes' => 'Extraction failed: '.$exception->getMessage(),
        ]);
    }

    protected function findOrCreateIncomeSource(?string $suggestion): ?IncomeSource
    {
        if (! $suggestion) {
            return null;
        }

        $source = IncomeSource::query()
            ->where('name', 'like', '%'.$suggestion.'%')
            ->first();

        if ($source) {
            return $source;
        }

        return IncomeSource::create([
            'name' => $suggestion,
            'is_active' => true,
        ]);
    }
}
