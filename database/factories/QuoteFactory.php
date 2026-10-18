<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Person;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-3 months', 'now');

        return [
            'person_id' => Person::factory(),
            'quote_number' => strtoupper(fake()->lexify('??')).'-Q-'.str_pad(fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'quote_date' => $date,
            'valid_until' => (clone $date)->modify('+30 days'),
            'customer_name' => fake()->company(),
            'customer_address' => fake()->address(),
            'customer_tax_id' => fake()->bothify('??######?'),
            'total_amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'EUR',
            'status' => QuoteStatus::Draft,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Sent,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Accepted,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Rejected,
        ]);
    }

    public function invoiced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Invoiced,
        ]);
    }
}
