<?php

namespace Database\Factories;

use App\Models\StripeAccount;
use App\Models\StripeTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripeTransaction>
 */
class StripeTransactionFactory extends Factory
{
    protected $model = StripeTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stripe_account_id' => StripeAccount::factory(),
            'stripe_transaction_id' => 'ch_'.fake()->sha256(),
            'type' => fake()->randomElement(['payment', 'refund', 'chargeback', 'fee']),
            'amount' => fake()->numberBetween(1000, 50000),
            'currency' => 'EUR',
            'customer_name' => fake()->company(),
            'customer_email' => fake()->companyEmail(),
            'description' => fake()->sentence(),
            'status' => 'ready',
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
