<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QuoteController extends Controller
{
    public function downloadPdf(Quote $quote, string $language = 'es'): Response
    {
        $pdfPath = $language === 'en' ? $quote->pdf_path_en : $quote->pdf_path;

        if (! $pdfPath) {
            abort(404, 'No PDF available for this quote.');
        }

        if (! Storage::exists($pdfPath)) {
            abort(404, 'PDF file not found.');
        }

        $suffix = $language === 'en' ? '-en' : '';
        $filename = $quote->quote_number.$suffix.'.pdf';

        return Storage::download($pdfPath, $filename);
    }

    public function showPdf(Quote $quote, string $language = 'es'): Response
    {
        $pdfPath = $language === 'en' ? $quote->pdf_path_en : $quote->pdf_path;

        if (! $pdfPath) {
            abort(404, 'No PDF available for this quote.');
        }

        if (! Storage::exists($pdfPath)) {
            abort(404, 'PDF file not found.');
        }

        return response()->file(
            Storage::path($pdfPath),
            ['Content-Type' => 'application/pdf']
        );
    }
}
