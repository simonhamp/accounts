<?php

namespace App\Services;

use OpenAI\Client;
use Smalot\PdfParser\Parser;

class BillExtractionService
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
                    'content' => 'You are an expert at extracting structured data from supplier invoices/bills. You are thorough, accurate, and follow instructions precisely. When you find information, you extract it even if you are not 100% certain.',
                ],
                [
                    'role' => 'user',
                    'content' => $this->getExtractionPrompt($text),
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $content = $response->choices[0]->message->content;

        return json_decode($content, true);
    }

    protected function getExtractionPrompt(string $pdfText): string
    {
        return <<<PROMPT
I have extracted the following text from a supplier invoice/bill PDF. This is a bill we RECEIVED from a supplier (not an invoice we issued). Please extract all information and return it as JSON with the following structure:

{
    "supplier_name": "Name of the company/person who issued this bill",
    "supplier_tax_id": "Supplier's Tax ID/VAT/CIF/NIF if available, or null",
    "supplier_address": "Supplier's full address as a single string, or null",
    "supplier_email": "Supplier's email if available, or null",
    "bill_number": "Invoice/Bill number from the document",
    "bill_date": "Date in YYYY-MM-DD format",
    "due_date": "Payment due date in YYYY-MM-DD format if available, or null",
    "currency": "Currency code (e.g., EUR, USD, GBP)",
    "items": [
        {
            "description": "Item/service description",
            "quantity": 1,
            "unit_price": 10000,
            "total": 10000
        }
    ],
    "subtotal": 10000,
    "tax_amount": 2100,
    "total_amount": 12100,
    "is_paid": false,
    "notes": "Any payment details, bank account info, or additional notes"
}

CRITICAL INSTRUCTIONS FOR SUPPLIER EXTRACTION:
- The SUPPLIER is the company/person who ISSUED this bill (they are billing us)
- Look for the company name/logo at the top of the invoice - this is usually the supplier
- Extract the supplier's full contact information including tax ID if present

BILL NUMBER EXTRACTION:
- Look for: Invoice No, Invoice Number, Bill No, Factura, N. de factura, Reference, Ref
- This is the supplier's reference number for this bill

DATE EXTRACTION:
- bill_date: When the invoice was issued (Invoice Date, Fecha, Date)
- due_date: When payment is due (Due Date, Payment Due, Vencimiento)
- Parse dates carefully and convert to YYYY-MM-DD format

TAX ID EXTRACTION:
- Look for: NIF, CIF, DNI, NIE, VAT, Tax ID, Fiscal Number, CIF/NIF, ID Fiscal
- The supplier's tax ID is usually near their company name/address

AMOUNT EXTRACTION:
- All amounts (unit_price, total, subtotal, tax_amount, total_amount) must be in cents (multiply by 100)
- subtotal: Amount before tax
- tax_amount: VAT/Tax amount
- total_amount: Final amount to pay (subtotal + tax)

PAYMENT STATUS DETECTION:
- is_paid: Set to true if there's evidence this bill has already been paid
- Look for: "PAID", "Payment received", "Thank you for your payment", payment confirmation stamps, crossed out amounts, "Paid on [date]"
- If no clear payment indicator is found, set is_paid to false

OTHER INSTRUCTIONS:
- If you genuinely can't find a field after thorough searching, set it to null
- Items array should contain all line items from the bill
- When in doubt, EXTRACT rather than skip - we can verify manually later

Here is the extracted text from the PDF:

{$pdfText}
PROMPT;
    }
}
