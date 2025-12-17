<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class RecordsController extends Controller
{
    public function index(?int $personId = null, ?int $year = null): View
    {
        $people = Person::orderBy('name')->get();

        if (! $personId && $people->isNotEmpty()) {
            $personId = $people->first()->id;
        }

        $person = $personId ? Person::find($personId) : null;

        // Get available years for this person
        $years = $this->getAvailableYears($person);

        if (! $year && $years->isNotEmpty()) {
            $year = $years->first();
        }

        // Get all records for the selected person and year
        $records = $person && $year ? $this->getRecords($person, $year) : collect();

        return view('records.index', [
            'people' => $people,
            'selectedPerson' => $person,
            'selectedYear' => $year,
            'years' => $years,
            'records' => $records,
        ]);
    }

    protected function getAvailableYears(?Person $person): Collection
    {
        if (! $person) {
            return collect();
        }

        $invoiceYears = Invoice::where('person_id', $person->id)
            ->whereNotNull('invoice_date')
            ->selectRaw('DISTINCT strftime("%Y", invoice_date) as year')
            ->pluck('year');

        $billYears = Bill::where('person_id', $person->id)
            ->whereNotNull('bill_date')
            ->selectRaw('DISTINCT strftime("%Y", bill_date) as year')
            ->pluck('year');

        $otherIncomeYears = OtherIncome::where('person_id', $person->id)
            ->whereNotNull('income_date')
            ->selectRaw('DISTINCT strftime("%Y", income_date) as year')
            ->pluck('year');

        return $invoiceYears
            ->merge($billYears)
            ->merge($otherIncomeYears)
            ->unique()
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->filter(fn ($year) => $year >= 2023)
            ->sortDesc()
            ->values();
    }

    protected function getRecords(Person $person, int $year): Collection
    {
        $locale = app()->getLocale();

        // Get invoices (income)
        $invoices = Invoice::where('person_id', $person->id)
            ->whereYear('invoice_date', $year)
            ->whereNotNull('invoice_date')
            ->get()
            ->map(fn ($invoice) => [
                'type' => 'invoice',
                'date' => $invoice->invoice_date,
                'description' => "Invoice {$invoice->invoice_number}".($invoice->customer ? " - {$invoice->customer->name}" : ''),
                'amount' => $invoice->total_amount,
                'amount_eur' => $invoice->amount_eur,
                'currency' => $invoice->currency,
                'is_income' => true,
                'download_url' => $invoice->pdf_path ? route('invoices.download-pdf', ['invoice' => $invoice, 'language' => $locale]) : null,
                'status' => $invoice->status->label(),
            ]);

        // Get other income
        $otherIncomes = OtherIncome::where('person_id', $person->id)
            ->whereYear('income_date', $year)
            ->whereNotNull('income_date')
            ->with('incomeSource')
            ->get()
            ->map(fn ($income) => [
                'type' => 'other_income',
                'date' => $income->income_date,
                'description' => ($income->incomeSource?->name ?? 'Other Income').($income->description ? " - {$income->description}" : ''),
                'amount' => $income->amount,
                'amount_eur' => $income->amount_eur,
                'currency' => $income->currency,
                'is_income' => true,
                'download_url' => $income->original_file_path ? route('other-incomes.original-pdf', $income) : null,
                'status' => $income->status->label(),
            ]);

        // Get bills (outgoing)
        $bills = Bill::where('person_id', $person->id)
            ->whereYear('bill_date', $year)
            ->whereNotNull('bill_date')
            ->with('supplier')
            ->get()
            ->map(fn ($bill) => [
                'type' => 'bill',
                'date' => $bill->bill_date,
                'description' => ($bill->supplier?->name ?? 'Unknown Supplier').($bill->bill_number ? " - {$bill->bill_number}" : ''),
                'amount' => $bill->total_amount,
                'amount_eur' => $bill->amount_eur,
                'currency' => $bill->currency,
                'is_income' => false,
                'download_url' => $bill->original_file_path ? route('bills.original-pdf', $bill) : null,
                'status' => $bill->status->label(),
            ]);

        return $invoices
            ->merge($otherIncomes)
            ->merge($bills)
            ->sortBy('date')
            ->values();
    }
}
