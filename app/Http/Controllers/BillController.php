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
            abort(404, 'No original PDF available for this bill.');
        }

        if (! Storage::disk('local')->exists($bill->original_file_path)) {
            abort(404, 'Original PDF file not found.');
        }

        return response()->file(
            Storage::disk('local')->path($bill->original_file_path),
            ['Content-Type' => 'application/pdf']
        );
    }
}
