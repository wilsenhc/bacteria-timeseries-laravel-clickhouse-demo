<?php

namespace Database\Seeders;

use App\Enums\GrowthLevel;
use App\Models\Bacteria;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObservationGenerator
{
    private int $observationId = 1;

    private int $bacteriaObservationId = 1;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $observationBuffer = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $bacteriaObservationBuffer = [];

    /**
     * @param  Collection<int, Bacteria>  $levelBacteria
     * @param  Collection<int, Bacteria>  $countBacteria
     */
    public function __construct(
        private readonly Collection $levelBacteria,
        private readonly Collection $countBacteria,
        private readonly int $observationScale = 1,
    ) {}

    /**
     * Generate and buffer observations for one sample across the given weeks.
     *
     * @param  array<int, CarbonImmutable>  $weeks  Monday starts in the organization timezone.
     */
    public function generateForSample(int $sampleId, array $weeks, string $timezone): void
    {
        $timelines = $this->buildTimelines(count($weeks));

        if ($timelines === []) {
            return;
        }

        foreach ($weeks as $weekIndex => $weekStart) {
            $readingTimes = $this->readingTimes($weekStart);

            foreach ($readingTimes as $readingIndex => $readingTime) {
                $results = [];

                foreach ($timelines as $bacteriaId => $timeline) {
                    $state = $timeline['timeline'][$weekIndex] ?? null;

                    if ($state === 'positive') {
                        $results[$bacteriaId] = $timeline['type'] === 'level'
                            ? ['growth_level' => $this->randomLevel()->value, 'count' => null]
                            : ['growth_level' => null, 'count' => $this->randomCount()];

                        continue;
                    }

                    if ($state === 'terminal' && $readingIndex === 0) {
                        $results[$bacteriaId] = $timeline['type'] === 'level'
                            ? ['growth_level' => GrowthLevel::None->value, 'count' => null]
                            : ['growth_level' => null, 'count' => 0];
                    }
                }

                if ($results !== []) {
                    $this->addObservation($sampleId, $readingTime, $timezone, $results);
                }
            }
        }
    }

    /**
     * Flush any buffered observations to the database.
     */
    public function flush(): void
    {
        if ($this->observationBuffer === []) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('observations')->insert($this->observationBuffer);
            DB::table('bacteria_observations')->insert($this->bacteriaObservationBuffer);
        });

        $this->observationBuffer = [];
        $this->bacteriaObservationBuffer = [];
    }

    /**
     * @return array<int, array{type: string, timeline: array<int, string>}>
     */
    private function buildTimelines(int $weekCount): array
    {
        if ($weekCount < 1 || mt_rand(1, 100) <= 10) {
            return [];
        }

        $levelIds = $this->levelBacteria->pluck('id')->all();
        $countIds = $this->countBacteria->pluck('id')->all();

        shuffle($levelIds);
        shuffle($countIds);

        $levelTake = mt_rand(0, count($levelIds));
        $countTake = mt_rand(0, count($countIds));

        if ($levelTake === 0 && $countTake === 0) {
            if (mt_rand(0, 1) === 0) {
                $levelTake = 1;
            } else {
                $countTake = 1;
            }
        }

        $timelines = [];

        foreach (array_slice($levelIds, 0, $levelTake) as $bacteriaId) {
            $timelines[$bacteriaId] = [
                'type' => 'level',
                'timeline' => $this->timeline($weekCount),
            ];
        }

        foreach (array_slice($countIds, 0, $countTake) as $bacteriaId) {
            $timelines[$bacteriaId] = [
                'type' => 'count',
                'timeline' => $this->timeline($weekCount),
            ];
        }

        return $timelines;
    }

    /**
     * Build a sparse week timeline of positive runs and terminal weeks.
     *
     * @return array<int, string>
     */
    private function timeline(int $weekCount): array
    {
        $timeline = [];
        $start = mt_rand(0, max(0, $weekCount - 2));

        while ($start < $weekCount) {
            $length = mt_rand(2, 8);
            $end = min($start + $length - 1, $weekCount - 1);

            for ($week = $start; $week <= $end; $week++) {
                $timeline[$week] = 'positive';
            }

            $terminal = $end + 1;

            if ($terminal < $weekCount) {
                $timeline[$terminal] = 'terminal';
            }

            $next = $terminal + 1 + mt_rand(1, 6);

            if ($next >= $weekCount) {
                break;
            }

            $start = $next;
        }

        return $timeline;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function readingTimes(CarbonImmutable $weekStart): array
    {
        $count = mt_rand(2 * max(1, $this->observationScale), 4 * max(1, $this->observationScale));
        $times = [];

        while (count($times) < $count) {
            $time = $weekStart
                ->addDays(mt_rand(0, 6))
                ->setTime(mt_rand(8, 18), mt_rand(0, 59), mt_rand(0, 59));

            $times[$time->format('Y-m-d H:i:s')] = $time;
        }

        ksort($times);

        return array_values($times);
    }

    /**
     * @param  array<int, array{growth_level: string|null, count: int|null}>  $results
     */
    private function addObservation(int $sampleId, CarbonImmutable $observedAt, string $timezone, array $results): void
    {
        $local = $observedAt->setTimezone($timezone);
        $observationId = $this->observationId++;
        $now = now()->toDateTimeString();

        $this->observationBuffer[] = [
            'id' => $observationId,
            'sample_id' => $sampleId,
            'observed_at' => $observedAt->utc()->format('Y-m-d H:i:sP'),
            'iso_year' => $local->isoWeekYear,
            'iso_week' => $local->isoWeek,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($results as $bacteriaId => $result) {
            $this->bacteriaObservationBuffer[] = [
                'id' => $this->bacteriaObservationId++,
                'observation_id' => $observationId,
                'bacteria_id' => $bacteriaId,
                'growth_level' => $result['growth_level'],
                'count' => $result['count'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($this->observationBuffer) >= 1000) {
            $this->flush();
        }
    }

    private function randomLevel(): GrowthLevel
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 45 => GrowthLevel::Low,
            $roll <= 80 => GrowthLevel::Medium,
            default => GrowthLevel::High,
        };
    }

    private function randomCount(): int
    {
        return (int) round(10 ** (mt_rand(100, 600) / 100));
    }
}
