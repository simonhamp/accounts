<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function showOriginalPdf(Invoice $invoice): Response
    {
        if (! $invoice->original_file_path) {
            abort(404, 'No original PDF available for this invoice.');
        }

        if (! Storage::disk('local')->exists($invoice->original_file_path)) {
            abort(404, 'Original PDF file not found.');
        }

        return response()->file(
            Storage::disk('local')->path($invoice->original_file_path),
            ['Content-Type' => 'application/pdf']
        );
    }

    public function downloadPdf(Invoice $invoice, string $language = 'es'): Response
    {
        $pdfPath = $language === 'en' ? $invoice->pdf_path_en : $invoice->pdf_path;

        if (! $pdfPath) {
            abort(404, 'No PDF available for this invoice.');
        }

        if (! Storage::exists($pdfPath)) {
            abort(404, 'PDF file not found.');
        }

        $suffix = $language === 'en' ? '-en' : '';
        $filename = $invoice->invoice_number.$suffix.'.pdf';

        return Storage::download($pdfPath, $filename);
    }
}
