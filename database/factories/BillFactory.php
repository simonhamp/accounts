<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Person;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bill>
 */
class BillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $billDate = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'supplier_id' => Supplier::factory(),
            'person_id' => Person::factory(),
            'bill_number' => fake()->unique()->numerify('INV-####'),
            'bill_date' => $billDate,
            'due_date' => fake()->optional(0.8)->dateTimeBetween($billDate, '+30 days'),
            'total_amount' => fake()->numberBetween(1000, 1000000),
            'currency' => fake()->randomElement(['EUR', 'USD', 'GBP']),
            'status' => BillStatus::Pending,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Pending,
        ]);
    }

    public function extracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Extracted,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Reviewed,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Paid,
        ]);
    }

    public function paidNeedsReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::PaidNeedsReview,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BillStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
