<?php

namespace App\Console\Commands;

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Lab;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('ch:load')]
#[Description('Load the lab observation dataset into ClickHouse from PostgreSQL')]
class LoadLabObservationsCommand extends Command
{
    public function handle(): int
    {
        $postgres = config('database.connections.pgsql');
        $password = (string) $postgres['password'];

        config(['database.connections.clickhouse.timeout' => 300]);

        try {
            $clickhouse = DB::connection('clickhouse');
            $clickhouse->select('SELECT 1');
        } catch (Throwable $exception) {
            $this->components->error('ClickHouse is unreachable: '.$this->redact($exception->getMessage(), $password));

            return self::FAILURE;
        }

        $postgresTable = fn (string $table): string => sprintf(
            "postgresql('%s:%s', '%s', '%s', '%s', '%s')",
            $postgres['host'],
            $postgres['port'],
            $postgres['database'],
            $table,
            $postgres['username'],
            $this->escape((string) $postgres['password']),
        );

        $tables = [
            LabObservation::TABLE,
            LabFilter::TABLE,
            LabFilterObservation::TABLE,
            LabWeeklySummary::TABLE,
        ];

        foreach ($tables as $table) {
            if (! Schema::connection('clickhouse')->hasTable($table)) {
                $this->components->error('ClickHouse schema is missing. Run php artisan migrate first.');

                return self::FAILURE;
            }
        }

        $this->components->info('Truncating ClickHouse tables ['.implode(', ', $tables).'].');

        try {
            $clickhouse->statement('TRUNCATE TABLE '.LabWeeklySummary::TABLE);
            $clickhouse->statement('TRUNCATE TABLE '.LabFilterObservation::TABLE);
            $clickhouse->statement('TRUNCATE TABLE '.LabFilter::TABLE);
            $clickhouse->statement('TRUNCATE TABLE '.LabObservation::TABLE);
        } catch (Throwable $exception) {
            $this->components->error('Truncate failed: '.$this->redact($exception->getMessage(), $password));

            return self::FAILURE;
        }

        $loadStartedAt = microtime(true);

        try {
            $clickhouse->statement($this->insertObservationsSql([
                'bacteria_observations' => $postgresTable('bacteria_observations'),
                'observations' => $postgresTable('observations'),
                'samples' => $postgresTable('samples'),
                'bacteria' => $postgresTable('bacteria'),
            ]));

            $clickhouse->statement($this->insertFiltersSql($postgresTable('filters')));

            $clickhouse->statement($this->insertFilterObservationsSql(
                $postgresTable('filter_observations'),
                $postgresTable('filters'),
            ));
        } catch (Throwable $exception) {
            $this->components->error('Load failed: '.$this->redact($exception->getMessage(), $password));

            return self::FAILURE;
        }

        $snapshotStartedAt = microtime(true);

        try {
            $firstObservation = $clickhouse->selectOne('SELECT min(week_start) AS first_week FROM '.LabObservation::TABLE.' HAVING count() > 0');
            $firstFilter = $clickhouse->selectOne('SELECT min(placed_week) AS first_week FROM '.LabFilter::TABLE.' HAVING count() > 0');

            $firstWeeks = array_values(array_filter([
                $firstObservation['first_week'] ?? null,
                $firstFilter['first_week'] ?? null,
            ]));

            $firstWeek = $firstWeeks === [] ? null : min($firstWeeks);

            if ($firstWeek !== null) {
                $lastWeek = Lab::query()
                    ->with('organization')
                    ->get()
                    ->map(fn (Lab $lab): string => CarbonImmutable::now($lab->organization->timezone)
                        ->startOfWeek(CarbonInterface::MONDAY)
                        ->toDateString())
                    ->max();

                for (
                    $week = CarbonImmutable::parse($firstWeek);
                    $week->lessThanOrEqualTo(CarbonImmutable::parse($lastWeek));
                    $week = $week->addWeek()
                ) {
                    $weekDate = $week->toDateString();

                    $clickhouse->statement($this->snapshotSql(), [
                        $weekDate,
                        $weekDate,
                        $weekDate,
                        $weekDate,
                    ]);
                    $clickhouse->statement($this->snapshotFilterOnlySql(), [
                        $weekDate,
                        $weekDate,
                        $weekDate,
                        $weekDate,
                    ]);
                }
            }
        } catch (Throwable $exception) {
            $this->components->error('Snapshot build failed: '.$this->redact($exception->getMessage(), $password));

            return self::FAILURE;
        }

        $raw = $this->stats($clickhouse, LabObservation::TABLE);
        $filters = $this->stats($clickhouse, LabFilter::TABLE);
        $filterObservations = $this->stats($clickhouse, LabFilterObservation::TABLE);
        $summary = $this->stats($clickhouse, LabWeeklySummary::TABLE);

        $this->components->info(sprintf(
            'Loaded %s raw rows (%s), %s filter rows (%s), %s filter readings (%s), and %s summary rows (%s) in %.1f s (%.1f s of snapshots).',
            number_format($raw['rows']),
            $raw['size'],
            number_format($filters['rows']),
            $filters['size'],
            number_format($filterObservations['rows']),
            $filterObservations['size'],
            number_format($summary['rows']),
            $summary['size'],
            microtime(true) - $loadStartedAt,
            microtime(true) - $snapshotStartedAt,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $postgresTables  Four postgresql() table functions, keyed by source table.
     */
    private function insertObservationsSql(array $postgresTables): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    s.lab_id,
    o.sample_id,
    bo.bacteria_id,
    o.observed_at,
    o.iso_year,
    o.iso_week,
    addDays(toMonday(makeDate(o.iso_year, 1, 4)), (o.iso_week - 1) * 7) AS week_start,
    ifNull(bo.growth_level, 'not_applicable') AS growth_level,
    ifNull(bo.`count`, -1) AS `count`,
    b.name AS bacteria_name,
    b.short_name AS bacteria_short_name,
    b.color AS bacteria_color,
    b.icon AS bacteria_icon,
    b.observation_type
FROM %s AS bo
INNER JOIN %s AS o ON o.id = bo.observation_id
INNER JOIN %s AS s ON s.id = o.sample_id
INNER JOIN %s AS b ON b.id = bo.bacteria_id
SQL, LabObservation::TABLE, ...array_values($postgresTables));
    }

    private function insertFiltersSql(string $postgresFilters): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    f.id AS filter_id,
    f.lab_id,
    f.sample_id,
    f.placed_at,
    f.week_start AS placed_week,
    f.iso_year AS placed_iso_year,
    f.iso_week AS placed_iso_week
FROM %s AS f
SQL, LabFilter::TABLE, $postgresFilters);
    }

    private function insertFilterObservationsSql(
        string $postgresFilterObservations,
        string $postgresFilters,
    ): string {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    fo.filter_id,
    f.lab_id,
    f.sample_id,
    fo.observed_at,
    fo.week_start,
    fo.value
FROM %s AS fo
INNER JOIN %s AS f ON f.id = fo.filter_id
SQL, LabFilterObservation::TABLE, $postgresFilterObservations, $postgresFilters);
    }

    private function snapshotSql(): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    b.lab_id,
    toDate(?) AS week_start,
    b.sample_id,
    b.present_count,
    b.level_count,
    b.count_count,
    b.highest_level,
    b.max_count,
    b.single_id,
    b.single_observation_type,
    b.single_growth_level,
    b.single_count,
    f.filter_id,
    f.filter_value
