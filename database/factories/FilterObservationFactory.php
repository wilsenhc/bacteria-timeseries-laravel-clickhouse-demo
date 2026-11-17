<?php

namespace Database\Factories;

use App\Models\Filter;
use App\Models\FilterObservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterObservation>
 */
class FilterObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'filter_id' => Filter::factory(),
            'observed_at' => fake()->dateTimeBetween('-26 weeks', 'now'),
            'value' => fake()->numberBetween(0, 100),
        ];
    }
}
