<?php

namespace App\Filament\Pages;

use App\Enums\BillStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OtherIncomeStatus;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\OtherIncome;
use App\Models\Person;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class EarningsSplit extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $navigationLabel = 'Earnings Split';

    protected static ?string $title = '50/50 Earnings Split';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.earnings-split';

    #[Url]
    public ?string $year = null;

    public function mount(): void
    {
        $this->year = $this->year ?? (string) date('Y');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Reports';
    }

    public function getMonthlyData(): Collection
    {
        $year = (int) $this->year;

        $people = Person::all()->keyBy('id');
        $personIds = $people->keys()->toArray();

        // Get finalized invoices (ready to send or later stages)
        $finalizedStatuses = [
            InvoiceStatus::ReadyToSend->value,
            InvoiceStatus::Sent->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Paid->value,
        ];

        $invoices = Invoice::query()
            ->selectRaw('person_id, period_month as month, SUM(amount_eur) as total')
            ->whereYear('invoice_date', $year)
            ->whereIn('status', $finalizedStatuses)
            ->whereIn('person_id', $personIds)
            ->groupBy('person_id', 'period_month')
            ->get()
            ->groupBy('person_id');

        // Get paid bills
        $bills = Bill::query()
            ->selectRaw('person_id, strftime("%m", bill_date) as month, SUM(amount_eur) as total')
            ->whereYear('bill_date', $year)
            ->where('status', BillStatus::Paid->value)
            ->whereIn('person_id', $personIds)
            ->groupBy('person_id', 'month')
            ->get()
            ->each(fn ($b) => $b->month = (int) $b->month)
            ->groupBy('person_id');

        // Get paid other income
        $otherIncomes = OtherIncome::query()
            ->selectRaw('person_id, strftime("%m", income_date) as month, SUM(amount_eur) as total')
            ->whereYear('income_date', $year)
            ->where('status', OtherIncomeStatus::Paid->value)
            ->whereIn('person_id', $personIds)
            ->groupBy('person_id', 'month')
            ->get()
            ->each(fn ($o) => $o->month = (int) $o->month)
            ->groupBy('person_id');

        // Build monthly data
        $monthlyData = collect();

        for ($month = 1; $month <= 12; $month++) {
            $row = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
            ];

            $earnings = [];

            foreach ($personIds as $personId) {
                $invoiceTotal = $invoices->get($personId)?->firstWhere('month', $month)?->total ?? 0;
                $otherIncomeTotal = $otherIncomes->get($personId)?->firstWhere('month', $month)?->total ?? 0;
                $billTotal = $bills->get($personId)?->firstWhere('month', $month)?->total ?? 0;

                $netEarnings = ($invoiceTotal + $otherIncomeTotal) - $billTotal;
                $earnings[$personId] = $netEarnings;

                $personKey = 'person_'.$personId;
                $row[$personKey.'_name'] = $people->get($personId)?->name ?? 'Unknown';
                $row[$personKey.'_invoices'] = $invoiceTotal;
                $row[$personKey.'_other_income'] = $otherIncomeTotal;
                $row[$personKey.'_bills'] = $billTotal;
                $row[$personKey.'_net'] = $netEarnings;
            }

            // Calculate the split
            $totalEarnings = array_sum($earnings);
            $fairShare = $totalEarnings / 2;

            $row['total_earnings'] = $totalEarnings;
            $row['fair_share'] = $fairShare;

            // Find who owes whom
            $differences = [];
            foreach ($earnings as $personId => $net) {
                $differences[$personId] = $net - $fairShare;
            }

            // Person with positive difference earned more and should pay
            // Person with negative difference earned less and should receive
            $row['differences'] = $differences;
            $row['settlement'] = $this->calculateSettlement($differences, $people);

            $monthlyData->push($row);
        }

        // Add yearly totals
        $yearlyRow = [
            'month' => 13,
            'month_name' => 'YEAR TOTAL',
        ];

        $yearlyEarnings = [];

        foreach ($personIds as $personId) {
            $invoiceTotal = $invoices->get($personId)?->sum('total') ?? 0;
            $otherIncomeTotal = $otherIncomes->get($personId)?->sum('total') ?? 0;
            $billTotal = $bills->get($personId)?->sum('total') ?? 0;

            $netEarnings = ($invoiceTotal + $otherIncomeTotal) - $billTotal;
            $yearlyEarnings[$personId] = $netEarnings;

            $personKey = 'person_'.$personId;
            $yearlyRow[$personKey.'_name'] = $people->get($personId)?->name ?? 'Unknown';
            $yearlyRow[$personKey.'_invoices'] = $invoiceTotal;
            $yearlyRow[$personKey.'_other_income'] = $otherIncomeTotal;
            $yearlyRow[$personKey.'_bills'] = $billTotal;
            $yearlyRow[$personKey.'_net'] = $netEarnings;
        }

        $totalEarnings = array_sum($yearlyEarnings);
        $fairShare = $totalEarnings / 2;

        $yearlyRow['total_earnings'] = $totalEarnings;
        $yearlyRow['fair_share'] = $fairShare;

        $differences = [];
        foreach ($yearlyEarnings as $personId => $net) {
            $differences[$personId] = $net - $fairShare;
        }

        $yearlyRow['differences'] = $differences;
        $yearlyRow['settlement'] = $this->calculateSettlement($differences, $people);

        $monthlyData->push($yearlyRow);

        return $monthlyData;
    }

    protected function calculateSettlement(array $differences, Collection $people): string
    {
        // Find who has the most positive difference (earned more, should pay)
        $maxDiff = max($differences);
        $minDiff = min($differences);

        if (abs($maxDiff) < 1) {
            return 'Already balanced';
        }

        $payerId = array_search($maxDiff, $differences);
        $receiverId = array_search($minDiff, $differences);

        $payerName = $people->get($payerId)?->name ?? 'Unknown';
        $receiverName = $people->get($receiverId)?->name ?? 'Unknown';

        // Get first name only
        $payerFirstName = explode(' ', $payerName)[0];
        $receiverFirstName = explode(' ', $receiverName)[0];

        $amount = abs($maxDiff) / 100;

        return sprintf('%s → %s: €%.2f', $payerFirstName, $receiverFirstName, $amount);
    }

    public function getPeople(): Collection
    {
        return Person::all();
    }
}
