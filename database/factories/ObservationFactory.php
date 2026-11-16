<?php

namespace Database\Factories;

use App\Models\Observation;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $observedAt = fake()->dateTimeBetween('-26 weeks', 'now');

        return [
            'sample_id' => Sample::factory(),
            'observed_at' => $observedAt,
            'iso_year' => (int) $observedAt->format('o'),
            'iso_week' => (int) $observedAt->format('W'),
        ];
    }
}
