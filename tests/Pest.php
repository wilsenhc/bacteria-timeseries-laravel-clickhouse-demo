<?php

use App\Actions\CreateLab;
use App\Enums\GrowthLevel;
use App\Models\Bacteria;
use App\Models\Filter;
use App\Models\Lab;
use App\Models\Observation;
use App\Models\Organization;
use App\Models\Sample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createLabWithHierarchy(
    Organization $organization,
    int $rows = 2,
    int $shelves = 2,
    int $levels = 2,
): Lab {
    return app(CreateLab::class)->handle($organization, [
        'name' => fake()->unique()->words(2, true),
        'code' => null,
        'notes' => null,
        'row_count' => $rows,
        'shelves_per_row' => $shelves,
        'levels_per_shelf' => $levels,
    ]);
}

function createSample(Lab $lab, string $code = 'R1-S1-L1-01'): Sample
{
    $level = $lab->rows()->first()->shelves()->first()->levels()->first();

    return Sample::create([
        'lab_id' => $lab->id,
        'lab_level_id' => $level->id,
        'code' => $code,
    ]);
}

function createObservation(
    Sample $sample,
    string $observedAt,
    Bacteria $bacteria,
    GrowthLevel|int $value,
): Observation {
    $observation = Observation::create([
        'sample_id' => $sample->id,
        'observed_at' => $observedAt,
    ]);

    $observation->bacteriaObservations()->create([
        'bacteria_id' => $bacteria->id,
        'growth_level' => $value instanceof GrowthLevel ? $value : null,
        'count' => $value instanceof GrowthLevel ? null : $value,
    ]);

    return $observation;
}

/**
 * @param  array<int, array{sample: Sample, bacteria: int, observed_at: string, iso_week: int, week_start: string, growth_level: string, count: int, name: string, short_name: string, observation_type: string}>  $states
 */
function insertClickHouseStates(string $table, Lab $lab, array $states): void
{
    $values = array_map(fn (array $state): string => sprintf(
        "(%d, %d, %d, '%s', 2026, %d, '%s', '%s', %d, '%s', '%s', '#123456', 'rod', '%s')",
        $lab->id,
        $state['sample']->id,
        $state['bacteria'],
        $state['observed_at'],
        $state['iso_week'],
        $state['week_start'],
        $state['growth_level'],
        $state['count'],
        $state['name'],
        $state['short_name'],
        $state['observation_type'],
    ), $states);

    DB::connection('clickhouse')->statement(sprintf(
        'INSERT INTO %s (lab_id, sample_id, bacteria_id, observed_at, iso_year, iso_week, week_start, growth_level, `count`, bacteria_name, bacteria_short_name, bacteria_color, bacteria_icon, observation_type) VALUES %s',
        $table,
        implode(', ', $values),
    ));
}

/**
 * @param  array<int, array{observed_at: string, week_start: string, value: int}>  $readings
 */
function insertClickHouseFilters(
    string $filterTable,
    string $filterObservationTable,
    Filter $filter,
    array $readings = [],
): void {
    DB::connection('clickhouse')->statement(sprintf(
        "INSERT INTO %s (filter_id, lab_id, sample_id, placed_at, placed_week, placed_iso_year, placed_iso_week) VALUES (%d, %d, %d, '%s', '%s', %d, %d)",
        $filterTable,
        $filter->id,
        $filter->lab_id,
        $filter->sample_id,
        $filter->placed_at->utc()->toDateTimeString(),
        $filter->week_start,
        $filter->iso_year,
        $filter->iso_week,
    ));

    if ($readings === []) {
        return;
    }

    $values = array_map(fn (array $reading): string => sprintf(
        "(%d, %d, %d, '%s', '%s', %d)",
        $filter->id,
        $filter->lab_id,
        $filter->sample_id,
        $reading['observed_at'],
        $reading['week_start'],
        $reading['value'],
    ), $readings);

    DB::connection('clickhouse')->statement(sprintf(
        'INSERT INTO %s (filter_id, lab_id, sample_id, observed_at, week_start, value) VALUES %s',
        $filterObservationTable,
        implode(', ', $values),
    ));
}
