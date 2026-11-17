# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The primary audience is developers evaluating Laravel 13 + Inertia/Vue against a timeseries-heavy analytical workload; they run the demo locally, open the lab map, switch query engines, and read the benchmark and schema notes in `docs/observations/`.

The app's simulated end users are lab technicians and QA / surveillance analysts in food-safety microbiology. Technicians locate a sample on the shelf map and review its weekly bacteria state; analysts scan a whole lab for presence, growth levels, and counts across ISO weeks. Their workflows exist to make the demo believable and performance-real, not because real labs are using the product.

## Product Purpose

Demonstrate how a realistic, domain-shaped analytical UI behaves when the same question — what is each sample+bacterium's state at the end of a selected ISO week — is answered by three different engines: PostgreSQL, a raw ClickHouse scan, and a pre-computed ClickHouse snapshot. Success means an engineer can seed one deterministic dataset, switch engines from the lab map, and see a measured comparison that holds up against the documented methodology.

## Positioning

The demo earns its comparison by shipping the domain with it: a multi-tenant lab hierarchy, ISO-week carry-forward state, and a deterministic dataset of over 10M bacteria observations (scalable to 100M), so the engines are benchmarked against real UI work rather than synthetic `SELECT 1` queries. It also records the rejected option — a weekly AggregatingMergeTree rollup that measured 191 ms versus 108 ms for the raw scan — so the performance claim includes a lesson, not just a victory.

## Operating Context

- Local development runs on lerd (default) or Docker with Laravel Sail. Postgres is the application database; ClickHouse is a secondary connection used through `DB::connection('clickhouse')` and is never the default.
- `DemoStressSeeder` creates 3 organizations across timezones, 9 labs (1,200, 3,200, and 6,400 sample places), 97,200 samples, and, in the normal seed, roughly 4.4M observations with about 10.6M bacteria observations. `--scale=10` produces 43,647,057 observations and 100,819,998 bacteria observations; the full-scale seed takes about 7.7 hours. Both are deterministic and re-runnable.
- `php artisan ch:load` rebuilds the denormalized ClickHouse replica and weekly snapshot; `php artisan ch:bench` measures engines, with `--scale=5` for synthetic per-lab scaling.
- Demo accounts (`demo.admin@example.com`, `demo.member@example.com`, password `password`) are attached to each organization with owner, admin, and member roles.
- The selected organization lives in the server session, so lab URLs never carry an organization id. There is no dashboard page; sign-in and email verification land on `/labs`.
- Integration tests hit a real ClickHouse server and skip with a message when it is unreachable; CI supplies ClickHouse, Postgres, and Redis as service containers.
- Scope decisions for the current increment are governed by `docs/observations/iso-29148-srs.md` (SRS-OBS-001).

## Capabilities and Constraints

- Pages: `/` (public atlas landing), `/bacteria` and `/bacteria/{bacteria}` (catalog), `/labs`, `/labs/{lab}` (lab map: a row/shelf/level matrix with one colour-and-shape mark per level, a week selector, and a sample details modal), `/organizations` (manage organizations), plus auth and settings.
- The lab map engine switch is a header dropdown over PostgreSQL (default), ClickHouse raw, and ClickHouse snapshot; each option shows the last measured state-query time for the current lab and week. Selection persists in the session under `lab_map_engine`; stale values fall back to PostgreSQL.
- The map does not print sample codes, bacterium names, growth words, or counts: those live in the details modal, which lists the level's samples and loads the selected sample's bacteria on demand.
- Lab structure is fixed at `rows × shelves_per_row × levels_per_shelf`. Samples are pinned to one level, and weekly state is the latest observation at or before the selected ISO week, carried forward from earlier weeks.
- Bacteria results are either a qualitative growth level (`high`/`medium`/`low`/`none`) or a non-negative count; a `none`/`0` terminates a cycle.
- Deliberately out of scope: manual observation creation/edit/delete UI, sample CRUD UI, membership invitation UI, dashboards beyond the lab map, batch readings, native mobile apps, guest access to organization/lab data, soft deletes, non-uniform lab layouts through the UI, and the withdrawn AggregatingMergeTree weekly rollup.
- ClickHouse writes are limited to the lab map replica; the snapshot is rebuilt by `ch:load`, not updated incrementally.

## Brand Commitments

The product name is Bacteria Timeseries, presented in the UI as a "Foodborne pathogen atlas" with the tagline "Weekly surveillance". Scientific terminology (sample place, row/shelf/level, growth level, ISO week, cycle) is part of the product's voice and should stay precise rather than consumer-friendly.

## Evidence on Hand

- Deterministic dataset and demo accounts defined in `DemoStressSeeder`; no real lab, customer, or production deployment exists, and no customer names, testimonials, compliance certifications, or production metrics may be invented.
- Measured ClickHouse replica (10x profile): 100,819,998 raw rows, 972 filter placements, 7,906 filter readings, 1,537,147 summary rows; 762.51 MiB raw and 21.82 MiB summary on disk; 137.3 s load including the 31.7 s snapshot build.
- Measured latency on Lab C: PostgreSQL 8,053 ms (p95 8,176 ms); ClickHouse raw 878 ms (p95 951 ms) / 3,946 ms synthetic 5x (p95 4,085 ms); ClickHouse snapshot 144 ms (p95 165 ms) / 656 ms synthetic 5x (p95 693 ms). The one-request map page returns the largest lab in ~0.75 s with the snapshot engine, ~1.6 s with raw ClickHouse, and ~8–12 s with PostgreSQL (engine query 8,053 ms median); the payload is ~3.39 MB.
- Root README and `docs/observations/clickhouse.md` carry the reproducible commands; `docs/observations/iso-29148-srs.md` carries requirements and acceptance criteria.

## Product Principles

1. The comparison is the product: every lab feature must make the three-engine question more believable or more measurable, not just add surface.
2. Show the measurement where the decision happens — engine timings sit next to the switch, not only in a doc.
3. Realism over volume: a deterministic dataset with a coherent lab hierarchy beats random rows, and it must stay reproducible.
4. Document the rejected path; a benchmark without its counter-example is marketing.
5. Keep Postgres the application truth and ClickHouse the analytical replica; never let the demo blur which store owns what.
