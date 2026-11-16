<?php

namespace Database\Factories;

use App\Enums\GrowthLevel;
use App\Models\Bacteria;
use App\Models\BacteriaObservation;
use App\Models\Observation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacteriaObservation>
 */
class BacteriaObservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observation_id' => Observation::factory(),
            'bacteria_id' => Bacteria::factory(),
            'growth_level' => GrowthLevel::Low,
            'count' => null,
        ];
    }

    public function level(GrowthLevel $level = GrowthLevel::Low): static
    {
        return $this->state(fn (): array => [
            'growth_level' => $level,
            'count' => null,
        ]);
    }

    public function withCount(int $count): static
    {
        return $this->state(fn (): array => [
            'growth_level' => null,
            'count' => $count,
        ]);
    }
}
