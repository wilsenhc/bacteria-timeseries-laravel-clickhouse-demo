<?php

namespace App\Support\LabMap;

use Illuminate\Database\Connection;
use InvalidArgumentException;

final class ClickHouseLabMapTable
{
    public const NAME = 'lab_observations';

    public const SUMMARY_NAME = 'lab_weekly_summaries';

    public const FILTER_NAME = 'lab_filters';

    public const FILTER_OBSERVATION_NAME = 'lab_filter_observations';

    public static function create(Connection $connection, string $table = self::NAME): void
    {
        $connection->statement(sprintf(<<<'SQL'
CREATE TABLE %s (
    lab_id UInt64,
    sample_id UInt64,
    bacteria_id UInt64,
    observed_at DateTime64(6, 'UTC') CODEC(Delta, ZSTD(1)),
    iso_year UInt16,
    iso_week UInt8,
    week_start Date,
    growth_level Enum8('none' = 0, 'low' = 1, 'medium' = 2, 'high' = 3, 'not_applicable' = 4),
    `count` Int32 CODEC(ZSTD(1)),
    bacteria_name LowCardinality(String),
    bacteria_short_name LowCardinality(String),
    bacteria_color LowCardinality(String),
    bacteria_icon LowCardinality(String),
    observation_type LowCardinality(String)
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, sample_id, bacteria_id)
SQL, self::assertName($table)));
    }

    public static function drop(Connection $connection, string $table = self::NAME): void
    {
        $connection->statement('DROP TABLE IF EXISTS '.self::assertName($table));
    }

    public static function createSummary(Connection $connection, string $table = self::SUMMARY_NAME): void
    {
        $connection->statement(sprintf(self::SUMMARY_TABLE_DDL, self::assertName($table)));
    }

    public static function dropSummary(Connection $connection, string $table = self::SUMMARY_NAME): void
    {
        $connection->statement('DROP TABLE IF EXISTS '.self::assertName($table));
    }

    public static function createFilters(Connection $connection, string $table = self::FILTER_NAME): void
    {
        $connection->statement(sprintf(self::FILTER_TABLE_DDL, self::assertName($table)));
    }

    public static function dropFilters(Connection $connection, string $table = self::FILTER_NAME): void
    {
        $connection->statement('DROP TABLE IF EXISTS '.self::assertName($table));
    }

    public static function createFilterObservations(Connection $connection, string $table = self::FILTER_OBSERVATION_NAME): void
    {
        $connection->statement(sprintf(self::FILTER_OBSERVATION_TABLE_DDL, self::assertName($table)));
    }

    public static function dropFilterObservations(Connection $connection, string $table = self::FILTER_OBSERVATION_NAME): void
    {
        $connection->statement('DROP TABLE IF EXISTS '.self::assertName($table));
    }

    /**
     * One INSERT SELECT that writes the bacteria-present summary snapshot for
     * one week directly from the raw observation table, left joined to filters
     * placed by that week.
     */
    public static function snapshotSql(
        string $summaryTable = self::SUMMARY_NAME,
        string $rawTable = self::NAME,
        string $filterTable = self::FILTER_NAME,
        string $filterObservationTable = self::FILTER_OBSERVATION_NAME,
    ): string {
        return sprintf(
            self::SUMMARY_INSERT_SQL,
            self::assertName($summaryTable),
            self::assertName($rawTable),
            self::filterStateSql($filterTable, $filterObservationTable),
        );
    }

    /**
     * Insert the filter-only samples a week's snapshot is missing: samples with
     * a filter placed by that week but no present bacteria.
     */
    public static function snapshotFilterOnlySql(
        string $summaryTable = self::SUMMARY_NAME,
        string $filterTable = self::FILTER_NAME,
        string $filterObservationTable = self::FILTER_OBSERVATION_NAME,
    ): string {
        return sprintf(
            self::FILTER_ONLY_INSERT_SQL,
            self::assertName($summaryTable),
            self::filterStateSql($filterTable, $filterObservationTable),
            self::assertName($summaryTable),
        );
    }

