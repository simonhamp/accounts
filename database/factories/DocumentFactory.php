<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extensions = ['pdf', 'jpg', 'png', 'xlsx', 'docx', 'txt'];
        $extension = fake()->randomElement($extensions);

        return [
            'person_id' => Person::factory(),
            'file_path' => 'documents/'.fake()->uuid().'.'.$extension,
            'original_filename' => fake()->words(3, true).'.'.$extension,
            'description' => fake()->optional()->sentence(),
            'year' => fake()->numberBetween(2023, (int) date('Y')),
            'month' => fake()->optional()->numberBetween(1, 12),
        ];
    }
}
