<?php

namespace Database\Factories;

use App\Models\Filter;
use App\Models\Lab;
use App\Models\Sample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filter>
 */
class FilterFactory extends Factory
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
            'sample_id' => Sample::factory(),
            'placed_at' => fake()->dateTimeBetween('-26 weeks', '-1 week'),
        ];
    }

    public function forSample(Sample $sample): static
    {
        return $this->state(fn (): array => [
            'lab_id' => $sample->lab_id,
            'sample_id' => $sample->id,
        ]);
    }
}