    /**
     * Build the INSERT SELECT that copies and denormalizes the Postgres dataset.
     *
     * @param  array<string, string>  $postgresTables  Four postgresql() table functions, keyed by source table.
     */
    public static function insertSql(string $table, array $postgresTables): string
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
SQL, self::assertName($table), ...array_values($postgresTables));
    }

    public static function insertFiltersSql(string $table, string $postgresFilters): string
    {
        return sprintf(self::FILTER_INSERT_SQL, self::assertName($table), $postgresFilters);
    }

    public static function insertFilterObservationsSql(
        string $table,
        string $postgresFilterObservations,
        string $postgresFilters,
    ): string {
        return sprintf(
            self::FILTER_OBSERVATION_INSERT_SQL,
            self::assertName($table),
            $postgresFilterObservations,
            $postgresFilters,
        );
    }

    /**
     * Row count and compressed size from system.parts.
     *
     * @return array{rows: int, size: string}
     */
    public static function stats(Connection $connection, string $table = self::NAME): array
    {
        $row = $connection->selectOne(
            'SELECT sum(rows) AS rows, formatReadableSize(sum(bytes_on_disk)) AS size FROM system.parts WHERE database = ? AND table = ? AND active',
            [config('database.connections.clickhouse.database'), self::assertName($table)],
        );

        return [
            'rows' => (int) ($row['rows'] ?? 0),
            'size' => (string) ($row['size'] ?? '0 B'),
        ];
    }

    public static function assertName(string $table): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new InvalidArgumentException("Invalid ClickHouse table name [{$table}].");
        }

        return $table;
    }

    private static function filterStateSql(string $filterTable, string $filterObservationTable): string
    {
        return sprintf(
            self::FILTER_STATE_SQL,
            self::assertName($filterTable),
            self::assertName($filterObservationTable),
        );
    }

    private const SUMMARY_TABLE_DDL = <<<'SQL'
CREATE TABLE %s (
    lab_id UInt64,
    week_start Date,
    sample_id UInt64,
    present_count UInt16,
    level_count UInt16,
    count_count UInt16,
    highest_level Enum8('none' = 0, 'low' = 1, 'medium' = 2, 'high' = 3, 'not_applicable' = 4),
    max_count Int32,
    single_id UInt64,
    single_observation_type LowCardinality(String),
    single_growth_level Enum8('none' = 0, 'low' = 1, 'medium' = 2, 'high' = 3, 'not_applicable' = 4),
    single_count Int32,
    filter_id UInt64,
    filter_value Int32
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, sample_id)
SQL;

    private const FILTER_TABLE_DDL = <<<'SQL'
CREATE TABLE %s (
    filter_id UInt64,
    lab_id UInt64,
    sample_id UInt64,
    placed_at DateTime64(6, 'UTC') CODEC(Delta, ZSTD(1)),
    placed_week Date,
    placed_iso_year UInt16,
    placed_iso_week UInt8
) ENGINE = MergeTree
PARTITION BY toYear(placed_week)
ORDER BY (lab_id, placed_week, sample_id)
SQL;

    private const FILTER_OBSERVATION_TABLE_DDL = <<<'SQL'
CREATE TABLE %s (
    filter_id UInt64,
    lab_id UInt64,
    sample_id UInt64,
    observed_at DateTime64(6, 'UTC') CODEC(Delta, ZSTD(1)),
    week_start Date,
    value UInt16
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, filter_id)
SQL;

    private const FILTER_STATE_SQL = <<<'SQL'
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
SQL;

    private const SUMMARY_INSERT_SQL = <<<'SQL'
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
SQL;

    private const FILTER_ONLY_INSERT_SQL = <<<'SQL'
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
SQL;

    private const FILTER_INSERT_SQL = <<<'SQL'
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
SQL;

    private const FILTER_OBSERVATION_INSERT_SQL = <<<'SQL'
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
SQL;
}
