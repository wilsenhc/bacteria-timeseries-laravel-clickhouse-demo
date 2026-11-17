# ClickHouse lab map

This document explains the ClickHouse side of the lab map: the two ClickHouse
implementations (a simple raw scan and a pre-computed snapshot), the ClickHouse
features they use, how the data gets there, and the benchmark.

PostgreSQL remains the application database and the source of truth. ClickHouse
is a read-only analytics replica for the lab map. `LabMapQuery` returns the same
summaries from all engines.

## The engines

There are exactly three engines:

| Engine              | What it reads                                              | Role                                  |
| ------------------- | ---------------------------------------------------------- | ------------------------------------- |
| PostgreSQL          | `observations` + `bacteria_observations` via `DISTINCT ON` | Default and reference.                |
| ClickHouse raw      | `lab_observations` with `argMax`                           | The simple ClickHouse implementation. |
| ClickHouse snapshot | `lab_weekly_summaries` with a direct week lookup           | The fast ClickHouse implementation.   |

The sample details modal uses the selected engine; the snapshot engine delegates
its per-sample query to the raw engine because the snapshot only stores the
map summary, not every bacterium.

## Schema and migrations

ClickHouse schema is versioned like the PostgreSQL schema, in the regular
`database/migrations` directory. Each ClickHouse migration declares
`protected $connection = 'clickhouse'` and uses the package's
`ClickHouse\Laravel\Schema\Blueprint`:

```php
Schema::connection('clickhouse')->create('lab_observations', function (ClickHouseBlueprint $table) {
    $table->unsignedBigInteger('lab_id');
    $table->unsignedBigInteger('sample_id');
    $table->unsignedBigInteger('bacteria_id');
    $table->dateTime('observed_at', 6);
    $table->unsignedSmallInteger('iso_year');
    $table->unsignedTinyInteger('iso_week');
    $table->date('week_start');
    $table->enum('growth_level', ['none', 'low', 'medium', 'high', 'not_applicable']);
    $table->integer('count');
    $table->text('bacteria_name')->lowCardinality();
    // ...
    $table->engine('MergeTree()');
    $table->partitionBy('toYear(week_start)');
    $table->orderBy(['lab_id', 'week_start', 'sample_id', 'bacteria_id']);
});
```

Run them with `php artisan migrate`; this runs the PostgreSQL and ClickHouse
migrations together, so ClickHouse must be running when you migrate. The
ClickHouse migrations recreate their tables if they already exist from the old
loader, which makes the first `migrate` the adoption path. `php artisan ch:load`
then checks the schema, truncates the replica tables, and reloads. The four
tables have
Eloquent models in `App\Models\ClickHouse` extending
`ClickHouse\Laravel\Eloquent\Model`, which provide the table names and casts.
Bulk federated loads (`postgresql()` + `INSERT ... SELECT`) and the ClickHouse
aggregate queries remain raw SQL because they are not Eloquent operations. The
snapshot engine uses the `LabWeeklySummary` model's table and connection through
the query builder's `toBase()` path, so it does not hydrate one model per
summary row.

ClickHouse remains optional at runtime, but `php artisan migrate` now requires
the service because its migrations run alongside the PostgreSQL ones.

## The raw table

One denormalized table holds everything the map needs:

```sql
CREATE TABLE lab_observations (
    lab_id UInt64,
    sample_id UInt64,
    bacteria_id UInt64,
    observed_at DateTime64(6),
    iso_year UInt16,
    iso_week UInt8,
    week_start Date,
    growth_level Enum8('none', 'low', 'medium', 'high', 'not_applicable'),
    `count` Int32,
    bacteria_name LowCardinality(String),
    bacteria_short_name LowCardinality(String),
    bacteria_color LowCardinality(String),
    bacteria_icon LowCardinality(String),
    observation_type LowCardinality(String)
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, sample_id, bacteria_id);
```

The bacteria metadata is copied into every row, so the map query needs no join.
`week_start` is the Monday of the observation's ISO week in the organization
timezone, computed once at load time. There are no `Nullable` columns: a level
observation stores `-1` in `count`, and a count observation stores
`not_applicable` in `growth_level`. The connection and ClickHouse server use
UTC; `DateTime64(6)` therefore stores UTC wall-clock values.

