<?php

namespace Database\Factories;

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
        ];
    }
}
