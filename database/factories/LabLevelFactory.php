<?php

namespace Database\Factories;

use App\Models\LabLevel;
use App\Models\LabShelf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabLevel>
 */
class LabLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_shelf_id' => LabShelf::factory(),
            'number' => 1,
        ];
    }
}
