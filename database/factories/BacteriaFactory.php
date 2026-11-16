<?php

namespace Database\Factories;

use App\Models\Bacteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bacteria>
 */
class BacteriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'short_name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'icon' => fake()->randomElement([
                'rod',
                'coccus',
                'spiral',
                'comma',
                'cluster',
                'chain',
                'spore',
                'flagellum',
            ]),
            'observation_type' => fake()->randomElement(['level', 'count']),
        ];
    }
}
