<?php

namespace App\Filament\Resources\MonthlyChecklists\Pages;

use App\Filament\Resources\MonthlyChecklists\MonthlyChecklistResource;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\IncomeSource;
use App\Models\Invoice;
use App\Models\MonthlyChecklist;
use App\Models\OtherIncome;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;

class ViewMonthlyChecklist extends Page
{
    protected static string $resource = MonthlyChecklistResource::class;

    protected string $view = 'filament.resources.monthly-checklists.pages.view-monthly-checklist';

    public MonthlyChecklist $record;

    public function mount(MonthlyChecklist $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return $this->record->period_name.' Checklist';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Items')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Refresh Checklist Items')
                ->modalDescription('This will add any new suppliers or income sources that are now expecting items this month. Existing items will not be affected.')
                ->action(function () {
                    Artisan::call('checklist:generate', [
                        '--month' => $this->record->period_month,
                        '--year' => $this->record->period_year,
                        '--force' => true,
                    ]);

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Checklist refreshed')
                        ->send();
                }),
        ];
    }

    #[Computed]
    public function suppliers(): array
    {
        $supplierIds = array_keys($this->record->items['suppliers'] ?? []);

        // Get supplier IDs that already have a bill this month
        $suppliersWithBillsThisMonth = Bill::whereIn('supplier_id', $supplierIds)
            ->whereMonth('bill_date', $this->record->period_month)
            ->whereYear('bill_date', $this->record->period_year)
            ->pluck('supplier_id')
            ->unique()
            ->toArray();

        // Filter out suppliers that already have a bill this month
        $pendingSupplierIds = array_diff($supplierIds, $suppliersWithBillsThisMonth);

        if (empty($pendingSupplierIds)) {
            return [];
        }

        return Supplier::whereIn('id', $pendingSupplierIds)
            ->with(['bills' => fn ($query) => $query->latest('bill_date')->limit(1)])
            ->get()
            ->mapWithKeys(fn ($supplier) => [
                $supplier->id => [
                    'model' => $supplier,
                    'status' => $this->record->items['suppliers'][$supplier->id] ?? ['completed' => false, 'bill_id' => null, 'skipped' => false],
                    'last_bill' => $supplier->bills->first(),
                ],
            ])
            ->toArray();
    }

    #[Computed]
    public function incomeSources(): array
    {
        $incomeSourceIds = array_keys($this->record->items['income_sources'] ?? []);

        return IncomeSource::whereIn('id', $incomeSourceIds)
            ->get()
            ->mapWithKeys(fn ($source) => [
                $source->id => [
                    'model' => $source,
                    'status' => $this->record->items['income_sources'][$source->id] ?? ['completed' => false, 'skipped' => false],
                ],
            ])
            ->toArray();
    }

    #[Computed]
    public function pendingInvoices()
    {
        return Invoice::whereNotIn('status', ['paid', 'written_off'])
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    #[Computed]
    public function pendingOtherIncomes()
    {
        return OtherIncome::where('status', 'pending')
            ->orderBy('income_date', 'desc')
            ->get();
    }

    #[Computed]
    public function activeBankAccounts()
    {
        return BankAccount::active()->orderBy('name')->get();
    }

    #[Computed]
    public function recentBillsThisMonth()
    {
        return Bill::whereMonth('bill_date', $this->record->period_month)
            ->whereYear('bill_date', $this->record->period_year)
            ->with('supplier')
            ->orderBy('bill_date', 'desc')
            ->get();
    }

    public function toggleSupplier(int $supplierId): void
    {
        $currentStatus = $this->record->items['suppliers'][$supplierId] ?? ['completed' => false, 'bill_id' => null, 'skipped' => false];

        if ($currentStatus['completed']) {
            $this->record->updateItem('suppliers', $supplierId, ['completed' => false, 'bill_id' => null, 'skipped' => false]);
        } else {
            $this->record->markSupplierCompleted($supplierId);
        }

        unset($this->suppliers);

        Notification::make()
            ->success()
            ->title('Item updated')
            ->send();
    }

    public function skipSupplier(int $supplierId): void
    {
        $this->record->markSupplierSkipped($supplierId);
        unset($this->suppliers);

        Notification::make()
            ->success()
            ->title('Supplier skipped for this month')
            ->send();
    }

    public function toggleIncomeSource(int $incomeSourceId): void
    {
        $currentStatus = $this->record->items['income_sources'][$incomeSourceId] ?? ['completed' => false, 'skipped' => false];

        if ($currentStatus['completed']) {
            $this->record->updateItem('income_sources', $incomeSourceId, ['completed' => false, 'skipped' => false]);
        } else {
            $this->record->markIncomeSourceCompleted($incomeSourceId);
        }

        unset($this->incomeSources);

        Notification::make()
            ->success()
            ->title('Item updated')
            ->send();
    }

    public function skipIncomeSource(int $incomeSourceId): void
    {
        $this->record->markIncomeSourceSkipped($incomeSourceId);
        unset($this->incomeSources);

        Notification::make()
            ->success()
            ->title('Income source skipped for this month')
            ->send();
    }

    public function toggleGeneralItem(string $key): void
    {
        $currentValue = $this->record->items[$key] ?? false;
        $this->record->updateItem($key, '', ! $currentValue);

        Notification::make()
            ->success()
            ->title('Item updated')
            ->send();
    }

    public function disableSupplierRecurring(int $supplierId): void
    {
        $supplier = Supplier::find($supplierId);

        if ($supplier) {
            $supplier->update(['billing_frequency' => 'none', 'billing_month' => null]);

            Notification::make()
                ->success()
                ->title('Recurring billing disabled')
                ->body("Recurring billing for {$supplier->name} has been disabled.")
                ->send();
        }
    }

    public function disableIncomeSourceRecurring(int $incomeSourceId): void
    {
        $incomeSource = IncomeSource::find($incomeSourceId);

        if ($incomeSource) {
            $incomeSource->update(['billing_frequency' => 'none', 'billing_month' => null]);

            Notification::make()
                ->success()
                ->title('Recurring income disabled')
                ->body("Recurring income for {$incomeSource->name} has been disabled.")
                ->send();
        }
    }
}
