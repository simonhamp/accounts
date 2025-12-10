<?php

namespace Database\Factories;

use App\Enums\BillingFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tax_id' => fake()->optional(0.7)->regexify('[A-Z][0-9]{8}'),
            'address' => fake()->optional(0.8)->address(),
            'email' => fake()->optional(0.6)->companyEmail(),
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
            'billing_frequency' => BillingFrequency::None,
            'billing_month' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'billing_frequency' => BillingFrequency::Monthly,
            'billing_month' => null,
        ]);
    }

    public function annual(?int $month = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'billing_frequency' => BillingFrequency::Annual,
            'billing_month' => $month ?? fake()->numberBetween(1, 12),
        ]);
    }

    public function noBilling(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_frequency' => BillingFrequency::None,
            'billing_month' => null,
        ]);
    }
}
