<?php

namespace App\Services;

use OpenAI\Client;
use Smalot\PdfParser\Parser;

class OtherIncomeExtractionService
{
    public function __construct(
        protected Client $openai
    ) {}

    public function extractFromPdf(string $pdfPath): array
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        $response = $this->openai->chat()->create([
            'model' => 'gpt-5.1',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting structured data from income documents like payslips, payout statements, and income confirmations. You are thorough, accurate, and follow instructions precisely.',
                ],
                [
                    'role' => 'user',
                    'content' => $this->getPdfExtractionPrompt($text),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }

    public function extractFromCsvData(array $headers, array $rows, string $filename): array
    {
        $csvContent = implode(',', $headers)."\n";
        foreach (array_slice($rows, 0, 50) as $row) {
            $csvContent .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\n";
        }

        $response = $this->openai->chat()->create([
            'model' => 'gpt-5.1',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting structured data from payout CSVs. You understand various payout formats from services like LemonSqueezy, Paddle, GitHub Sponsors, affiliate networks, and similar platforms.',
                ],
                [
                    'role' => 'user',
                    'content' => $this->getCsvExtractionPrompt($csvContent, $filename),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }

    protected function getPdfExtractionPrompt(string $pdfText): string
    {
        return <<<PROMPT
I have extracted the following text from an income document PDF (payslip, payout statement, income confirmation, etc.). Please extract all information and return it as JSON with the following structure:

{
    "income_date": "Date in YYYY-MM-DD format (payment date, pay period end, or document date)",
    "description": "Brief description of the income (e.g., 'Salary - December 2024', 'GitHub Sponsors Payout')",
    "payer_name": "Name of the company/platform making the payment",
    "amount": 150000,
    "currency": "Currency code (e.g., EUR, USD, GBP)",
    "reference": "Transaction ID, payout ID, or reference number if available",
    "income_source_suggestion": "One of: GitHub Sponsors, LemonSqueezy, ShopMy, Bifrost, Production Payslip, Consulting, Affiliate Income, or a descriptive suggestion",
    "additional_data": {
        "gross_amount": 200000,
        "deductions": 50000,
        "tax_withheld": 30000,
        "period_start": "2024-12-01",
        "period_end": "2024-12-31",
        "notes": "Any additional relevant information"
    }
}

INSTRUCTIONS:
- All amounts must be in cents (multiply by 100). For example, €1,500.00 becomes 150000
- income_date: The date the payment was made or the period end date
- description: A concise but descriptive summary of the income
- income_source_suggestion: Try to identify the type/source of income
- additional_data: Include any other relevant extracted information

Here is the extracted text from the PDF:

{$pdfText}
PROMPT;
    }

    protected function getCsvExtractionPrompt(string $csvContent, string $filename): string
    {
        return <<<PROMPT
I have a CSV file from a payout service. The filename is: {$filename}

Please analyze this CSV and return JSON with the following structure:

{
    "platform_detected": "Name of the platform (LemonSqueezy, Paddle, GitHub, etc.) if identifiable",
    "income_source_suggestion": "One of: GitHub Sponsors, LemonSqueezy, ShopMy, Bifrost, Consulting, Affiliate Income, or a descriptive suggestion",
    "column_mapping": {
        "date_column": "name of the column containing the date",
        "amount_column": "name of the column containing the payout amount",
        "currency_column": "name of the column containing currency (or null if fixed)",
        "reference_column": "name of the column containing transaction/payout ID (or null)",
        "description_column": "name of the column for description (or null)"
    },
    "currency_fixed": "If all rows have the same currency, specify it here (e.g., 'USD'), otherwise null",
    "date_format": "The format of dates in the file (e.g., 'Y-m-d', 'm/d/Y', 'd/m/Y')",
    "amount_format": "How amounts are formatted: 'decimal' (e.g., 123.45), 'cents' (e.g., 12345), 'comma_decimal' (e.g., 123,45)",
    "payouts": [
        {
            "income_date": "Date in YYYY-MM-DD format",
            "description": "Description of the payout",
            "amount": 150000,
            "currency": "USD",
            "reference": "payout_123456 or null"
        }
    ],
    "total_count": 50,
    "total_amount": 7500000
}

IMPORTANT INSTRUCTIONS:
- All amounts in the "payouts" array must be converted to cents (multiply decimal values by 100)
- Dates must be converted to YYYY-MM-DD format
- Include ALL rows as individual payouts in the array
- Be thorough - extract every row from the CSV

Here is the CSV content:

{$csvContent}
PROMPT;
    }
}
