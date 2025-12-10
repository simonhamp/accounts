<?php

namespace Database\Factories;

use App\Enums\InvoiceItemUnit;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BillItem>
 */
class BillItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 0.5, 10);
        $unitPrice = fake()->numberBetween(1000, 50000);

        return [
            'bill_id' => Bill::factory(),
            'description' => fake()->sentence(3),
            'unit' => fake()->randomElement(InvoiceItemUnit::cases()),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => (int) round($quantity * $unitPrice),
        ];
    }
}
