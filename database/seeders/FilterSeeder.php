<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\Lab;
use App\Models\Sample;
use App\Support\ObservationWeek;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rebuild every lab's filter placements and weekly readings.
 *
 * This is destructive: it removes all existing filters and readings before
 * regenerating them for every lab with samples. It is intentionally explicit,
 * like DemoStressSeeder, and is not called by DatabaseSeeder.
 */
class FilterSeeder extends Seeder
{
    private const RANDOM_SEED = 20261116;

    private const READING_PROBABILITY = 70;

    private const MIN_VALUE = 0;

    private const MAX_VALUE = 100;

    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);

        DB::table('filter_observations')->delete();
        DB::table('filters')->delete();

        $filters = 0;
        $readings = 0;

        foreach (Lab::query()->with('organization')->orderBy('id')->get() as $lab) {
            [$labFilters, $labReadings] = DB::transaction(fn (): array => $this->seedLab($lab));

            $filters += $labFilters;
            $readings += $labReadings;
        }

        $this->command?->info(sprintf('Seeded %d filters and %d filter readings.', $filters, $readings));
    }

    /**
     * @return array{int, int}
     */
    private function seedLab(Lab $lab): array
    {
        $sampleIds = $this->sampleIds($lab);

        if ($sampleIds === []) {
            return [0, 0];
        }

        $timezone = $lab->organization->timezone;
        $lastWeekStart = CarbonImmutable::now($timezone)
            ->startOfWeek(CarbonInterface::MONDAY)
            ->subWeek();
        $creationWeek = $lab->created_at
            ?->toImmutable()
            ->setTimezone($timezone)
            ->startOfWeek(CarbonInterface::MONDAY);
        $firstWeekStart = ($creationWeek ?? $lastWeekStart)->addWeek();

        if ($firstWeekStart->greaterThan($lastWeekStart)) {
            return [0, 0];
        }

        $interval = max(1, $lab->filter_interval);
        $now = now();
        $filterCount = 0;
        $readingRows = [];

        foreach ($sampleIds as $index => $sampleId) {
            if (($index + 1) % $interval !== 0) {
                continue;
            }

            $placementWeek = $firstWeekStart->addWeeks(
                mt_rand(0, (int) $firstWeekStart->diffInWeeks($lastWeekStart)),
            );
            $placedAt = $placementWeek
                ->addDays(mt_rand(0, 6))
                ->addHours(mt_rand(0, 23))
                ->addMinutes(mt_rand(0, 59))
                ->addSeconds(mt_rand(0, 59));

            $placedWeek = ObservationWeek::for($placedAt, $timezone);

            $filter = Filter::create([
                'lab_id' => $lab->id,
                'sample_id' => $sampleId,
                'placed_at' => $placedAt,
                'iso_year' => $placedWeek->isoYear,
                'iso_week' => $placedWeek->isoWeek,
                'week_start' => $placedWeek->startDate,
            ]);

            $filterCount++;

            for ($week = $placementWeek; $week->lessThanOrEqualTo($lastWeekStart); $week = $week->addWeek()) {
                if (mt_rand(1, 100) > self::READING_PROBABILITY) {
                    continue;
                }

                $weekEnd = $week->addWeek();
                $earliest = $week->equalTo($placementWeek) ? $placedAt : $week->startOfDay();

                if ($earliest->greaterThanOrEqualTo($weekEnd)) {
                    continue;
                }

                $observedAt = $earliest->addSeconds(
                    mt_rand(0, max(0, $weekEnd->getTimestamp() - $earliest->getTimestamp() - 1)),
                );

                $readingRows[] = [
                    'filter_id' => $filter->id,
                    'observed_at' => $observedAt->utc()->toDateTimeString(),
                    'week_start' => ObservationWeek::for($week, $timezone)->startDate,
                    'value' => mt_rand(self::MIN_VALUE, self::MAX_VALUE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($readingRows, 1000) as $chunk) {
            DB::table('filter_observations')->insert($chunk);
        }

        return [$filterCount, count($readingRows)];
    }

    /**
     * @return array<int, int>
     */
    private function sampleIds(Lab $lab): array
    {
        return Sample::query()
            ->join('lab_levels as l', 'l.id', '=', 'samples.lab_level_id')
            ->join('lab_shelves as sh', 'sh.id', '=', 'l.lab_shelf_id')
            ->join('lab_rows as r', 'r.id', '=', 'sh.lab_row_id')
            ->where('samples.lab_id', $lab->id)
            ->orderBy('r.number')
            ->orderBy('sh.number')
            ->orderBy('l.number')
            ->orderBy('samples.code')
            ->pluck('samples.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
