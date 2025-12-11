<?php

namespace Database\Factories;

use App\Models\MonthlyChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonthlyChecklist>
 */
class MonthlyChecklistFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_month' => fake()->numberBetween(1, 12),
            'period_year' => fake()->numberBetween(2024, 2026),
            'items' => MonthlyChecklist::defaultItems(),
            'completed_at' => null,
        ];
    }

    public function forMonth(int $month, int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'period_month' => $month,
            'period_year' => $year,
        ]);
    }

    public function currentMonth(): static
    {
        return $this->forMonth(now()->month, now()->year);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
        ]);
    }

    public function withSuppliers(array $supplierIds): static
    {
        return $this->state(function (array $attributes) use ($supplierIds) {
            $items = $attributes['items'] ?? MonthlyChecklist::defaultItems();
            foreach ($supplierIds as $supplierId) {
                $items['suppliers'][$supplierId] = ['completed' => false, 'bill_id' => null, 'skipped' => false];
            }

            return ['items' => $items];
        });
    }

    public function withIncomeSources(array $incomeSourceIds): static
    {
        return $this->state(function (array $attributes) use ($incomeSourceIds) {
            $items = $attributes['items'] ?? MonthlyChecklist::defaultItems();
            foreach ($incomeSourceIds as $incomeSourceId) {
                $items['income_sources'][$incomeSourceId] = ['completed' => false, 'skipped' => false];
            }

            return ['items' => $items];
        });
    }
}
