<?php

namespace Database\Factories;

use App\Models\LabRow;
use App\Models\LabShelf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabShelf>
 */
class LabShelfFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_row_id' => LabRow::factory(),
            'number' => 1,
        ];
    }
}
