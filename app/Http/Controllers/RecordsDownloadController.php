<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class RecordsDownloadController extends Controller
{
    public function downloadAll(Person $person, int $year, Request $request): StreamedResponse
    {
        $month = $request->query('month');
        if ($month !== null && ! preg_match('/^'.$year.'-(0[1-9]|1[0-2])$/', $month)) {
            $month = null;
        }

        $files = $this->getFilesForPeriod($person, $year, $month);

        if ($files->isEmpty()) {
            abort(404, 'No files found for this period.');
        }

        $periodSuffix = $month !== null ? str_replace('-', '_', $month) : (string) $year;
        $zipFileName = "{$person->name}_{$periodSuffix}_records.zip";
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
        $zip->addEmptyDir('documents');

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

    protected function getFilesForPeriod(Person $person, int $year, ?string $month = null): \Illuminate\Support\Collection
    {
        $files = collect();
        $locale = app()->getLocale();

        $applyPeriod = function ($query, string $dateColumn) use ($year, $month) {
            $query->whereYear($dateColumn, $year)->whereNotNull($dateColumn);

            if ($month !== null) {
                $query->whereRaw("strftime('%Y-%m', {$dateColumn}) = ?", [$month]);
            }
        };

        // Get invoice PDFs - use language-specific PDF
        $invoiceQuery = Invoice::where('person_id', $person->id);
        $applyPeriod($invoiceQuery, 'invoice_date');
        $invoiceQuery
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
        $billQuery = Bill::where('person_id', $person->id)
            ->whereNotNull('original_file_path')
            ->with('supplier');
        $applyPeriod($billQuery, 'bill_date');
        $billQuery
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
        $incomeQuery = OtherIncome::where('person_id', $person->id)
            ->whereNotNull('original_file_path')
            ->with('incomeSource');
        $applyPeriod($incomeQuery, 'income_date');
        $incomeQuery
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

        // Get shared documents — only when downloading the whole year
        if ($month === null) {
            Document::forYear($year)
                ->whereNotNull('file_path')
                ->each(function ($document) use ($files) {
                    $files->push([
                        'path' => $document->file_path,
                        'folder' => 'documents',
                        'filename' => $document->original_filename,
                    ]);
                });
        }

        return $files;
    }
}
