<?php

namespace App\Services;

use OpenAI\Client;
use Smalot\PdfParser\Parser;

class InvoiceExtractionService
{
    public function __construct(
        protected Client $openai
    ) {}

    public function extractFromPdf(string $pdfPath, array $excludeAddresses = []): array
    {
        // Parse PDF and extract text
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        // Call OpenAI API with latest model
        $response = $this->openai->chat()->create([
            'model' => 'gpt-5.1', // Latest GPT-5.1 version
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert at extracting structured data from invoices. You are thorough, accurate, and follow instructions precisely. When you find information, you extract it even if you are not 100% certain.',
                ],
                [
                    'role' => 'user',
                    'content' => $this->getExtractionPrompt($text, $excludeAddresses),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2, // Slightly higher for more flexible interpretation
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }

    protected function getExtractionPrompt(string $pdfText, array $excludeAddresses = []): string
    {
        return <<<PROMPT
I have extracted the following text from an invoice PDF. Please extract all invoice information and return it as JSON with the following structure:

{
    "invoice_number": "Invoice number from the document",
    "invoice_date": "Date in YYYY-MM-DD format",
    "all_addresses": [
        "Full address 1 as a single string",
        "Full address 2 as a single string"
    ],
    "customer_name": "Full customer name (could be near one of the addresses)",
    "customer_email": "Customer email if available, or null",
    "customer_tax_id": "Tax ID/VAT/DNI/NIE if available, or null",
    "currency": "Currency code (e.g., EUR, USD, GBP)",
    "items": [
        {
            "description": "Item description",
            "quantity": 1,
            "unit_price": 10000,
            "total": 10000
        }
    ],
    "total_amount": 10000,
    "notes": "Any additional notes or payment information"
}

CRITICAL INSTRUCTIONS FOR ADDRESS EXTRACTION:
- Extract ALL addresses found in the invoice into the "all_addresses" array
- Include complete addresses with street, city, postal code, country
- There will typically be 2 addresses: sender/issuer and customer/recipient
- Extract every address you find, even if you're not sure which is which
- Format each address as a complete single string

TAX ID EXTRACTION:
- Look EVERYWHERE in the document for tax/VAT/fiscal IDs
- Common labels: NIF, CIF, DNI, NIE, VAT, Tax ID, Fiscal Number, Tax Number, CIF/NIF, ID Fiscal
- The tax ID might be near the customer name, in a separate field, or in small print
- Extract ANY alphanumeric code that looks like a tax identifier
- Be flexible - tax IDs can appear in various formats

OTHER INSTRUCTIONS:
- All amounts (unit_price, total, total_amount) must be in cents/pennies (multiply by 100)
- If you genuinely can't find a field after thorough searching, set it to null
- Items array should contain all line items from the invoice
- Parse dates carefully and convert to YYYY-MM-DD format
- When in doubt, EXTRACT rather than skip - we can verify manually later

Here is the extracted text from the PDF:

{$pdfText}
PROMPT;
    }

    protected function buildExcludeAddressesText(array $excludeAddresses): string
    {
        if (empty($excludeAddresses)) {
            return '';
        }

        $text = "- These are the SENDER/ISSUER addresses (ignore these, they are NOT the customer):\n";
        foreach ($excludeAddresses as $address) {
            $text .= "  * {$address}\n";
        }
        $text .= "- Extract the OTHER address - the one that is NOT listed above\n";

        return $text;
    }
}
