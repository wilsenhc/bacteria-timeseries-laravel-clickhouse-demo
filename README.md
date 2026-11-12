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

## Setup

```bash
composer setup
php artisan ch:health
```
