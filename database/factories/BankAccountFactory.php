<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Account',
            'bank_name' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'sort_code' => fake()->numerify('##-##-##'),
            'iban' => fake()->iban(),
            'swift_bic' => fake()->swiftBicNumber(),
            'currency' => fake()->randomElement(['EUR', 'USD', 'GBP']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
