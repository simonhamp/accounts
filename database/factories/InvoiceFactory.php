<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'person_id' => Person::factory(),
            'invoice_number' => strtoupper(fake()->lexify('??')).'-'.str_pad(fake()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'invoice_date' => $date,
            'period_month' => (int) $date->format('m'),
            'period_year' => (int) $date->format('Y'),
            'customer_name' => fake()->company(),
            'customer_address' => fake()->address(),
            'customer_tax_id' => fake()->bothify('??######?'),
            'total_amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'EUR',
            'status' => InvoiceStatus::Finalized,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Pending,
            'person_id' => null,
            'invoice_number' => null,
            'invoice_date' => null,
            'period_month' => null,
            'period_year' => null,
            'customer_name' => null,
            'customer_address' => null,
            'total_amount' => null,
        ]);
    }

    public function extracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Extracted,
            'extracted_data' => [
                'all_addresses' => [
                    '123 Main St, City, 12345',
                    '456 Oak Ave, Town, 67890',
                ],
                'items' => [
                    [
                        'description' => 'Service fee',
                        'quantity' => 1,
                        'unit_price' => 10000,
                        'total' => 10000,
                    ],
                ],
            ],
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Reviewed,
            'extracted_data' => [
                'items' => [
                    [
                        'description' => 'Service fee',
                        'quantity' => 1,
                        'unit_price' => 10000,
                        'total' => 10000,
                    ],
                ],
            ],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Failed,
            'error_message' => 'Failed to extract data from PDF',
        ]);
    }

    public function withOriginalFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_file_path' => 'imports/test-invoice.pdf',
        ]);
    }
}
