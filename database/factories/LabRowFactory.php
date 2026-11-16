<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\LabRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabRow>
 */
class LabRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lab_id' => Lab::factory(),
            'number' => 1,
        ];
    }
}
