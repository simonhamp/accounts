<?php

namespace Database\Factories;

use App\Enums\OtherIncomeStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtherIncome>
 */
class OtherIncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => \App\Models\Person::factory(),
            'income_source_id' => \App\Models\IncomeSource::factory(),
            'income_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => fake()->sentence(),
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => fake()->randomElement(['EUR', 'USD', 'GBP']),
            'status' => OtherIncomeStatus::Pending,
            'reference' => fake()->optional()->uuid(),
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public function paid(?int $amountPaid = null): static
    {
        return $this->afterCreating(function (\App\Models\OtherIncome $income) use ($amountPaid) {
            $income->update([
                'status' => OtherIncomeStatus::Paid,
                'amount_paid' => $amountPaid ?? $income->amount,
                'paid_at' => fake()->dateTimeBetween($income->income_date, 'now'),
            ]);
        });
    }
}
