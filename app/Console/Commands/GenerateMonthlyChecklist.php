<?php

namespace App\Console\Commands;

use App\Models\IncomeSource;
use App\Models\MonthlyChecklist;
use App\Models\Supplier;
use Illuminate\Console\Command;

class GenerateMonthlyChecklist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checklist:generate
                            {--month= : The month to generate for (1-12, defaults to current month)}
                            {--year= : The year to generate for (defaults to current year)}
                            {--force : Force regeneration even if checklist already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a monthly checklist for tracking bills, income, and other tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $month = (int) ($this->option('month') ?? now()->month);
        $year = (int) ($this->option('year') ?? now()->year);

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');

            return self::FAILURE;
        }

        $existing = MonthlyChecklist::forMonth($month, $year)->first();

        if ($existing && ! $this->option('force')) {
            $this->info("Checklist for {$existing->period_name} already exists.");

            return self::SUCCESS;
        }

        $checklist = $existing ?? MonthlyChecklist::create([
            'period_month' => $month,
            'period_year' => $year,
            'items' => MonthlyChecklist::defaultItems(),
        ]);

        $this->populateChecklist($checklist, $month);

        $this->info("Generated checklist for {$checklist->period_name}");
        $this->table(
            ['Item Type', 'Count'],
            [
                ['Suppliers', count($checklist->items['suppliers'] ?? [])],
                ['Income Sources', count($checklist->items['income_sources'] ?? [])],
                ['General Items', 3],
            ]
        );

        return self::SUCCESS;
    }

    protected function populateChecklist(MonthlyChecklist $checklist, int $month): void
    {
        $items = MonthlyChecklist::defaultItems();

        // Add suppliers expecting bills this month
        $suppliers = Supplier::expectingBillInMonth($month)->pluck('id');
        foreach ($suppliers as $supplierId) {
            $items['suppliers'][$supplierId] = ['completed' => false, 'bill_id' => null, 'skipped' => false];
        }

        // Add income sources expecting income this month
        $incomeSources = IncomeSource::expectingIncomeInMonth($month)->pluck('id');
        foreach ($incomeSources as $incomeSourceId) {
            $items['income_sources'][$incomeSourceId] = ['completed' => false, 'skipped' => false];
        }

        $checklist->update(['items' => $items]);
    }
}