## ClickHouse features used

| Feature                                         | Where it is used                           | Why it matters                                                              |
| ----------------------------------------------- | ------------------------------------------ | --------------------------------------------------------------------------- |
| `MergeTree`                                     | Both tables                                | Columnar storage, immutable parts, sparse primary index.                    |
| `PARTITION BY toYear(week_start)`               | Both tables                                | Prunes whole years for older selected weeks.                                |
| `ORDER BY (lab_id, week_start, sample_id, ...)` | Both tables                                | The primary index starts with `lab_id`, so a map query reads only one lab.  |
| `LowCardinality(String)`                        | Bacteria metadata and observation type     | Dictionary-encodes repeated strings.                                        |
| `Enum8`                                         | `growth_level`                             | One byte per state; the numeric order makes `max()` mean the highest level. |
| `DateTime64(6)` and `Date`                      | Timestamps and `week_start`                | Microsecond ordering plus a cheap date filter; the server is UTC.           |
| `argMax(value, version)`                        | Raw current-state query and snapshot build | Latest value by timestamp, replacing PostgreSQL's `DISTINCT ON`.            |
| `countIf`, `maxIf`, `anyIf`                     | Per-sample summary                         | Conditional aggregates build the map row inside ClickHouse.                 |
| `PREWHERE`                                      | Raw query and snapshot build               | Reads the `lab_id` filter column first.                                     |
| `postgresql()` table function                   | `php artisan ch:load`                      | Copies and enriches the Postgres data without export files.                 |
| Plain `MergeTree` snapshot                      | `lab_weekly_summaries`                     | Stores the final map payload per sample/week for a direct lookup.           |
| `system.parts`                                  | `ch:load` output and experiments           | Shows compressed size and parts.                                            |
| `EXPLAIN indexes = 1`, `system.query_log`       | Manual experiments                         | Shows index pruning and real read/timing data.                              |

## Loading the data

```bash
php artisan ch:load
```

The command applies pending migrations, truncates the replica tables, and
reloads them. It first runs one federated insert into the raw table:

```sql
INSERT INTO lab_observations
SELECT
    s.lab_id,
    o.sample_id,
    bo.bacteria_id,
    o.observed_at,
    o.iso_year,
    o.iso_week,
    addDays(toMonday(makeDate(o.iso_year, 1, 4)), (o.iso_week - 1) * 7) AS week_start,
    ifNull(bo.growth_level, 'not_applicable') AS growth_level,
    ifNull(bo.count, -1) AS count,
    b.name,
    b.short_name,
    b.color,
    b.icon,
    b.observation_type
FROM postgresql('lerd-postgres:5432', 'bacteria_timeseries_laravel_example', 'bacteria_observations', 'postgres', '...') AS bo
INNER JOIN postgresql(/* observations */) AS o ON o.id = bo.observation_id
INNER JOIN postgresql(/* samples */) AS s ON s.id = o.sample_id
INNER JOIN postgresql(/* bacteria */) AS b ON b.id = bo.bacteria_id;
```

The `postgresql()` calls use the host, port, database, user, and password from
`config('database.connections.pgsql')`. The command redacts the password from
error messages, but the generated SQL still contains it; this is a local/demo
path, not a production credential pattern.

Then it builds the summary snapshot with one `INSERT ... SELECT` per week, from
the first observed week (or the first filter placement week, whichever is
earlier) through the current week. The snapshot query runs `argMax` over the raw
table up to that week and stores the final per-sample payload as plain columns;
filters placed by that week are left joined, and filter-only samples are inserted
separately. Empty carry-forward weeks are included.

Measured on the deterministic stress dataset:

| Metric                       | Result                                    |
| ---------------------------- | ----------------------------------------- |
| Raw rows                     | 10,606,888                                |
| Filter placements            | 972                                       |
| Filter readings              | 8,333                                     |
| Summary rows                 | 1,540,144                                 |
| Load time                    | 24.5 s, including 12.5 s for the snapshot |
| Raw on-disk size             | 102.83 MiB                                |
| Filter on-disk size          | 15.83 KiB                                 |
| Filter readings on-disk size | 95.75 KiB                                 |
| Summary on-disk size         | 21.62 MiB                                 |

