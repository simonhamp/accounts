<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BillController extends Controller
{
    public function showOriginalPdf(Bill $bill): Response
    {
        if (! $bill->original_file_path) {
            abort(404, 'No original document available for this bill.');
        }

        if (! Storage::disk('local')->exists($bill->original_file_path)) {
            abort(404, 'Original document file not found.');
        }

        $filePath = Storage::disk('local')->path($bill->original_file_path);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        return response()->file($filePath, ['Content-Type' => $mimeType]);
    }
}
