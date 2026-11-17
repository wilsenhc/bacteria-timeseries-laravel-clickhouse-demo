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
php artisan clickhouse:health
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

ClickHouse schema is versioned in the regular `database/migrations` directory alongside the
PostgreSQL migrations; each ClickHouse migration declares the `clickhouse` connection. Run:

```bash
php artisan migrate
php artisan demo:seed
php artisan clickhouse:load
```

`php artisan migrate` runs both schemas, so ClickHouse must be running when you migrate. The
ClickHouse migrations recreate their tables if they already exist from the old loader, so the
first `migrate` is also the adoption path. `clickhouse:load` then checks the schema, truncates the
replica tables, and reloads them from PostgreSQL using ClickHouse's `postgresql()` table
function and one `INSERT ... SELECT` per week for the snapshot. ClickHouse remains optional
at runtime, but its migrations require the service when migrating. The tables have read
models under `app/Models/ClickHouse`; the migrations use the package's `Blueprint` and
`Schema` builder.

The PostgreSQL map query uses a denormalized `sample_id`/`observed_at` state index on
`bacteria_observations`. The index is migration-owned; the demo seeder keeps it during bulk
inserts, so a fresh 10x seed is slower than the pre-index measurement. An existing database
created before the index runs `php artisan observations:backfill-state` (DML only) and then
applies the index migration.

Benchmark the engines (and optionally a synthetic 5x per-lab dataset):

```bash
php artisan clickhouse:bench --iterations=5
php artisan clickhouse:bench --scale=5 --iterations=5
```

The schema, the ClickHouse features used, the query, the snapshot, the benchmark, and the
measured comparison are in
[`docs/observations/clickhouse.md`](docs/observations/clickhouse.md).

The lab map is rendered in one Inertia request: the page returns rows, shelves, levels, sample
ids/codes, and the selected week's per-sample summaries as arrays. The three engines sit behind
`LabMapService`; the modal's bacterium and filter details load through the `sampleDetails`
optional prop with a POST body. There is no custom `fetch`/AJAX endpoint. `clickhouse:bench` uses
migration-created ClickHouse bench tables and only truncates and fills them.

### Tests

```bash
php artisan test --filter=ClickHouseConnectionTest
php artisan test tests/Feature/Support/LabMap
```

The integration tests hit a real ClickHouse server and are skipped with a message when the
service is unreachable. They use a separate ClickHouse database, so the test suite does not
wipe the development replica. Create it once:

```bash
curl -u lerd:lerd \
  "http://localhost:8123/?query=CREATE+DATABASE+IF+NOT+EXISTS+bacteria_timeseries_laravel_example_testing"
```

CI starts ClickHouse, Postgres and Redis as service containers with the testing
databases already configured.

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
php artisan clickhouse:load
```

This keeps the same 97,200 samples and 26-week window but generates 20–40 readings per sample
per week instead of 2–4, producing **43,647,057 observations** and **100,819,998 bacteria
observations**. PostgreSQL grows to 44 GB after the state-column backfill (20 GB before); the
ClickHouse raw table loads in 137.3 s (31.7 s of that is the snapshot) and occupies 762.51 MiB.
The seed takes about 7.7 hours on the local environment. The PostgreSQL full-lab query is
8.05 s at this scale, so the one-request map page takes ~10–12 s on PostgreSQL; the ClickHouse
snapshot page takes ~0.75 s.

The seeder commits the hierarchy once per lab and observation batches in 1,000-observation
chunks, so a 10x run does not hold one giant transaction. ClickHouse is not touched until
`clickhouse:load` at the end.

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
php artisan clickhouse:health
```
