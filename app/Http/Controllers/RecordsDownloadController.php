<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RecordsDownloadController extends Controller
{
    public function downloadAll(Person $person, int $year): StreamedResponse
    {
        $files = $this->getFilesForYear($person, $year);

        if ($files->isEmpty()) {
            abort(404, 'No files found for this year.');
        }

        $zipFileName = "{$person->name}_{$year}_records.zip";
        $tempZipPath = storage_path("app/temp/{$zipFileName}");

        // Ensure temp directory exists
        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip file.');
        }

        // Create subdirectories in the zip
        $zip->addEmptyDir('invoices');
        $zip->addEmptyDir('bills');
        $zip->addEmptyDir('other_income');

        foreach ($files as $file) {
            $filePath = Storage::disk('local')->path($file['path']);

            if (file_exists($filePath)) {
                $zip->addFile($filePath, "{$file['folder']}/{$file['filename']}");
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($tempZipPath) {
            readfile($tempZipPath);
            @unlink($tempZipPath);
        }, $zipFileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    protected function getFilesForYear(Person $person, int $year): \Illuminate\Support\Collection
    {
        $files = collect();
        $locale = app()->getLocale();

        // Get invoice PDFs - use language-specific PDF
        Invoice::where('person_id', $person->id)
            ->whereYear('invoice_date', $year)
            ->whereNotNull('invoice_date')
            ->each(function ($invoice) use ($files, $locale) {
                // Use English PDF if locale is 'en', otherwise Spanish
                $pdfPath = $locale === 'en' ? $invoice->pdf_path_en : $invoice->pdf_path;

                if ($pdfPath) {
                    $suffix = $locale === 'en' ? '_en' : '';
                    $files->push([
                        'path' => $pdfPath,
                        'folder' => 'invoices',
                        'filename' => "invoice_{$invoice->invoice_number}{$suffix}.pdf",
                    ]);
                }
            });

        // Get bill attachments
        Bill::where('person_id', $person->id)
            ->whereYear('bill_date', $year)
            ->whereNotNull('bill_date')
            ->whereNotNull('original_file_path')
            ->with('supplier')
            ->each(function ($bill) use ($files) {
                $extension = pathinfo($bill->original_file_path, PATHINFO_EXTENSION);
                $supplierName = $bill->supplier?->name ?? 'unknown';
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $supplierName);
                $date = $bill->bill_date->format('Y-m-d');

                $files->push([
                    'path' => $bill->original_file_path,
                    'folder' => 'bills',
                    'filename' => "{$date}_{$safeName}_{$bill->id}.{$extension}",
                ]);
            });

        // Get other income attachments
        OtherIncome::where('person_id', $person->id)
            ->whereYear('income_date', $year)
            ->whereNotNull('income_date')
            ->whereNotNull('original_file_path')
            ->with('incomeSource')
            ->each(function ($income) use ($files) {
                $extension = pathinfo($income->original_file_path, PATHINFO_EXTENSION);
                $sourceName = $income->incomeSource?->name ?? 'other';
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sourceName);
                $date = $income->income_date->format('Y-m-d');

                $files->push([
                    'path' => $income->original_file_path,
                    'folder' => 'other_income',
                    'filename' => "{$date}_{$safeName}_{$income->id}.{$extension}",
                ]);
            });

        return $files;
    }
}
