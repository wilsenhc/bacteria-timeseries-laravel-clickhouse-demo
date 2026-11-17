<?php

namespace App\Console\Commands;

use App\Models\ClickHouse\LabFilter;
use App\Models\ClickHouse\LabFilterObservation;
use App\Models\ClickHouse\LabObservation;
use App\Models\ClickHouse\LabWeeklySummary;
use App\Models\Lab;
use App\Services\LabMap\Replica\WeeklySnapshot;
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
    public function handle(WeeklySnapshot $snapshot): int
    {
        $postgres = config('database.connections.pgsql');
        $password = (string) $postgres['password'];

        config(['database.connections.clickhouse.timeout' => 300]);

        $clickhouse = DB::connection('clickhouse');
        $attempts = 60;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $clickhouse->select('SELECT 1');

                break;
            } catch (Throwable $exception) {
                if ($attempt === $attempts) {
                    $this->components->error('ClickHouse is unreachable: '.$this->redact($exception->getMessage(), $password));

                    return self::FAILURE;
                }

                if ($attempt === 1) {
                    $this->components->info('Waiting for ClickHouse to accept connections...');
                }

                sleep(1);
            }
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

                    [$sql, $bindings] = $snapshot->summariesStatement($weekDate);
                    $clickhouse->statement($sql, $bindings);

                    [$sql, $bindings] = $snapshot->filterOnlyStatement($weekDate);
                    $clickhouse->statement($sql, $bindings);
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
SETTINGS join_algorithm = 'grace_hash', max_bytes_in_join = 1073741824
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
