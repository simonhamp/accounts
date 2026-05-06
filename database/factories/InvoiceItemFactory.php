<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(4, 0.5, 10);
        $unitPrice = fake()->numberBetween(500, 50000);

        return [
            'invoice_id' => Invoice::factory(),
            'stripe_transaction_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => (int) round($quantity * $unitPrice),
            'tax_type' => null,
            'tax_rate' => 0,
            'tax_amount' => 0,
        ];
    }

    public function withIva(float $rate = 21): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_type' => \App\Enums\TaxType::Iva,
            'tax_rate' => $rate,
            'tax_amount' => (int) round(($attributes['total'] ?? 0) * $rate / 100),
        ]);
    }

    public function withIgic(float $rate = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_type' => \App\Enums\TaxType::Igic,
            'tax_rate' => $rate,
            'tax_amount' => (int) round(($attributes['total'] ?? 0) * $rate / 100),
        ]);
    }
}
