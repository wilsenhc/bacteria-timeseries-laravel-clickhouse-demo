# Bacteria Timeseries

A Laravel 13 + Inertia/Vue demo for a timeseries-heavy workload backed by ClickHouse.

Postgres remains the application database (users, sessions, cache, queues). ClickHouse is a
secondary connection reserved for analytical data; the lab map is the first analytical
surface, and ingestion is limited to its denormalized replica.

## Requirements

- PHP 8.5, Node 22
- [lerd](https://lerd.sh) (default) or Docker with Laravel Sail
- Postgres, Redis and ClickHouse

## ClickHouse

The connection is defined in `config/database.php` as `clickhouse` and is used through
`DB::connection('clickhouse')`. It is never the default connection.

| Variable              | Local (lerd)                          | Description   |
| --------------------- | ------------------------------------- | ------------- |
| `CLICKHOUSE_HOST`     | `lerd-clickhouse`                     | HTTP host     |
| `CLICKHOUSE_PORT`     | `8123`                                | HTTP port     |
| `CLICKHOUSE_DATABASE` | `bacteria_timeseries_laravel_example` | Database name |
| `CLICKHOUSE_USERNAME` | `lerd`                                | User          |
| `CLICKHOUSE_PASSWORD` | `lerd`                                | Password      |
| `CLICKHOUSE_HTTPS`    | `false`                               | Use TLS       |

### lerd

`.lerd.yaml` declares `clickhouse` as a service, so `lerd start` brings it up. The values in
`.env.example` are the lerd ones; copy it to `.env`, link the site, then create the per-site
databases:

```bash
lerd link
lerd db:create --service clickhouse
php artisan ch:health
```

The command prints the server version, host, database and round-trip time, and exits non-zero
when the connection fails.

### Sail

```bash
cp .env.example .env
./vendor/bin/sail up -d
```

`compose.yaml` runs `clickhouse:26.3` and injects `CLICKHOUSE_HOST=clickhouse` into the
application container, so no `.env` change is needed. The host port is **8124** so it does not
collide with a local lerd install on 8123; the HTTP playground is at
<http://localhost:8124/play?user=lerd&password=lerd>.

### Lab map data

The lab map has three interchangeable query engines: PostgreSQL (the default), ClickHouse (a
raw denormalized scan), and ClickHouse snapshot (a pre-computed per-sample/week summary). The
lab map header has a dropdown switch and shows the last measured state-query time for each
engine on the current lab and week.

Load the ClickHouse replica after rebuilding the PostgreSQL demo data:

```bash
php artisan demo:seed
php artisan ch:load
```

`ch:load` rebuilds the denormalized `lab_observations` MergeTree table, the `lab_filters`
and `lab_filter_observations` tables, and the `lab_weekly_summaries` snapshot, using
ClickHouse's `postgresql()` table function for the federated copy and one `INSERT ...
SELECT` per week for the snapshot.

Benchmark the engines (and optionally a synthetic 5x per-lab dataset):

```bash
php artisan ch:bench --iterations=5
php artisan ch:bench --scale=5 --iterations=5
```

The schema, the ClickHouse features used, the query, the snapshot, the benchmark, and the
measured comparison are in
[`docs/observations/clickhouse.md`](docs/observations/clickhouse.md).

### Tests

```bash
php artisan test --filter=ClickHouseConnectionTest
php artisan test --filter=ClickHouseLabMapTest
```

The integration tests hit a real ClickHouse server and are skipped with a message when the
service is unreachable. CI starts ClickHouse, Postgres and Redis as service containers.

## Observations

The observations module adds organizations, labs, a fixed row/shelf/level hierarchy, samples, and weekly bacteria observations. The full ISO/IEC/IEEE 29148 requirements specification is in [`docs/observations/iso-29148-srs.md`](docs/observations/iso-29148-srs.md).

### Demo data

`DemoStressSeeder` is explicit and destructive to demo organizations, labs, samples, and observations. It preserves users and the bacteria catalog.

```bash
php artisan demo:seed
```

It creates 3 organizations in different timezones, 9 labs of three sizes (10×10×12, 20×10×16, and 20×20×16 rows × shelves × levels, giving 1,200, 3,200, and 6,400 sample places), 97,200 samples, 4,434,752 observations, and 10,606,888 bacteria observations. It also places 972 filters (1% of samples: 36, 96, and 192 per lab) with 8,333 weekly filter readings; a filter's location and first placed week are permanent, and its value does not carry over to the next week. On a local lerd install the normal seed takes about 47 minutes and peaks around 43 MB of memory. The seeder is deterministic and can be re-run. It attaches the existing user and two demo users (`demo.admin@example.com`, `demo.member@example.com`) to each organization with owner, admin, and member roles; the demo users use the password `password`.

### 10x stress dataset

To test the raw-query limit, scale the readings per sample per week:

```bash
php artisan demo:seed --scale=10
php artisan ch:load
```

This keeps the same 97,200 samples and 26-week window but generates 20–40 readings per sample
per week instead of 2–4, producing roughly **44M observations** and **106M bacteria
observations**. PostgreSQL grows to about 20 GB; the ClickHouse raw table grows to about 1 GB.
Budget roughly 8 hours for the seed on the local environment.

The seeder commits the hierarchy once per lab and observation batches in 1,000-observation
chunks, so a 10x run does not hold one giant transaction. ClickHouse is not touched until
`ch:load` at the end.

### Pages

- `/labs` — labs of the organization selected in the sidebar switcher.
- `/labs/{lab}` — the lab map: a row/shelf/level matrix with one mark per level, a week selector, and a sample details modal.
- `/organizations` — Manage Organizations: create, edit, and delete organizations.
- `/bacteria` — the bacteria atlas.

The selected organization lives in the server session, so lab URLs never contain an organization id. There is no dashboard page; sign-in and email verification land on `/labs`.

Observations are seeded only in this increment; manual observation creation is deferred.

## Setup

```bash
composer setup
php artisan ch:health
```
