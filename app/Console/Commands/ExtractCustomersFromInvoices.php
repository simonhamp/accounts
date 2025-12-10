<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Console\Command;

class ExtractCustomersFromInvoices extends Command
{
    protected $signature = 'customers:extract';

    protected $description = 'Extract unique customers from existing invoices and link them';

    public function handle(): int
    {
        $this->info('Extracting customers from invoices...');

        // Get unique customer names from invoices without a customer_id
        $customerNames = Invoice::query()
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->distinct()
            ->pluck('customer_name');

        if ($customerNames->isEmpty()) {
            $this->info('No new customers to extract.');

            return self::SUCCESS;
        }

        $this->info("Found {$customerNames->count()} unique customer names.");

        $bar = $this->output->createProgressBar($customerNames->count());
        $bar->start();

        $created = 0;
        $linked = 0;

        foreach ($customerNames as $customerName) {
            // Check if customer already exists
            $customer = Customer::firstOrCreate(
                ['name' => $customerName],
                ['name' => $customerName]
            );

            if ($customer->wasRecentlyCreated) {
                $created++;
            }

            // Link all invoices with this customer name to this customer
            $updatedCount = Invoice::query()
                ->whereNull('customer_id')
                ->where('customer_name', $customerName)
                ->update(['customer_id' => $customer->id]);

            $linked += $updatedCount;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Created {$created} new customers.");
        $this->info("Linked {$linked} invoices to customers.");

        return self::SUCCESS;
    }
}
