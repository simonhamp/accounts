<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
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
            'email' => fake()->optional(0.7)->companyEmail(),
            'address' => fake()->optional(0.6)->address(),
            'tax_id' => fake()->optional(0.7)->bothify('??########'),
            'country_code' => fake()->optional(0.7)->randomElement(['ES', 'GB', 'US', 'FR', 'DE']),
            'tax_region' => null,
        ];
    }

    public function canarias(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => 'ES',
            'tax_region' => \App\Enums\CustomerTaxRegion::Canarias,
        ]);
    }

    public function peninsulaSpain(): static
    {
        return $this->state(fn (array $attributes) => [
            'country_code' => 'ES',
            'tax_region' => \App\Enums\CustomerTaxRegion::PeninsulaBaleares,
        ]);
    }
}
