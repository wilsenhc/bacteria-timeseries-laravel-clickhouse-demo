<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\LabLevel;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sample>
 */
class SampleFactory extends Factory
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
            'lab_level_id' => LabLevel::factory(),
            'code' => sprintf('R1-S1-L1-%02d', fake()->unique()->numberBetween(1, 99)),
        ];
    }
}