These are the normal dataset numbers. A 10x stress profile is available with
`php artisan demo:seed --scale=10`: it keeps the same samples and weeks but
generates 20–40 readings per sample per week, producing about 44M observations
and 106M bacteria observations (PostgreSQL ~20 GB, ClickHouse raw ~1 GB). The
summary snapshot stays at ~1.54M rows because it is per sample/week. The seed
takes roughly 8 hours; run `ch:load` afterward. The 100M rows are across the
whole replica, so the largest lab has ~19M raw rows and a per-lab query scans
that lab's rows only.

Rebuild PostgreSQL first, then ClickHouse:

```bash
php artisan db:seed --class=DemoStressSeeder
php artisan ch:load
```

## The simple ClickHouse query

The raw engine answers: for this lab, what is each sample+bacterium's latest
state at the end of the selected ISO week?

```sql
SELECT
    lab_id,
    sample_id,
    bacteria_id,
    argMax(growth_level, observed_at) AS growth_level,
    argMax(`count`, observed_at) AS `count`,
    argMax(observation_type, observed_at) AS observation_type
FROM lab_observations
PREWHERE lab_id = ?
WHERE week_start <= toDate(?)
GROUP BY lab_id, sample_id, bacteria_id
```

`argMax` keeps the value from the row with the largest `observed_at`. Because
`week_start` is already in the organization's ISO weeks, the cutoff is a single
date comparison. The outer query then uses conditional aggregates to produce one
summary per sample:

```sql
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
    ...
FROM (/* the argMax states */)
GROUP BY lab_id, sample_id
```

`highest_level` works because the `Enum8` order is `low < medium < high`. The
sample details modal repeats the same state query for one `sample_id`.

Filters are a separate layer. The raw summary left joins one row per filter
placed by the selected week, with the latest reading inside that week only:

```sql
LEFT JOIN (
    SELECT
        f.lab_id,
        f.sample_id,
        f.filter_id,
        if(max(fo.observed_at) = toDateTime64(0, 6, 'UTC'), -1, argMax(fo.value, fo.observed_at)) AS filter_value
    FROM lab_filters AS f
    LEFT JOIN lab_filter_observations AS fo
        ON fo.filter_id = f.filter_id AND fo.week_start = toDate(?)
    WHERE f.placed_week <= toDate(?)
    GROUP BY f.lab_id, f.sample_id, f.filter_id
) AS f ON f.lab_id = b.lab_id AND f.sample_id = b.sample_id
```

The `-1` sentinel means the filter exists but has no reading in that week. A
second query returns the placed filters whose samples have no present bacteria,
so filter-only cells still reach the map. Filter values never look back before
the selected week's Monday.

## The fast ClickHouse snapshot

The snapshot table stores the final map summary directly, without repeating
bacterium metadata:

```sql
CREATE TABLE lab_weekly_summaries (
    lab_id UInt64,
    week_start Date,
    sample_id UInt64,
    present_count UInt16,
    level_count UInt16,
    count_count UInt16,
    highest_level Enum8(...),
    max_count Int32,
    single_id UInt64,
    single_observation_type LowCardinality(String),
    single_growth_level Enum8(...),
    single_count Int32,
    filter_id UInt64,       -- 0 = no filter
    filter_value Int32      -- -1 = filter placed but no reading this week
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, sample_id);
```

Filter placements and readings live in two small replicated tables:

```sql
CREATE TABLE lab_filters (
    filter_id UInt64,
    lab_id UInt64,
    sample_id UInt64,
    placed_at DateTime64(6),
    placed_week Date,
    placed_iso_year UInt16,
    placed_iso_week UInt8
) ENGINE = MergeTree
PARTITION BY toYear(placed_week)
ORDER BY (lab_id, placed_week, sample_id);

CREATE TABLE lab_filter_observations (
    filter_id UInt64,
    lab_id UInt64,
    sample_id UInt64,
    observed_at DateTime64(6),
    week_start Date,
    value UInt16
) ENGINE = MergeTree
PARTITION BY toYear(week_start)
ORDER BY (lab_id, week_start, filter_id);
```

