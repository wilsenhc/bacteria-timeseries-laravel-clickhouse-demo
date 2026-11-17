<?php

namespace App\Console\Commands;

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Lab;
use App\Support\LabMap\ClickHouseLabMapEngine;
use App\Support\LabMap\ClickHouseSnapshotLabMapEngine;
use App\Support\LabMap\LabMapEngineContract;
use App\Support\LabMap\PostgresLabMapEngine;
use App\Support\LabMapQuery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('ch:bench {--lab= : Lab id; defaults to the largest lab} {--week= : ISO week, for example 2026-W47} {--iterations=5} {--scale=1 : 1 for the loaded data, 5 for synthetic per-lab 5x tables}')]
#[Description('Benchmark the PostgreSQL and ClickHouse lab map engines')]
class BenchmarkLabMapCommand extends Command
{
    public function handle(LabMapQuery $mapQuery): int
    {
        $iterations = max(1, (int) $this->option('iterations'));
        $scale = (int) $this->option('scale');

        if (! in_array($scale, [1, 5], true)) {
            $this->components->error('The scale must be 1 or 5.');

            return self::FAILURE;
        }

        $lab = Lab::query()
            ->withCount('samples')
            ->when($this->option('lab'), fn ($query, $id) => $query->whereKey((int) $id))
            ->orderByDesc('samples_count')
            ->first();

        if ($lab === null) {
            $this->components->error('No lab found.');

            return self::FAILURE;
        }

        $weekParam = $this->option('week');
        $week = $mapQuery->selectedWeek($mapQuery->weekOptions($lab), is_string($weekParam) ? $weekParam : null);
        $weekEnd = $mapQuery->weekEnd($lab, $week);

        $this->components->info(sprintf(
            'Benchmarking %s (%d samples), week %s, scale %s, %d iteration(s).',
            $lab->name,
            $lab->samples_count,
            $week['value'],
            $scale === 1 ? '1x' : '5x synthetic',
            $iterations,
        ));

        $clickhouse = DB::connection('clickhouse');
        $bench = null;

        try {
            $bench = $scale === 5 ? $this->buildScaledTables($clickhouse, $lab) : null;
            $engines = $this->engines($scale, $bench);
            $rows = [];

            foreach ($engines as $name => $engine) {
                $rows[] = $this->measure($name, $engine, $lab, $weekEnd, $iterations, $scale);
            }

            $this->table(
                ['Engine', 'Scale', 'Summaries', 'Min ms', 'Median ms', 'p95 ms', 'Max ms'],
                $rows,
            );

            $this->line('Engine query only. The map page adds hierarchy hydration, payload serialization and HTTP overhead.');
            $this->line('Scale 5 duplicates the selected lab five times with shifted sample ids; it does not re-seed PostgreSQL.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error('Benchmark failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($bench !== null) {
                foreach ([$bench['summary'], $bench['raw'], $bench['filterObservations'], $bench['filters']] as $table) {
                    $clickhouse->statement('DROP TABLE IF EXISTS '.$table);
                }
            }
        }
    }

    /**
     * @return array{raw: string, summary: string, filters: string, filterObservations: string}
     */
    private function buildScaledTables(Connection $connection, Lab $lab): array
    {
        $raw = 'lab_observations_bench';
        $summary = 'lab_weekly_summaries_bench';
        $filters = 'lab_filters_bench';
        $filterObservations = 'lab_filter_observations_bench';

        foreach ([$summary, $raw, $filterObservations, $filters] as $table) {
            $connection->statement('DROP TABLE IF EXISTS '.$table);
        }

        $connection->statement('CREATE TABLE '.$raw.' AS '.LabObservation::TABLE);
        $connection->statement('CREATE TABLE '.$summary.' AS '.LabWeeklySummary::TABLE);
        $connection->statement('CREATE TABLE '.$filters.' AS '.LabFilter::TABLE);
        $connection->statement('CREATE TABLE '.$filterObservations.' AS '.LabFilterObservation::TABLE);

        for ($copy = 0; $copy < 5; $copy++) {
            $offset = $copy * 1_000_000;

            $connection->statement(sprintf(
                'INSERT INTO %s SELECT lab_id, sample_id + %d, bacteria_id, observed_at, iso_year, iso_week, week_start, growth_level, `count`, bacteria_name, bacteria_short_name, bacteria_color, bacteria_icon, observation_type FROM %s WHERE lab_id = ?',
                $raw,
                $offset,
                LabObservation::TABLE,
            ), [$lab->id]);

            $connection->statement(sprintf(
                'INSERT INTO %s SELECT lab_id, week_start, sample_id + %d, present_count, level_count, count_count, highest_level, max_count, single_id, single_observation_type, single_growth_level, single_count, if(filter_id = 0, 0, filter_id + %d), filter_value FROM %s WHERE lab_id = ?',
                $summary,
                $offset,
                $offset,
                LabWeeklySummary::TABLE,
            ), [$lab->id]);

            $connection->statement(sprintf(
                'INSERT INTO %s SELECT filter_id + %d, lab_id, sample_id + %d, placed_at, placed_week, placed_iso_year, placed_iso_week FROM %s WHERE lab_id = ?',
                $filters,
                $offset,
                $offset,
                LabFilter::TABLE,
            ), [$lab->id]);

            $connection->statement(sprintf(
                'INSERT INTO %s SELECT filter_id + %d, lab_id, sample_id + %d, observed_at, week_start, value FROM %s WHERE lab_id = ?',
                $filterObservations,
                $offset,
                $offset,
                LabFilterObservation::TABLE,
            ), [$lab->id]);
        }

        return compact('raw', 'summary', 'filters', 'filterObservations');
    }

    /**
     * @param  array{raw: string, summary: string, filters: string, filterObservations: string}|null  $bench
     * @return array<string, LabMapEngineContract>
     */
    private function engines(int $scale, ?array $bench): array
    {
        if ($scale === 1) {
            return [
                'PostgreSQL' => app(PostgresLabMapEngine::class),
                'ClickHouse raw' => app(ClickHouseLabMapEngine::class),
                'ClickHouse snapshot' => app(ClickHouseSnapshotLabMapEngine::class),
            ];
        }

        $raw = new ClickHouseLabMapEngine($bench['raw'], $bench['filters'], $bench['filterObservations']);

        return [
            'ClickHouse raw' => $raw,
            'ClickHouse snapshot' => new ClickHouseSnapshotLabMapEngine($bench['summary'], $raw),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}
     */
    private function measure(
        string $name,
        LabMapEngineContract $engine,
        Lab $lab,
        CarbonImmutable $weekEnd,
        int $iterations,
        int $scale,
    ): array {
        try {
            $summaries = $engine->summaries($lab, $weekEnd);
            $times = [];

            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                $startedAt = hrtime(true);
                $summaries = $engine->summaries($lab, $weekEnd);
                $times[] = (hrtime(true) - $startedAt) / 1_000_000;
            }
        } catch (Throwable $exception) {
            return [$name, $scale.'x', 'error', '-', '-', '-', '-'];
        }

        if ($times === []) {
            return [$name, $scale.'x', 'error', '-', '-', '-', '-'];
        }

        sort($times);
        $count = count($times);
        $median = $times[intdiv($count, 2)];
        $p95 = $times[min($count - 1, (int) ceil($count * 0.95) - 1)];

        return [
            $name,
            $scale.'x',
            number_format(count($summaries)),
            number_format(min($times), 1),
            number_format($median, 1),
            number_format($p95, 1),
            number_format(max($times), 1),
        ];
    }
}