FROM (
    SELECT
        lab_id,
        sample_id,
        countIf(present) AS present_count,
        countIf(present AND observation_type = 'level') AS level_count,
        countIf(present AND observation_type = 'count') AS count_count,
        maxIf(growth_level, present AND observation_type = 'level') AS highest_level,
        maxIf(`count`, present AND observation_type = 'count') AS max_count,
        anyIf(bacteria_id, present) AS single_id,
        anyIf(observation_type, present) AS single_observation_type,
        anyIf(growth_level, present) AS single_growth_level,
        anyIf(`count`, present) AS single_count
    FROM (
        SELECT
            lab_id,
            sample_id,
            bacteria_id,
            argMax(growth_level, observed_at) AS growth_level,
            argMax(`count`, observed_at) AS `count`,
            argMax(observation_type, observed_at) AS observation_type,
            if(observation_type = 'count', `count` > 0, growth_level IN ('low', 'medium', 'high')) AS present
        FROM %s
        WHERE week_start <= toDate(?)
        GROUP BY lab_id, sample_id, bacteria_id
    )
    GROUP BY lab_id, sample_id
    HAVING present_count > 0
) AS b
LEFT JOIN (
%s
) AS f ON f.lab_id = b.lab_id AND f.sample_id = b.sample_id
SQL, LabWeeklySummary::TABLE, LabObservation::TABLE, $this->filterStateSql());
    }

    private function snapshotFilterOnlySql(): string
    {
        return sprintf(<<<'SQL'
INSERT INTO %s
SELECT
    f.lab_id,
    toDate(?) AS week_start,
    f.sample_id,
    0 AS present_count,
    0 AS level_count,
    0 AS count_count,
    'none' AS highest_level,
    -1 AS max_count,
    0 AS single_id,
    '' AS single_observation_type,
    'none' AS single_growth_level,
    -1 AS single_count,
    f.filter_id,
    f.filter_value
FROM (
%s
) AS f
LEFT JOIN (
    SELECT lab_id, sample_id
    FROM %s
    WHERE week_start = toDate(?)
    GROUP BY lab_id, sample_id
) AS existing ON existing.lab_id = f.lab_id AND existing.sample_id = f.sample_id
WHERE existing.sample_id = 0
SQL, LabWeeklySummary::TABLE, $this->filterStateSql(), LabWeeklySummary::TABLE);
    }

    private function filterStateSql(): string
    {
        return sprintf(<<<'SQL'
SELECT
    f.lab_id,
    f.sample_id,
    f.filter_id,
    if(max(fo.observed_at) = toDateTime64(0, 6, 'UTC'), -1, argMax(fo.value, fo.observed_at)) AS filter_value
FROM %s AS f
LEFT JOIN %s AS fo
    ON fo.filter_id = f.filter_id AND fo.week_start = toDate(?)
WHERE f.placed_week <= toDate(?)
GROUP BY f.lab_id, f.sample_id, f.filter_id
SQL, LabFilter::TABLE, LabFilterObservation::TABLE);
    }

    /**
     * @return array{rows: int, size: string}
     */
    private function stats(Connection $connection, string $table): array
    {
        $row = $connection->selectOne(
            'SELECT sum(rows) AS rows, formatReadableSize(sum(bytes_on_disk)) AS size FROM system.parts WHERE database = ? AND table = ? AND active',
            [config('database.connections.clickhouse.database'), $table],
        );

        return [
            'rows' => (int) ($row['rows'] ?? 0),
            'size' => (string) ($row['size'] ?? '0 B'),
        ];
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function redact(string $message, string $password): string
    {
        return $password === '' ? $message : str_replace($password, '***', $message);
    }
}
