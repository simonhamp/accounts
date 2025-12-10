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

        $created = 0;
        $linked = 0;
        $updated = 0;

        // Step 1: Update existing customers that are missing addresses
        $this->info('Checking existing customers for missing addresses...');

        $customersWithoutAddress = Customer::query()
            ->where(function ($query) {
                $query->whereNull('address')->orWhere('address', '');
            })
            ->get();

        foreach ($customersWithoutAddress as $customer) {
            // Find an address from any linked invoice
            $invoiceWithAddress = Invoice::query()
                ->where('customer_id', $customer->id)
                ->whereNotNull('customer_address')
                ->where('customer_address', '!=', '')
                ->first();

            // Or try matching by name
            if (! $invoiceWithAddress) {
                $invoiceWithAddress = Invoice::query()
                    ->where('customer_name', $customer->name)
                    ->whereNotNull('customer_address')
                    ->where('customer_address', '!=', '')
                    ->first();
            }

            if ($invoiceWithAddress) {
                $customer->update(['address' => $invoiceWithAddress->customer_address]);
                $updated++;
            }
        }

        $this->info("Updated {$updated} existing customers with addresses.");

        // Step 2: Get unique customer names from invoices without a customer_id
        $customerNames = Invoice::query()
            ->whereNull('customer_id')
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->distinct()
            ->pluck('customer_name');

        if ($customerNames->isEmpty()) {
            $this->info('No new customers to extract from unlinked invoices.');

            return self::SUCCESS;
        }

        $this->info("Found {$customerNames->count()} unique customer names from unlinked invoices.");

        $bar = $this->output->createProgressBar($customerNames->count());
        $bar->start();

        foreach ($customerNames as $customerName) {
            // Get the first invoice with an address for this customer
            $invoiceWithAddress = Invoice::query()
                ->where('customer_name', $customerName)
                ->whereNotNull('customer_address')
                ->where('customer_address', '!=', '')
                ->first();

            // Check if customer already exists, create with address if available
            $customer = Customer::firstOrCreate(
                ['name' => $customerName],
                [
                    'name' => $customerName,
                    'address' => $invoiceWithAddress?->customer_address,
                ]
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
