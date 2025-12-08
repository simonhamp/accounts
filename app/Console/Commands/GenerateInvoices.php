<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\InvoiceService;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate {year} {month} {--person= : Generate for specific person ID}';

    protected $description = 'Generate invoices from Stripe transactions for a given month and year';

    public function handle(InvoiceService $invoiceService): int
    {
        $year = (int) $this->argument('year');
        $month = (int) $this->argument('month');

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12');

            return self::FAILURE;
        }

        $people = $this->option('person')
            ? Person::query()->where('id', $this->option('person'))->get()
            : Person::all();

        if ($people->isEmpty()) {
            $this->error('No people found. Please add person records in the admin panel.');

            return self::FAILURE;
        }

        $this->info("Generating invoices for {$year}/{$month}...");
        $this->newLine();

        $totalGenerated = 0;

        foreach ($people as $person) {
            $this->line("Processing: {$person->name}");

            $validation = $invoiceService->validatePeriod($person, $year, $month);

            if (! $validation['valid']) {
                $this->warn("  ⚠ Skipping: {$validation['incomplete_transactions']} incomplete transaction(s)");
                $this->warn("  Transaction IDs: ".implode(', ', $validation['incomplete_ids']));

                continue;
            }

            if ($validation['total_transactions'] === 0) {
                $this->line('  → No transactions found for this period');

                continue;
            }

            try {
                $result = $invoiceService->generateInvoices($person, $year, $month);

                if ($result['generated'] === 0) {
                    $this->line('  → No ready transactions to invoice');
                } else {
                    $this->info("  ✓ Generated {$result['generated']} invoice(s)");
                    $totalGenerated += $result['generated'];
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("✓ Total invoices generated: {$totalGenerated}");

        return self::SUCCESS;
    }
}
