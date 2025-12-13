<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\StripeAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StripeAccount>
 */
class StripeAccountFactory extends Factory
{
    protected $model = StripeAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'account_name' => fake()->company().' Stripe',
            'api_key' => 'sk_test_'.fake()->sha256(),
        ];
    }
}
