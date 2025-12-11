<?php

namespace Database\Factories;

use App\Enums\BillingFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IncomeSource>
 */
class IncomeSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'GitHub Sponsors',
                'LemonSqueezy',
                'ShopMy',
                'Bifrost',
                'Production Payslip',
                'Consulting',
                'Affiliate Income',
            ]),
            'description' => fake()->optional()->sentence(),
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