The map query is then a direct week lookup:

```sql
SELECT ...
FROM lab_weekly_summaries
WHERE lab_id = ? AND week_start = toDate(?)
```

No `GROUP BY`, no `argMax`, no state merge. The snapshot table is rebuilt by
`ch:load`; it is not updated incrementally. The build runs two inserts per week:
the bacteria-present rows left joined to the filter state, then the filter-only
samples missing from that week's rows. The week loop starts at the earliest of
the first observation week and the first filter placement week, so filters placed
before any bacteria sample still appear.

## Rejected alternative: weekly AggregatingMergeTree rollup

Before the snapshot, a weekly state rollup was implemented with
`AggregatingMergeTree`, `argMaxState`/`argMaxMerge`, and a materialized view. It
stored 709,636 rows and 34.88 MiB and answered the same lab/week in about 191 ms,
slower than the 108 ms raw scan. The aggregate states were wider than the raw
values, and merging them cost more CPU than the raw scan saved. It was removed
to keep the map to three engines; the snapshot is built directly from the raw
table instead. The lesson: pre-aggregation is not automatically faster when the
raw columnar scan is already narrow.

## Benchmark

```bash
php artisan ch:bench --iterations=5
php artisan ch:bench --scale=5 --iterations=5
```

`ch:bench` measures the engines for the largest lab. `--scale=5` builds synthetic
per-lab 5x tables (five copies with shifted sample ids) for the two ClickHouse
engines. It does not re-seed PostgreSQL.

Measured on Lab C (1x = 19,200 samples; 5x = 96,000 samples, 80,000 present
summaries):

| Engine              | 1x median  | 5x median  | 5x p95     |
| ------------------- | ---------- | ---------- | ---------- |
| PostgreSQL          | 4,745 ms   | —          | —          |
| ClickHouse raw      | 258 ms     | 1,193 ms   | 1,508 ms   |
| ClickHouse snapshot | **112 ms** | **390 ms** | **405 ms** |

At 1x the snapshot is ~42x faster than PostgreSQL and ~2.3x faster than the raw
ClickHouse scan. At synthetic 5x it is ~3x faster than raw and stays around
400 ms at p95. The loaded dataset itself is 5x the previous 19,440-sample
dataset (97,200 samples across nine labs); `--scale=5` additionally duplicates
the largest lab to 96,000 samples for a per-lab stress query.

## The page path

The engine timer measures only the state query. The full page also builds the
hierarchy, the summaries, and the Inertia payload:

- One left-joined query loads rows, shelves, levels, and samples (including
  levels with no samples) in hierarchy order. No `Sample`/`LabLevel`/`LabShelf`
  models are hydrated.
- That query's sample ids seed the summary map, so the PostgreSQL prefill query
  and the separate sample-count query are gone.
- The summary is compact: per sample it stores counts, highest level, max count,
  and (only for single-bacterium samples) a `single` object with the bacterium id,
  observation type, growth level, and count. The page does not send a bacterium
  dictionary; the map renders one mark per level and the details endpoint loads
  the metadata for the selected sample.
- Rows and shelves use `content-visibility: auto` with intrinsic-size hints, so
  off-screen map sections skip layout and paint without paginating the map.

Measured for Lab C (6,400 levels, 19,200 samples) with the snapshot engine,
Inertia DevTools off, and SPX off:

| Metric              | Result                                              |
| ------------------- | --------------------------------------------------- |
| Server request      | ~780 ms                                             |
| Browser TTFB / load | ~779 ms / ~989 ms                                   |
| State query         | ~88–109 ms                                          |
| Queries             | 10                                                  |
| Hydrated models     | only user, lab, and organizations; no sample models |
| PHP memory peak     | ~79 MB                                              |
| Inertia page JSON   | ~3.37 MB                                            |
| Map cells rendered  | 6,400 level buttons (19,200 samples)                |
| Filter levels       | 192 (1% of samples)                                 |

