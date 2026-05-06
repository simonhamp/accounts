<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'entity_type' => \App\Enums\EntityType::Individual,
            'dni_nie' => fake()->bothify('??######?'),
            'cif' => null,
            'registro_mercantil' => null,
            'tax_regime' => \App\Enums\TaxRegime::PeninsulaBaleares,
            'invoice_prefix' => strtoupper(fake()->unique()->lexify('???')),
            'next_invoice_number' => 1,
        ];
    }

    public function sociedadLimitada(): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => \App\Enums\EntityType::SociedadLimitada,
            'dni_nie' => null,
            'cif' => 'B'.fake()->numerify('########'),
            'registro_mercantil' => 'Inscrita en el Registro Mercantil de '.fake()->city().', Tomo 1, Folio 1, Hoja 1',
        ]);
    }

    public function canarias(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_regime' => \App\Enums\TaxRegime::Canarias,
        ]);
    }
}
