<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('LAB-###')),
            'notes' => fake()->sentence(),
            'row_count' => 2,
            'shelves_per_row' => 2,
            'levels_per_shelf' => 2,
        ];
    }
}
