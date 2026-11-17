<?php

namespace App\Console\Commands;

use App\Models\Lab;
use App\Support\LabMap\ClickHouseLabMapTable;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        $this->components->info('Rebuilding ClickHouse tables ['.implode(', ', [
            ClickHouseLabMapTable::NAME,
            ClickHouseLabMapTable::FILTER_NAME,
            ClickHouseLabMapTable::FILTER_OBSERVATION_NAME,
            ClickHouseLabMapTable::SUMMARY_NAME,
        ]).'].');
        ClickHouseLabMapTable::dropSummary($clickhouse);
        ClickHouseLabMapTable::dropFilterObservations($clickhouse);
        ClickHouseLabMapTable::dropFilters($clickhouse);
        ClickHouseLabMapTable::drop($clickhouse);
        ClickHouseLabMapTable::create($clickhouse);
        ClickHouseLabMapTable::createSummary($clickhouse);
        ClickHouseLabMapTable::createFilters($clickhouse);
        ClickHouseLabMapTable::createFilterObservations($clickhouse);

        $loadStartedAt = microtime(true);

        try {
            $clickhouse->statement(ClickHouseLabMapTable::insertSql(ClickHouseLabMapTable::NAME, [
                'bacteria_observations' => $postgresTable('bacteria_observations'),
                'observations' => $postgresTable('observations'),
                'samples' => $postgresTable('samples'),
                'bacteria' => $postgresTable('bacteria'),
            ]));

            $clickhouse->statement(ClickHouseLabMapTable::insertFiltersSql(
                ClickHouseLabMapTable::FILTER_NAME,
                $postgresTable('filters'),
            ));

            $clickhouse->statement(ClickHouseLabMapTable::insertFilterObservationsSql(
                ClickHouseLabMapTable::FILTER_OBSERVATION_NAME,
                $postgresTable('filter_observations'),
                $postgresTable('filters'),
            ));
        } catch (Throwable $exception) {
            $this->components->error('Load failed: '.$this->redact($exception->getMessage(), $password));

            return self::FAILURE;
        }

        $snapshotStartedAt = microtime(true);

        try {
            $firstObservation = $clickhouse->selectOne('SELECT min(week_start) AS first_week FROM '.ClickHouseLabMapTable::NAME.' HAVING count() > 0');
            $firstFilter = $clickhouse->selectOne('SELECT min(placed_week) AS first_week FROM '.ClickHouseLabMapTable::FILTER_NAME.' HAVING count() > 0');

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

                    $clickhouse->statement(ClickHouseLabMapTable::snapshotSql(), [
                        $weekDate,
                        $weekDate,
                        $weekDate,
                        $weekDate,
                    ]);
                    $clickhouse->statement(ClickHouseLabMapTable::snapshotFilterOnlySql(), [
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

        $raw = ClickHouseLabMapTable::stats($clickhouse, ClickHouseLabMapTable::NAME);
        $filters = ClickHouseLabMapTable::stats($clickhouse, ClickHouseLabMapTable::FILTER_NAME);
        $filterObservations = ClickHouseLabMapTable::stats($clickhouse, ClickHouseLabMapTable::FILTER_OBSERVATION_NAME);
        $summary = ClickHouseLabMapTable::stats($clickhouse, ClickHouseLabMapTable::SUMMARY_NAME);

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

    private function escape(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    private function redact(string $message, string $password): string
    {
        return $password === '' ? $message : str_replace($password, '***', $message);
    }
}
