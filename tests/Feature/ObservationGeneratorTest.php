<?php

use App\Models\Bacteria;
use App\Models\Observation;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Database\Seeders\ObservationGenerator;

test('the observation generator creates valid bacteria result shapes', function () {
    mt_srand(1);

    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 1);
    $levelBacteria = Bacteria::factory()->create(['observation_type' => 'level']);
    $countBacteria = Bacteria::factory()->create(['observation_type' => 'count']);
    $generator = new ObservationGenerator(collect([$levelBacteria]), collect([$countBacteria]));
    $weeks = [
        CarbonImmutable::parse('2026-11-02', 'UTC'),
        CarbonImmutable::parse('2026-11-09', 'UTC'),
    ];

    for ($index = 0; $index < 5; $index++) {
        $sample = createSample($lab, sprintf('R1-S1-L1-%02d', $index + 1));
        $generator->generateForSample($sample->id, $weeks, 'UTC');
    }

    $generator->flush();

    $observations = Observation::query()->with('bacteriaObservations')->get();

    expect($observations)->not->toBeEmpty();

    foreach ($observations as $observation) {
        expect($observation->bacteriaObservations)->not->toBeEmpty();

        foreach ($observation->bacteriaObservations as $result) {
            expect(($result->growth_level !== null) xor ($result->count !== null))->toBeTrue()
                ->and($result->sample_id)->toBe($observation->sample_id)
                ->and($result->observed_at?->equalTo($observation->observed_at))->toBeTrue();
        }
    }
});