The 3,600-sample lab measures ~636 KB, ~337 ms TTFB and ~494 ms load, with 36
filter levels. Inertia DevTools is disabled in `.env`
(`INERTIA_DEVTOOLS_ENABLED=false`): in local mode it records the full page and
serialized every cell, adding seconds on this map. SPX full profiling also adds
seconds, so it is enabled only for targeted profiles, not left on while
browsing.

## Filters in the map UI

A filter is a separate per-sample sampler, not a bacterium. The seeder places
one filter every 100th sample (1%) in hierarchy order, on a random completed week
after lab creation. The location and first-placed week never change; each weekly
reading is independent and does not carry over.

- The map renders one cell per level. If any of the level's three samples has a
  filter, the cell gets a small yellow filter-panel badge with the selected week's
  value, or the badge alone when that week has no reading.
- The details modal marks the filtered sample button and shows a separate Filter
  section with the first week placed and the selected week's reading, or "No
  reading this week." The section is absent before the placement week.
- The map legend has a yellow Filter entry.
- There is no placement UI; `FilterSeeder` rebuilds placements and readings, and
  `ch:load` replicates them to ClickHouse.

## The UI switch

The switch is a compact dropdown in the lab map header: **PostgreSQL**,
**ClickHouse**, and **ClickHouse snapshot**. Each option shows the last measured
state-query time for the current lab and week.

- The selected engine is stored in the session under `lab_map_engine`.
- `POST /labs/engine` validates `pgsql|clickhouse|clickhouse_snapshot`.
- `LabMapEngineManager` resolves the engine and defaults to PostgreSQL.
- `LabMapQuery` keeps the week and date logic; only the state queries move.
- The sample details modal uses the same selected engine; the snapshot engine
  delegates the modal to the raw engine.
- An unknown/stale session value falls back to PostgreSQL.

## Experiments worth running

1. **See the index work.**

    ```sql
    EXPLAIN indexes = 1
    SELECT sample_id, bacteria_id, argMax(growth_level, observed_at)
    FROM lab_observations
    PREWHERE lab_id = 9
    WHERE week_start <= toDate('2026-11-16')
    GROUP BY sample_id, bacteria_id;
    ```

2. **Read real timings.**

    ```sql
    SELECT query_duration_ms, read_rows, read_bytes, memory_usage
    FROM system.query_log
    WHERE type = 'QueryFinish'
      AND (query LIKE '%lab_observations%' OR query LIKE '%lab_weekly_summaries%' OR query LIKE '%lab_filter%')
    ORDER BY event_time DESC LIMIT 5;
    ```

3. **Watch parts merge.**

    ```sql
    SELECT table, partition, rows, formatReadableSize(bytes_on_disk)
    FROM system.parts
    WHERE table IN ('lab_observations', 'lab_weekly_summaries') AND active;
    OPTIMIZE TABLE lab_weekly_summaries FINAL;
    ```

4. **Refresh snapshots incrementally.** A scheduled build or refreshable
   materialized view would keep the snapshot current without a full `ch:load`.

5. **Try a plain weekly table instead of states.** A `ReplacingMergeTree` with
   plain weekly values and `argMax` reads fewer rows than raw without the wider
   aggregate states; compare it with the raw scan if you revisit the weekly
   design.

6. **Shrink the raw table metadata.** A bacteria dimension table joined at read
   time would remove repeated strings from the raw row, at the cost of a join.

## Limitations

- The ClickHouse tables are replicas. Run `php artisan migrate` for schema and
  `php artisan ch:load` to truncate and reload. There is no incremental sync.
- The ClickHouse migrations drop and recreate their tables; running `migrate`
  against loader-created tables is the adoption path.
- PostgreSQL stays the source of truth; writes still go to PostgreSQL only.
- The `ch:bench --scale=5` benchmark is synthetic per-lab duplication; the
  loaded 10x stress profile is a separate full dataset.
- The engine choice is per session, so all tabs share it.
- The `postgresql()` load needs the Postgres host to be resolvable from the
  ClickHouse server (`lerd-postgres` in lerd, `pgsql` in Sail).
