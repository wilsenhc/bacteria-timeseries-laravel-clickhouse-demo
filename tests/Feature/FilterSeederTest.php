<?php

use App\Models\Filter;
use App\Models\FilterObservation;
use App\Models\Lab;
use App\Models\Organization;
use App\Models\Sample;
use App\Support\ObservationWeek;
use Carbon\CarbonImmutable;
use Database\Seeders\FilterSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function createSamplesOnEveryLevel(Lab $lab): Collection
{
    return $lab->rows()->first()->shelves()->first()->levels()
        ->orderBy('number')
        ->get()
        ->values()
        ->map(fn ($level, int $index): Sample => Sample::create([
            'lab_id' => $lab->id,
            'lab_level_id' => $level->id,
            'code' => sprintf('R1-S1-L1-%02d', $index + 1),
        ]));
}

it('places one filter per interval and fills weekly readings', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 4);
    $lab->forceFill([
        'filter_interval' => 2,
        'created_at' => CarbonImmutable::now()->subWeeks(6),
    ])->saveQuietly();

    $samples = createSamplesOnEveryLevel($lab);

    app(FilterSeeder::class)->run();

    $filters = Filter::query()->orderBy('sample_id')->get();

    expect($filters)->toHaveCount(2)
        ->and($filters->pluck('sample_id')->all())
        ->toBe([$samples[1]->id, $samples[3]->id]);

    $firstWeek = '2026-10-05';
    $lastWeek = '2026-11-02';

    foreach ($filters as $filter) {
        $week = ObservationWeek::for($filter->placed_at, 'UTC');

        expect($filter->iso_year)->toBe($week->isoYear)
            ->and($filter->iso_week)->toBe($week->isoWeek)
            ->and($filter->week_start)->toBe($week->startDate)
            ->and($filter->placed_at->toDateString())->toBeGreaterThanOrEqual($firstWeek)
            ->and($filter->placed_at->toDateString())->toBeLessThan('2026-11-09')
            ->and($filter->week_start)->toBeGreaterThanOrEqual($firstWeek)
            ->and($filter->week_start)->toBeLessThanOrEqual($lastWeek);
    }

    $readings = FilterObservation::query()->get();

    expect($readings)->not->toBeEmpty();

    foreach ($readings as $reading) {
        $filter = $filters->firstWhere('id', $reading->filter_id);

        expect($reading->value)->toBeGreaterThanOrEqual(0)
            ->and($reading->value)->toBeLessThanOrEqual(100)
            ->and($reading->week_start)->toBe(ObservationWeek::for($reading->observed_at, 'UTC')->startDate)
            ->and($reading->observed_at->greaterThanOrEqualTo($filter->placed_at))->toBeTrue()
            ->and($reading->observed_at->lessThan(CarbonImmutable::parse($reading->week_start)->addWeek()))->toBeTrue();
    }

    $duplicateWeeks = $readings
        ->groupBy(fn (FilterObservation $reading): string => $reading->filter_id.'|'.$reading->week_start)
        ->filter(fn (Collection $group): bool => $group->count() > 1);

    expect($duplicateWeeks)->toBeEmpty();
});

it('rebuilds the same dataset when run twice', function () {
    $organization = Organization::factory()->create(['timezone' => 'UTC']);
    $lab = createLabWithHierarchy($organization, 1, 1, 3);
    $lab->forceFill([
        'filter_interval' => 1,
        'created_at' => CarbonImmutable::now()->subWeeks(6),
    ])->saveQuietly();

    createSamplesOnEveryLevel($lab);

    app(FilterSeeder::class)->run();

    $firstFilters = Filter::query()->orderBy('sample_id')->pluck('sample_id')->all();
    $firstReadings = FilterObservation::query()->count();

    app(FilterSeeder::class)->run();

    expect(Filter::query()->orderBy('sample_id')->pluck('sample_id')->all())->toBe($firstFilters)
        ->and(FilterObservation::query()->count())->toBe($firstReadings)
        ->and(Filter::query()->count())->toBe(3);
});
