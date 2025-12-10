<?php

namespace App\Http\Controllers;

use App\Models\OtherIncome;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class OtherIncomeController extends Controller
{
    public function showOriginalPdf(OtherIncome $otherIncome): Response
    {
        if (! $otherIncome->original_file_path) {
            abort(404, 'No original PDF available for this income record.');
        }

        if (! Storage::disk('local')->exists($otherIncome->original_file_path)) {
            abort(404, 'Original PDF file not found.');
        }

        return response()->file(
            Storage::disk('local')->path($otherIncome->original_file_path),
            ['Content-Type' => 'application/pdf']
        );
    }
}
