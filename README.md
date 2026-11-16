# Bacteria Timeseries

A Laravel 13 + Inertia/Vue demo for a timeseries-heavy workload backed by ClickHouse.

Postgres remains the application database (users, sessions, cache, queues). ClickHouse is a
secondary connection reserved for analytical data; the dataset, ingestion and dashboard are
not wired up yet.

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

### Tests

```bash
php artisan test --filter=ClickHouseConnectionTest
```

The integration test hits a real ClickHouse server and is skipped with a message when the
service is unreachable. CI starts ClickHouse, Postgres and Redis as service containers.

## Observations

The observations module adds organizations, labs, a fixed row/shelf/level hierarchy, samples, and weekly bacteria observations. The full ISO/IEC/IEEE 29148 requirements specification is in [`docs/observations/iso-29148-srs.md`](docs/observations/iso-29148-srs.md).

### Demo data

`DemoStressSeeder` is explicit and destructive to demo organizations, labs, samples, and observations. It preserves users and the bacteria catalog.

```bash
php artisan db:seed --class=DemoStressSeeder
```

It creates 3 organizations in different timezones, 9 labs of three sizes (5×6×8, 8×8×10, and 8×10×16 rows × shelves × levels, giving 240, 640, and 1,280 sample places), 19,440 samples, roughly 800,000 observations, and about 1.9 million bacteria observations. On a local lerd install the seed takes about 8 minutes and peaks around 43 MB of memory. The seeder is deterministic and can be re-run. It attaches the existing user and two demo users (`demo.admin@example.com`, `demo.member@example.com`) to each organization with owner, admin, and member roles; the demo users use the password `password`.

### Pages

- `/labs` — labs of the organization selected in the sidebar switcher.
- `/labs/{lab}` — the lab map with a week selector and per-sample bacteria summaries.
- `/organizations` — Manage Organizations: create, edit, and delete organizations.
- `/bacteria` — the bacteria atlas.

The selected organization lives in the server session, so lab URLs never contain an organization id. There is no dashboard page; sign-in and email verification land on `/labs`.

Observations are seeded only in this increment; manual observation creation is deferred.

## Setup

```bash
composer setup
php artisan ch:health
```
