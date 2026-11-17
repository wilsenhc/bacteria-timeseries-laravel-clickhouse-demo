# Software Requirements Specification

## Observations Module — Bacteria Timeseries

| Field              | Value                                                             |
| ------------------ | ----------------------------------------------------------------- |
| Document ID        | SRS-OBS-001                                                       |
| Version            | 1.8                                                               |
| Date               | 2026-11-17                                                        |
| Status             | Draft for approval                                                |
| Standard           | ISO/IEC/IEEE 29148:2018                                           |
| Project            | `bacteria-timeseries-laravel-example`                             |
| Module             | Organizations, Labs, Samples, Observations, Lab Map, Demo Seeding |
| Database increment | PostgreSQL primary; ClickHouse lab map replica                    |
| Prepared by        | Engineering (design interview output)                             |
| Approved by        | Pending                                                           |

### Document control

| Version | Date       | Author      | Change                                                                                                                                                                                 |
| ------- | ---------- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1.0     | 2026-11-12 | Engineering | Initial SRS derived from the design interview                                                                                                                                          |
| 1.1     | 2026-11-13 | Engineering | Stress seeder tripled: small, medium, and large labs per organization (240, 640, and 1,280 sample places); updated volume and map performance targets                                  |
| 1.2     | 2026-11-13 | Engineering | Selected organization moved to the server-side session; Dashboard removed; added Labs and Manage Organizations pages; organization id removed from lab URLs                            |
| 1.3     | 2026-11-16 | Engineering | ClickHouse lab map engine added alongside PostgreSQL, with a UI switch and measured timings                                                                                            |
| 1.4     | 2026-11-16 | Engineering | Added the ClickHouse weekly AggregatingMergeTree rollup as a third lab map engine                                                                                                      |
| 1.5     | 2026-11-16 | Engineering | Added the ClickHouse weekly summary snapshot as a fourth engine and the 1x/5x benchmark command                                                                                        |
| 1.6     | 2026-11-17 | Engineering | Removed the weekly AggregatingMergeTree engine; three engines remain: PostgreSQL, ClickHouse raw, and ClickHouse snapshot                                                              |
| 1.7     | 2026-11-17 | Engineering | Lab map page path: flat hierarchy query, compact summaries plus bacteria dictionary, no prefill query, content-visibility; refreshed benchmarks and payload sizes                      |
| 1.8     | 2026-11-17 | Engineering | Lab map reworked as a row/shelf/level matrix: one colour-and-shape mark per level, sample codes and counts moved to a details modal, bacteria dictionary removed from the page payload |
| 1.9     | 2026-11-17 | Engineering | Added static Filter sampling: permanent per-sample placements, weekly non-carrying readings, yellow map badge, modal Filter section, ClickHouse filter tables and 5x dataset           |
| 1.10    | 2026-11-17 | Engineering | Moved ClickHouse schema into package Blueprint migrations and Eloquent models; ch:load checks the schema, truncates, and reloads                                                       |
| 1.11    | 2026-11-17 | Engineering | 10x stress dataset loaded and measured; PostgreSQL state query rewritten with denormalized sample/time columns and a state index                                                       |
| 1.12    | 2026-11-17 | Engineering | Lab map service refactor; one-request page with array summaries and sample-details optional prop; ClickHouse raw sample skip index and migration-owned bench tables                    |

### Table of contents

1. [Introduction](#1-introduction)
2. [Stakeholders and user classes](#2-stakeholders-and-user-classes)
3. [System context and operational environment](#3-system-context-and-operational-environment)
4. [Functional requirements](#4-functional-requirements)
5. [Non-functional requirements](#5-non-functional-requirements)
6. [External interface requirements](#6-external-interface-requirements)
7. [Data requirements](#7-data-requirements)
8. [Constraints](#8-constraints)
9. [Assumptions and dependencies](#9-assumptions-and-dependencies)
10. [Verification and acceptance](#10-verification-and-acceptance)
11. [Traceability](#11-traceability)
12. [Appendices](#12-appendices)

---

## 1. Introduction

### 1.1 Purpose

This document specifies the requirements for the Observations module of the Bacteria Timeseries application. It defines the organizations, labs, physical lab hierarchy, samples, observation semantics, weekly state model, lab map, demo seeding, access control, data model, and verification criteria needed to build the module.

The document is written to be reviewable and actionable before implementation. It is the authoritative source for scope decisions in this increment.

### 1.2 Scope

#### 1.2.1 In scope

- Organization management with name, address, and IANA timezone.
- A server-side selected-organization context with a navigation switcher.
- Organization membership with owner, admin, and member roles.
- A Manage Organizations page for creating, updating, and deleting organizations.
- A Labs page that lists the labs of the selected organization.
- Lab management with a fixed physical hierarchy of rows, shelves, and levels.
- Sample records placed at a lab level, with generated codes and fixed locations.
- Observation storage for raw readings, including level-based and count-based bacteria results.
- ISO week computation in the organization timezone and carry-forward weekly state.
- A full lab map with a week selector, per-level marks, and a details modal with lazy-loaded per-sample bacteria.
- Three interchangeable lab map query engines: PostgreSQL (default), ClickHouse raw, and ClickHouse snapshot, selected from the map UI.
- A deterministic, high-volume demo seeder for observations.
- PostgreSQL schema, migrations, models, factories, policies, form requests, pages, and tests.
- Existing bacteria catalog extension with an observation type.

#### 1.2.2 Out of scope

- Manual observation creation, editing, or deletion UI.
- Sample create, edit, or delete UI.
- Membership invitation or membership management UI.
- ClickHouse ingestion for sources other than the lab map replica, mirroring of live writes, and ClickHouse-only features such as materialized views or dictionaries. The lab map replica itself is in scope.
- Dashboards or reports beyond the lab map. The application has no dashboard page in this increment.
- Batch readings covering multiple samples at once.
- Mobile-native applications.
- Public or guest access to organization or lab data.
- Soft deletes.
- Historical timezone rewriting.
- Editing a lab's structure counts after creation.
- Non-uniform lab layouts created through the UI (the schema supports them, but the form does not).

### 1.3 Intended audience and reading suggestions

| Audience                      | Recommended sections     |
| ----------------------------- | ------------------------ |
| Product owner / domain expert | 1, 2, 4, 10, 12          |
| Backend engineer              | 3, 4, 5, 7, 8, 9, 10, 11 |
| Frontend engineer             | 4.7, 4.9, 5.5, 6.1, 10   |
| QA engineer                   | 4, 5, 10, 11             |
| Reviewer / approver           | 1, 2, 10, 11, 12         |

### 1.4 Definitions, acronyms, and abbreviations

| Term                  | Definition                                                                                                                       |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| Organization          | Top-level tenant. Owns labs and members. Has a name, address, and IANA timezone.                                                 |
| Selected organization | The organization the signed-in user chose in the session. It scopes the Labs area and is never part of a lab URL.                |
| Lab                   | A physical laboratory belonging to one organization. Has a fixed hierarchy of rows, shelves, and levels.                         |
| Row                   | A numbered aisle or row of shelving units inside a lab.                                                                          |
| Shelf                 | A numbered shelving unit inside a row.                                                                                           |
| Level                 | A numbered plank or tier inside a shelf. Samples sit on a level.                                                                 |
| Sample place          | One lab level. A lab has `row_count × shelves_per_row × levels_per_shelf` sample places, whatever the number of samples on each. |
| Sample                | A physical sample placed at one level. Fixed to that level for its lifetime.                                                     |
| Observation           | A reading event for one sample at one timestamp.                                                                                 |
| Bacteria observation  | A result for one bacterium within an observation.                                                                                |
| Growth level          | Qualitative result for a level bacterium: `high`, `medium`, `low`, or `none`.                                                    |
| Count                 | Non-negative integer result for a count bacterium.                                                                               |
| Cycle                 | A maximal run of positive observations for one sample+bacterium, terminated by `none` or `0`.                                    |
| NONE                  | Terminal growth level meaning the bacterium is absent and the cycle has ended.                                                   |
| Zero                  | Terminal count value meaning the bacterium is absent and the cycle has ended.                                                    |
| ISO week              | Monday–Sunday week defined by ISO 8601; week 1 contains January 4.                                                               |
| YEARWEEK              | MySQL-style week function. This document uses ISO-8601 semantics, equivalent to `YEARWEEK(date, 3)`, not MySQL's default mode 0. |
| Weekly state          | The latest observation for a sample+bacterium before the end of a selected ISO week, carried forward from earlier weeks.         |
| Lab map               | The read-only visualization of rows, shelves, levels, and samples with current bacteria summaries.                               |
| Week selector         | The control that chooses the ISO week whose end-of-week state the map displays.                                                  |
| Owner                 | Organization role with full organization and lab control.                                                                        |
| Admin                 | Organization role that manages labs but not the organization itself.                                                             |
| Member                | Organization role with read-only access.                                                                                         |
| Tenant                | An organization and its data, isolated from other organizations.                                                                 |
| CFU                   | Colony-forming unit. The count is stored as a raw integer; no unit is stored in this increment.                                  |
| SRS                   | Software Requirements Specification.                                                                                             |
| NFR                   | Non-functional requirement.                                                                                                      |
| FK                    | Foreign key.                                                                                                                     |
| PK                    | Primary key.                                                                                                                     |

### 1.5 References

| Reference                              | Description                                                                         |
| -------------------------------------- | ----------------------------------------------------------------------------------- |
| ISO/IEC/IEEE 29148:2018                | Systems and software engineering — Life cycle processes — Requirements engineering. |
| ISO 8601                               | Date and time representation; ISO week rules.                                       |
| IANA Time Zone Database                | Valid timezone identifiers.                                                         |
| Laravel 13 documentation               | Framework conventions, Eloquent, migrations, policies, validation.                  |
| Inertia.js 3 documentation             | Server-driven page props and forms.                                                 |
| Vue 3 documentation                    | Frontend components and reactivity.                                                 |
| PostgreSQL 16 documentation            | Types, constraints, indexes, `DISTINCT ON`.                                         |
| Repository `README.md`                 | Existing application setup and ClickHouse context.                                  |
| Repository `DESIGN.md`                 | Existing visual direction and UI conventions.                                       |
| Repository `AGENTS.md` / lerd guidance | Local development environment and conventions.                                      |

### 1.6 Document overview

This SRS follows the ISO/IEC/IEEE 29148 structure: introduction, stakeholders, system context, functional requirements, non-functional requirements, external interfaces, data requirements, constraints, assumptions, verification, traceability, and appendices.

Each functional requirement has a unique identifier, a statement using **shall**, a priority, a verification method, and a source decision reference. Requirements are grouped by feature area. The source decisions are listed in Appendix A. The traceability summary is in Section 11.

Requirement quality rules applied:

- Each requirement is necessary for the agreed scope.
- Each requirement is stated with **shall** (mandatory), **should** (recommended), or **may** (optional).
- Each requirement is verifiable by test, demonstration, inspection, or analysis.
- Requirements avoid implementation details except where the decision itself is architectural (for example, PostgreSQL and ISO week columns).
- Compound form requirements are kept together only when they describe one user action with one validation surface.

---

## 2. Stakeholders and user classes

### 2.1 Stakeholders

| Stakeholder          | Interest                                                                                 |
| -------------------- | ---------------------------------------------------------------------------------------- |
| Product owner        | Wants a data-heavy observations module seeded and viewable before manual entry is built. |
| Lab domain expert    | Wants the physical hierarchy and bacteria cycles to match real lab semantics.            |
| Backend engineer     | Needs a clear schema, observation contract, and seeding algorithm.                       |
| Frontend engineer    | Needs exact page behavior, map mark rules, and payload expectations.                     |
| QA engineer          | Needs verifiable acceptance criteria and a test plan.                                    |
| Operator / developer | Runs migrations and the stress seeder locally.                                           |
| Future analyst       | Will consume the observation data, eventually through ClickHouse.                        |

### 2.2 User classes

| User class    | Description                                                    | Authentication |
| ------------- | -------------------------------------------------------------- | -------------- |
| Guest         | Unauthenticated visitor.                                       | No             |
| Verified user | Authenticated user with a verified email address.              | Yes            |
| Owner         | Member of an organization with the owner role.                 | Yes            |
| Admin         | Member of an organization with the admin role.                 | Yes            |
| Member        | Member of an organization with the member role.                | Yes            |
| Non-member    | Authenticated user with no membership in a given organization. | Yes            |
| Seeder        | Console process that creates demo data.                        | Console        |

### 2.3 User class permissions

| Action                       | Owner                      | Admin | Member | Non-member | Guest |
| ---------------------------- | -------------------------- | ----- | ------ | ---------- | ----- |
| View organization            | Yes                        | Yes   | Yes    | No         | No    |
| View Manage Organizations    | Yes                        | Yes   | Yes    | Yes        | No    |
| Switch selected organization | Yes                        | Yes   | Yes    | No         | No    |
| Update organization          | Yes                        | No    | No     | No         | No    |
| Delete organization          | Yes                        | No    | No     | No         | No    |
| Manage members               | Yes (no UI this increment) | No    | No     | No         | No    |
| View labs                    | Yes                        | Yes   | Yes    | No         | No    |
| Create lab                   | Yes                        | Yes   | No     | No         | No    |
| Update lab                   | Yes                        | Yes   | No     | No         | No    |
| Delete lab                   | Yes                        | Yes   | No     | No         | No    |
| View lab map                 | Yes                        | Yes   | Yes    | No         | No    |
| View sample details          | Yes                        | Yes   | Yes    | No         | No    |
| Create organization          | Yes                        | Yes   | Yes    | Yes        | No    |

---

## 3. System context and operational environment

### 3.1 System context

The Observations module is part of the existing Laravel 13 application. It uses:

- PostgreSQL as the application database, and ClickHouse as the read-only analytics replica for the lab map.
- Inertia.js 3 with Vue 3 for pages.
- Laravel Fortify for authentication.
- Laravel Wayfinder for typed frontend routes.
- shadcn-vue components for UI.
- Pest for automated tests.
- PHPStan/Larastan, Pint, and the existing TypeScript checks for quality gates.

The module does not introduce a separate service, queue worker, or external API. The demo seeder runs as a console process against PostgreSQL.

### 3.2 Operational environment

| Environment       | Description                                                                                                                                                                  |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Local development | lerd with PostgreSQL, Redis, Mailpit, and ClickHouse services. The module needs PostgreSQL; ClickHouse must be running to migrate and to use the ClickHouse lab-map engines. |
| Testing           | PostgreSQL and ClickHouse test databases. Pest with `RefreshDatabase`; ClickHouse cases skip when the service is unreachable.                                                |
| Production        | Not specified in this increment. The demo seeder is not intended for production.                                                                                             |
| Browser           | Modern evergreen desktop and mobile browsers. Minimum supported width is 320 px.                                                                                             |

### 3.3 Existing system facts

- The application already has `users`, `sessions`, `cache`, `jobs`, `passkeys`, and `bacteria` tables.
- The `bacteria` catalog currently contains 10 foodborne organisms with name, short name, description, color, and icon.
- The existing bacteria pages are read-only and remain unchanged except for the new `observation_type` attribute.
- The existing `DatabaseSeeder` creates one user and calls `BacteriaSeeder`.
- The existing application uses Postgres by default (`DB_CONNECTION=pgsql`).
- The existing test environment uses Postgres (`DB_CONNECTION=pgsql` in `.env.testing`).
- The existing frontend uses Inertia pages, Wayfinder route helpers, and shadcn-vue components.
- The existing sidebar has Labs, Organizations, and Bacteria navigation items and an organization switcher. There is no dashboard page.

### 3.4 Data volume profile

The stress seeder is expected to produce:

| Metric                         | Measured / expected                                              |
| ------------------------------ | ---------------------------------------------------------------- |
| Organizations                  | 3                                                                |
| Labs                           | 9 (3 per organization: small, medium, large)                     |
| Sample places (levels) per lab | 1,200 (10×10×12), 3,200 (20×10×16), or 6,400 (20×20×16)          |
| Samples per lab                | 3,600, 9,600, or 19,200 (3 per level)                            |
| Total samples                  | 97,200 (32,400 per organization)                                 |
| Observation window             | 26 weeks, labs 20–26 weeks old, 0–4 week delay                   |
| Readings per sample per week   | 2–4 while bacteria are present                                   |
| Level bacteria per sample      | 0–6                                                              |
| Count bacteria per sample      | 0–3                                                              |
| Observations                   | 4,434,752 measured                                               |
| Bacteria observations          | 10,606,888 measured                                              |
| Filter placements              | 972 measured (36, 96, and 192 per lab; 1% of samples)            |
| Filter readings                | 8,333 measured (~70% of weeks from placement onward)             |
| PostgreSQL storage             | 2.1 GB measured including indexes                                |
| Lab map initial payload        | 3,600 summaries / ~636 KB (small) to 19,200 summaries / ~3.37 MB |

These are planning estimates, not hard limits. The schema and queries must remain usable at this scale.

A separate 10x stress profile is available with `php artisan demo:seed --scale=10`:

| Metric                   | 10x stress profile (measured)                 |
| ------------------------ | --------------------------------------------- |
| Readings per sample/week | 20–40                                         |
| Observations             | 43,647,057                                    |
| Bacteria observations    | 100,819,998                                   |
| PostgreSQL storage       | 44 GB after the state backfill (20 GB before) |
| ClickHouse raw table     | 762.51 MiB                                    |
| Summary snapshot         | 1,537,147 rows (same samples/weeks)           |
| Seed time                | ~7.7 hours                                    |
| ClickHouse load          | 137.3 s, including 31.7 s for snapshots       |

The 100M rows are across the whole replica. The largest lab has 19,817,218 raw rows, so a per-lab raw query scans its own rows, not the whole table. The whole-replica profile tests storage, `ch:load`, and the map's memory/payload path; a single-lab amplification is the way to make one query scan 100M rows.

---

## 4. Functional requirements

### 4.1 Organization management

| ID         | Requirement                                                                                                                                                        | Priority | Verification        | Source           |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | ------------------- | ---------------- |
| FR-ORG-001 | The system shall allow an authenticated, verified user to create an organization.                                                                                  | Must     | Test                | D8               |
| FR-ORG-002 | The system shall require an organization name of 1–255 characters that is unique across all organizations.                                                         | Must     | Test                | D8               |
| FR-ORG-003 | The system shall require an organization address of 1–1000 characters.                                                                                             | Must     | Test                | D9               |
| FR-ORG-004 | The system shall require an organization timezone that is a valid IANA timezone identifier.                                                                        | Must     | Test                | D5               |
| FR-ORG-005 | The system shall assign the creating user the owner role for the new organization in the same database transaction.                                                | Must     | Test                | D8, D17          |
| FR-ORG-006 | The system shall list only organizations in which the authenticated user has a membership.                                                                         | Must     | Test                | D8               |
| FR-ORG-007 | The Manage Organizations page shall display each organization's name, address, timezone, the current user's role, lab count, and the actions the user may perform. | Must     | Test                | D16              |
| FR-ORG-008 | The system shall allow an owner to update an organization's name, address, and timezone.                                                                           | Must     | Test                | D17, D20         |
| FR-ORG-009 | The system shall prevent an admin or member from updating an organization.                                                                                         | Must     | Test                | D20              |
| FR-ORG-010 | The system shall allow an owner to delete an organization after explicit confirmation.                                                                             | Must     | Test                | D17, D20         |
| FR-ORG-011 | The system shall prevent an admin or member from deleting an organization.                                                                                         | Must     | Test                | D20              |
| FR-ORG-012 | The system shall cascade deletion of an organization to its labs, rows, shelves, levels, samples, observations, and bacteria observations.                         | Must     | Test                | D16, FR-DATA-001 |
| FR-ORG-013 | The system shall display a confirmation dialog that names the organization and its lab count before deletion.                                                      | Must     | Test, Demonstration | D16              |
| FR-ORG-014 | The system shall store the selected organization in the server-side session, not in the URL.                                                                       | Must     | Test, Inspection    | D33              |
| FR-ORG-015 | The system shall provide an organization switcher in the main navigation listing only organizations the user belongs to.                                           | Must     | Test, Demonstration | D33              |
| FR-ORG-016 | The system shall keep the selected organization across requests until the user switches it or it becomes unavailable.                                              | Must     | Test                | D33              |
| FR-ORG-017 | The system shall fall back to the user's first organization, ordered by name, when the session selection is missing or invalid, and store that fallback.           | Must     | Test                | D33              |
| FR-ORG-018 | The system shall clear the stored selection when the selected organization is deleted.                                                                             | Must     | Test                | D33              |
| FR-ORG-019 | The system shall select an organization automatically when the user creates it.                                                                                    | Should   | Test                | D33              |
| FR-ORG-020 | The system shall redirect to the Labs page after the user switches the selected organization.                                                                      | Must     | Test                | D36              |

**Notes**

- The organization address is a single text field in this increment. Structured address fields are out of scope.
- The organization timezone applies to all its labs. Labs do not have independent timezones.
- Organization names are globally unique to keep routing and display unambiguous.
- Organizations are created, updated, and deleted from the Manage Organizations page, not from a detail page.
- Any authenticated, verified user may create an organization; only its owner may update or delete it.
- The organization switcher is in the sidebar header. Switching posts to a dedicated endpoint and redirects to the Labs page, so a lab from the previous organization is never left on screen.
- The selected organization is per user session. Two tabs share the selection; switching in one tab changes the other tab on its next request.
- The organization list on the Manage Organizations page is a personal membership list, not a global directory.

### 4.2 Membership and authorization

| ID          | Requirement                                                                                                                              | Priority | Verification | Source       |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------ | ------------ |
| FR-AUTH-001 | The system shall support the roles `owner`, `admin`, and `member` on an organization membership.                                         | Must     | Test         | D17          |
| FR-AUTH-002 | The system shall allow an owner to manage labs and to update or delete the organization.                                                 | Must     | Test         | D20          |
| FR-AUTH-003 | The system shall allow an admin to manage labs and to view the organization.                                                             | Must     | Test         | D20          |
| FR-AUTH-004 | The system shall allow a member to view the organization and its labs only.                                                              | Must     | Test         | D20          |
| FR-AUTH-005 | The system shall deny organization and lab access to authenticated users who are not members of the organization.                        | Must     | Test         | D8, D20      |
| FR-AUTH-006 | The system shall redirect unauthenticated users to the login page when they request an organization or lab route.                        | Must     | Test         | Existing app |
| FR-AUTH-007 | The system shall require a verified email address for organization and lab routes.                                                       | Must     | Test         | Existing app |
| FR-AUTH-008 | The system shall not provide membership invitation or membership management UI in this increment.                                        | Must     | Inspection   | D8           |
| FR-AUTH-009 | The system shall allow the demo seeder to assign memberships and roles.                                                                  | Must     | Test         | D8           |
| FR-AUTH-010 | The system shall hide create, edit, and delete controls from users whose role does not permit the action.                                | Must     | Test         | D20          |
| FR-AUTH-011 | The system shall allow any authenticated, verified user to create an organization and become its owner.                                  | Must     | Test         | D8, D17      |
| FR-AUTH-012 | The system shall enforce all authorization server-side through policies, independently of the UI.                                        | Must     | Test         | D20          |
| FR-AUTH-013 | The system shall allow a user to select only an organization in which they have a membership.                                            | Must     | Test         | D33          |
| FR-AUTH-014 | The system shall return 403 for a lab route when the user is not a member of the lab's organization.                                     | Must     | Test         | D8, D20      |
| FR-AUTH-015 | The system shall return 404 for a lab route when the user is a member of the lab's organization but it is not the selected organization. | Must     | Test         | D33          |

**Notes**

- A non-member receives HTTP 403 for organization and lab routes. The system must not leak whether the organization exists.
- A member who requests a lab from an organization other than the selected one receives 404, so the selected organization behaves as the working context.
- The UI hides forbidden actions, but the policy remains the source of truth.
- There is no owner-transfer or member-removal UI in this increment.
- The seeder assigns the existing user and demo users to organizations with a mix of roles.

### 4.3 Lab management and hierarchy

| ID         | Requirement                                                                                                                                     | Priority | Verification     | Source          |
| ---------- | ----------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ---------------- | --------------- |
| FR-LAB-001 | The system shall allow an owner or admin to create a lab under the selected organization.                                                       | Must     | Test             | D20             |
| FR-LAB-002 | The system shall require a lab name of 1–255 characters that is unique within its organization.                                                 | Must     | Test             | D10             |
| FR-LAB-003 | The system shall accept an optional lab code of 1–50 characters that is unique within its organization when present.                            | Must     | Test             | D10             |
| FR-LAB-004 | The system shall accept optional lab notes of up to 2000 characters.                                                                            | Must     | Test             | D10             |
| FR-LAB-005 | The system shall require integer `row_count`, `shelves_per_row`, and `levels_per_shelf` values from 1 to 20 on lab creation.                    | Must     | Test             | D15, constraint |
| FR-LAB-006 | The system shall generate lab rows numbered 1 through `row_count` in the same transaction as the lab.                                           | Must     | Test             | D1, D2          |
| FR-LAB-007 | The system shall generate lab shelves numbered 1 through `shelves_per_row` for each generated row.                                              | Must     | Test             | D1, D2          |
| FR-LAB-008 | The system shall generate lab levels numbered 1 through `levels_per_shelf` for each generated shelf.                                            | Must     | Test             | D1, D2          |
| FR-LAB-009 | The system shall prevent changes to `row_count`, `shelves_per_row`, and `levels_per_shelf` after lab creation.                                  | Must     | Test             | D15             |
| FR-LAB-010 | The system shall display a lab's structure summary (rows × shelves × levels), creation date, and sample count on the Labs page and the lab map. | Must     | Test             | D16             |
| FR-LAB-011 | The system shall allow an owner or admin to update a lab's name, code, and notes.                                                               | Must     | Test             | D20             |
| FR-LAB-012 | The system shall prevent a member from creating, updating, or deleting a lab.                                                                   | Must     | Test             | D20             |
| FR-LAB-013 | The system shall allow an owner or admin to delete a lab after explicit confirmation.                                                           | Must     | Test             | D20             |
| FR-LAB-014 | The system shall cascade deletion of a lab to its rows, shelves, levels, samples, observations, and bacteria observations.                      | Must     | Test             | FR-DATA-001     |
| FR-LAB-015 | The system shall list a lab's rows, shelves, levels, and samples on the lab map.                                                                | Must     | Test             | D21             |
| FR-LAB-016 | The system shall use the lab creation date to determine the first week offered by the week selector.                                            | Must     | Test             | D29             |
| FR-LAB-017 | The system shall display a preview of the total level count on the lab creation form.                                                           | Should   | Test             | D15             |
| FR-LAB-018 | The system shall display the fixed structure counts as read-only on the lab edit form.                                                          | Must     | Test             | D15             |
| FR-LAB-019 | The Labs page shall list only the labs of the selected organization.                                                                            | Must     | Test             | D33             |
| FR-LAB-020 | The system shall address lab pages as `/labs/{lab}` and shall not include an organization identifier in the URL.                                | Must     | Test, Inspection | D33             |

**Notes**

- The 1–20 limit per dimension protects the UI and database from accidental massive hierarchies. The stress seeder uses three sizes per organization: 10×10×12, 20×10×16, and 20×20×16 (rows × shelves × levels), which is five times the previous 2,160-place dataset.
- The hierarchy tables support non-uniform layouts, but the form only creates uniform grids.
- Lab structure counts are creation parameters and are immutable. They are stored on the lab for display and audit.
- The lab detail page is the lab map, not a separate structure page. Labs are reached from the Labs page and addressed as `/labs/{lab}`.
- The Labs page, lab map, edit form, sample details request, and delete action all operate on the selected organization; the organization id is not part of lab URLs.
- Lab creation reads the organization from the selected-organization context, not from the URL.
- Lab creation and hierarchy generation must be atomic. A failure must leave no partial lab.

### 4.4 Samples

| ID         | Requirement                                                                              | Priority | Verification | Source      |
| ---------- | ---------------------------------------------------------------------------------------- | -------- | ------------ | ----------- |
| FR-SMP-001 | The system shall associate each sample with exactly one lab and one lab level.           | Must     | Test         | D3          |
| FR-SMP-002 | The system shall assign each sample a code that is unique within its lab.                | Must     | Test         | D3          |
| FR-SMP-003 | The system shall format sample codes as `R{row}-S{shelf}-L{level}-{sequence:02}`.        | Must     | Unit test    | D3          |
| FR-SMP-004 | The system shall assign sample sequence numbers starting at `01` within each level.      | Must     | Unit test    | D3          |
| FR-SMP-005 | The system shall keep a sample fixed to its level for its lifetime.                      | Must     | Test         | D3          |
| FR-SMP-006 | The system shall allow multiple samples per level.                                       | Must     | Test         | D3          |
| FR-SMP-007 | The system shall not provide sample create, edit, or delete UI in this increment.        | Must     | Inspection   | D16         |
| FR-SMP-008 | The system shall allow the demo seeder and future ingestion processes to create samples. | Must     | Test         | D3          |
| FR-SMP-009 | The system shall store a denormalized `lab_id` on each sample.                           | Must     | Inspection   | Performance |

**Notes**

- A sample is a physical item. Replacing a sample means creating a new sample at the same level, preserving the old sample's observation history.
- Sample codes are unique per lab, not globally. The lab ID disambiguates.
- No sample type, source, or collected date fields exist in this increment.
- The sample sequence is per level. A level with three samples has codes ending `-01`, `-02`, and `-03`.

### 4.5 Observations

| ID         | Requirement                                                                                                                                                         | Priority | Verification        | Source         |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------- | -------------- |
| FR-OBS-001 | The system shall store an observation as a reading event for one sample at one `observed_at` timestamp.                                                             | Must     | Test                | D4             |
| FR-OBS-002 | The system shall store `observed_at` as a PostgreSQL `timestamptz` in UTC.                                                                                          | Must     | Inspection          | D5             |
| FR-OBS-003 | The system shall store `iso_year` and `iso_week` on each observation.                                                                                               | Must     | Test                | D5             |
| FR-OBS-004 | The system shall compute `iso_year` and `iso_week` from `observed_at` in the organization's timezone at write time.                                                 | Must     | Unit test           | D5             |
| FR-OBS-005 | The system shall allow multiple observations per sample per ISO week.                                                                                               | Must     | Test                | D4             |
| FR-OBS-006 | The system shall store one or more bacteria observations per observation.                                                                                           | Must     | Test                | D4             |
| FR-OBS-007 | The system shall enforce at most one bacteria observation per observation and bacterium.                                                                            | Must     | Test                | Data integrity |
| FR-OBS-008 | The system shall require `growth_level` for bacteria with `observation_type = level`.                                                                               | Must     | Test                | D6, D18        |
| FR-OBS-009 | The system shall allow `growth_level` values `high`, `medium`, `low`, and `none` for level bacteria.                                                                | Must     | Unit test           | D6             |
| FR-OBS-010 | The system shall require a non-negative integer `count` for bacteria with `observation_type = count`.                                                               | Must     | Test                | D7, D19        |
| FR-OBS-011 | The system shall require exactly one of `growth_level` or `count` to be set on a bacteria observation.                                                              | Must     | Test, DB inspection | Data integrity |
| FR-OBS-012 | The system shall record a level bacterium's `none` value only when a previously positive bacterium becomes absent.                                                  | Must     | Test                | D12            |
| FR-OBS-013 | The system shall record a count bacterium's `0` value only when a previously positive bacterium becomes absent.                                                     | Must     | Test                | D19            |
| FR-OBS-014 | The system shall not store bacteria observation rows for a bacterium while it remains absent.                                                                       | Must     | Test                | D12, D19       |
| FR-OBS-015 | The system shall allow a new positive observation after a `none` or `0` observation, starting a new cycle.                                                          | Must     | Test                | D12, D19       |
| FR-OBS-016 | The system shall record, in a reading, every bacterium that is present at that reading and every bacterium that transitions from present to absent at that reading. | Must     | Test                | D4             |
| FR-OBS-017 | The system shall not store an observation that has no bacteria observations.                                                                                        | Must     | Test                | D4             |
| FR-OBS-018 | The system shall derive cycles from the observation stream and shall not store a cycle entity or cycle number.                                                      | Must     | Inspection          | D14            |
| FR-OBS-019 | The system shall not provide manual observation creation, editing, or deletion UI in this increment.                                                                | Must     | Inspection          | D16            |
| FR-OBS-020 | The system shall enforce the observation data rules for future ingestion through application validation.                                                            | Must     | Inspection          | D11            |
| FR-OBS-021 | The system shall enforce at most one observation per sample and `observed_at` timestamp.                                                                            | Must     | Test                | Data integrity |
| FR-OBS-022 | The system shall store `observation_type` on each bacterium as `level` or `count`.                                                                                  | Must     | Test                | D18            |
| FR-OBS-023 | The system shall classify Salmonella, E. coli, Listeria, Campylobacter, S. aureus, and B. cereus as level bacteria.                                                 | Must     | Test                | D18            |
| FR-OBS-024 | The system shall classify C. perfringens, C. botulinum, Vibrio, and Yersinia as count bacteria.                                                                     | Must     | Test                | D18            |
| FR-OBS-025 | The system shall reject an observation whose `observed_at` is in the future.                                                                                        | Should   | Test                | Data quality   |

**Notes**

- A reading includes every currently present bacterium, even if its level or count is unchanged. This creates a dense timeline for present bacteria and a sparse timeline for absent bacteria.
- A level bacterium's `none` is a recorded event that closes a cycle. It is not a blank. After `none`, no rows exist for that bacterium until it reappears.
- A count bacterium's `0` is the terminal event that closes a cycle. After `0`, no rows exist until it reappears.
- A bacterium that has never been positive has no rows. This is equivalent to "not tested / not present" for the map.
- The observation parent is not stored if it would have zero bacteria observations.
- The application enforces the relationship between the bacterium's `observation_type` and the result field. The database enforces exactly one of the two result fields.
- `observed_at` is stored in UTC. The organization timezone is used only to derive the ISO week and to render labels.

### 4.6 Weekly state and cycles

| ID        | Requirement                                                                                                                                                                    | Priority | Verification    | Source   |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | --------------- | -------- |
| FR-WK-001 | The system shall define the state of a sample+bacterium for an ISO week as the latest observation with `observed_at` before the end of that week in the organization timezone. | Must     | Unit test, Test | D13      |
| FR-WK-002 | The system shall carry forward the latest prior observation into weeks with no observation.                                                                                    | Must     | Unit test, Test | D13      |
| FR-WK-003 | The system shall treat a sample+bacterium with no observation ever as having no state.                                                                                         | Must     | Unit test, Test | D13      |
| FR-WK-004 | The system shall treat a `none` or `0` latest observation as absent.                                                                                                           | Must     | Unit test, Test | D12, D19 |
| FR-WK-005 | The system shall use the end of the selected ISO week (Sunday 23:59:59.999999 in the organization timezone) as the state cutoff.                                               | Must     | Unit test       | D13      |
| FR-WK-006 | The system shall use the latest observation so far for the current, incomplete ISO week.                                                                                       | Must     | Test            | D30      |
| FR-WK-007 | The system shall define ISO weeks as Monday–Sunday, with week 1 containing January 4.                                                                                          | Must     | Unit test       | D5       |
| FR-WK-008 | The system shall not rewrite historical `iso_year` and `iso_week` values when an organization's timezone changes.                                                              | Must     | Inspection      | D5       |
| FR-WK-009 | The system shall derive a cycle as a maximal run of positive observations terminated by `none` or `0`.                                                                         | Must     | Unit test       | D14      |
| FR-WK-010 | The system shall allow a new cycle after a `none` or `0` observation.                                                                                                          | Must     | Unit test, Test | D12, D19 |

**Notes**

- The weekly state is a "last observation carried forward" model. A bacterium does not become unknown just because it was not retested.
- The current week is partial. Its state is the latest observation so far, not a future end-of-week value.
- If an organization's timezone is corrected, historical week columns remain as originally computed. Recomputing history is out of scope.
- Cycles are a derived interpretation of the event stream. No cycle table or cycle number exists.

### 4.7 Lab map and week selector

| ID         | Requirement                                                                                                                                                                          | Priority | Verification               | Source   |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------- | -------------------------- | -------- |
| FR-MAP-001 | The system shall display a full lab map for a lab as a row/shelf/level matrix, addressing every level through the hierarchy.                                                         | Must     | Test, Demonstration        | D21, D28 |
| FR-MAP-002 | The system shall display every level in the lab without pagination or collapsing and keep every sample reachable from its level.                                                     | Must     | Demonstration              | D21, D28 |
| FR-MAP-003 | The system shall offer a week selector containing every ISO week from the lab creation week through the current ISO week in the organization timezone.                               | Must     | Test                       | D29, D30 |
| FR-MAP-004 | The system shall default the week selector to the current ISO week.                                                                                                                  | Must     | Test                       | D30      |
| FR-MAP-005 | The system shall include weeks with no observations in the selector.                                                                                                                 | Must     | Test                       | D29      |
| FR-MAP-006 | The system shall accept the selected week as a `YYYY-Www` query parameter.                                                                                                           | Must     | Test                       | D24      |
| FR-MAP-007 | The system shall fall back to the current week when the query parameter is missing or invalid.                                                                                       | Must     | Test                       | D24      |
| FR-MAP-008 | The system shall display only bacteria that are present in the selected week's state.                                                                                                | Must     | Test                       | D21      |
| FR-MAP-009 | The system shall encode each level as one map mark using a colour and a distinct shape for high, medium, low, count, and empty.                                                      | Must     | Test                       | D25      |
| FR-MAP-010 | The system shall derive a level's mark from the highest growth level among its samples, otherwise count presence, otherwise empty.                                                   | Must     | Test                       | D25      |
| FR-MAP-011 | The system shall not display sample codes, bacterium names, growth-level words, or numeric counts in the map.                                                                        | Must     | Test                       | D25      |
| FR-MAP-012 | The system shall use its own colour and shape for count presence when the level has no higher growth level.                                                                          | Must     | Test                       | D25      |
| FR-MAP-013 | The system shall show the highest growth level for a level that mixes level and count bacteria; the modal shall list every sample and bacterium.                                     | Must     | Test                       | D25      |
| FR-MAP-014 | The system shall order growth levels HIGH > MEDIUM > LOW for the highest-level calculation.                                                                                          | Must     | Unit test                  | D25      |
| FR-MAP-015 | The system shall display a muted empty mark for a level with no present bacteria.                                                                                                    | Must     | Test, Demonstration        | D25      |
| FR-MAP-016 | The system shall open a details modal when a level mark is activated.                                                                                                                | Must     | Test, Demonstration        | D21      |
| FR-MAP-017 | The details modal shall list the level's samples with their codes and states and, for the selected sample, every present bacterium with its name, icon or color, and level or count. | Must     | Test, Demonstration        | D21      |
| FR-MAP-018 | The lab map payload shall include the map structure and the selected week's per-sample summary arrays, without bacterium metadata.                                                   | Must     | Test                       | D26      |
| FR-MAP-019 | The system shall load sample bacteria details through the sampleDetails Inertia optional prop on demand.                                                                             | Must     | Test                       | D26      |
| FR-MAP-020 | The sample details optional prop shall apply the same selected-week cutoff as the map.                                                                                               | Must     | Test                       | D26      |
| FR-MAP-021 | The sample details optional prop shall require membership in the lab's organization.                                                                                                 | Must     | Test                       | D20      |
| FR-MAP-022 | The system shall display a legend for the five mark states using the same colour and shape as the map.                                                                               | Must     | Demonstration              | D25      |
| FR-MAP-023 | The system shall display an empty state when the lab has no observations.                                                                                                            | Must     | Test, Demonstration        | D21      |
| FR-MAP-024 | The system shall be responsive from 320 px to 1280 px and above.                                                                                                                     | Must     | Demonstration              | NFR      |
| FR-MAP-025 | The system shall not rely on color alone to convey growth level or presence.                                                                                                         | Must     | Inspection, Demonstration  | NFR      |
| FR-MAP-026 | The system shall load the lab map within 2 seconds at p95 for 3,600 samples on the local environment.                                                                                | Must     | Analysis, Performance test | NFR      |
| FR-MAP-027 | The system shall not provide observation create, edit, or delete controls in this increment.                                                                                         | Must     | Inspection                 | D16      |
| FR-MAP-028 | The system shall load the lab map within 6 seconds at p95 for the largest seeded lab (6,400 levels, 19,200 samples) on the local environment with the ClickHouse snapshot engine.    | Must     | Analysis, Performance test | NFR      |
| FR-MAP-029 | The lab map payload shall include the selected week's per-sample summary arrays; the sample details optional prop returns bacterium metadata for the selected sample.                | Must     | Test                       | D26      |
| FR-MAP-030 | The system shall render one map mark per level, not one cell per sample, so the largest seeded lab stays interactive.                                                                | Must     | Demonstration              | NFR      |
| FR-MAP-031 | The system shall label rows in the map's top-left corner, shelves in the column headers, and levels in the left rail.                                                                | Must     | Demonstration              | D25      |
| FR-MAP-032 | The system shall keep the row corner and level rail pinned while the map scrolls horizontally.                                                                                       | Must     | Demonstration              | NFR      |

**Notes**

- The week selector starts at the ISO week containing the lab's `created_at` and ends at the current ISO week in the organization timezone. Both endpoints are inclusive.
- The selected week is carried in the URL so the view is shareable and reloadable.
- An invalid or out-of-range week falls back to the current week. The page does not error.
- The page returns the structure and the selected week's summaries in one request. Each summary is an array and carries a `single` object only when exactly one bacterium is present. The `sampleDetails` optional prop returns the bacteria and filter for the selected sample and week; there is no row-level summary request.
- The map renders one mark per level, combining its samples: the highest growth level wins, count presence is shown only when no level bacterium is present, and otherwise the mark is empty.
- The mark vocabulary is fixed: high is a red square, medium an amber triangle, low a green circle, count a blue diamond, and empty a muted ring. The same shapes appear in the legend, so colour is never the only signal.
- Sample codes, bacterium names, growth words, and numeric counts are not rendered in the map. Activating a level opens the details modal, which lists its samples with codes, present counts, and states; selecting a sample loads its present bacteria and values.
- The map is read-only. There is no observation creation UI.
- Sample details use the same lab map URL with a POST body (`sample` id) and the `sampleDetails` optional prop; there is no separate JSON endpoint and no organization id in the URL.

### 4.8 Seeding

| ID          | Requirement                                                                                                                                                                                                      | Priority | Verification               | Source |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | -------------------------- | ------ |
| FR-SEED-001 | The system shall provide a dedicated `DemoStressSeeder` class.                                                                                                                                                   | Must     | Inspection                 | D22    |
| FR-SEED-002 | The system shall run the stress seeder only through an explicit class invocation or command.                                                                                                                     | Must     | Inspection                 | D22    |
| FR-SEED-003 | The system shall not invoke the stress seeder from `DatabaseSeeder`.                                                                                                                                             | Must     | Inspection                 | D22    |
| FR-SEED-004 | The stress seeder shall truncate demo organizations, memberships, labs, rows, shelves, levels, samples, observations, and bacteria observations before seeding.                                                  | Must     | Test                       | D22    |
| FR-SEED-005 | The stress seeder shall preserve the `users` table and the `bacteria` catalog.                                                                                                                                   | Must     | Test                       | D22    |
| FR-SEED-006 | The stress seeder shall seed the bacteria catalog if it is empty.                                                                                                                                                | Must     | Test                       | D22    |
| FR-SEED-007 | The stress seeder shall create three organizations in `America/Sao_Paulo`, `Europe/Lisbon`, and `America/New_York`.                                                                                              | Must     | Test                       | D23    |
| FR-SEED-008 | The stress seeder shall create three labs per organization.                                                                                                                                                      | Must     | Test                       | D23    |
| FR-SEED-009 | The stress seeder shall create three labs per organization with sizes 10×10×12, 20×10×16, and 20×20×16 (rows × shelves × levels), so every organization has a lab with at least 1,000 sample places.             | Must     | Test                       | D23    |
| FR-SEED-010 | The stress seeder shall create three samples per level.                                                                                                                                                          | Must     | Test                       | D23    |
| FR-SEED-011 | The stress seeder shall randomize each lab's `created_at` between 26 and 20 weeks before the current date.                                                                                                       | Must     | Test                       | D31    |
| FR-SEED-012 | The stress seeder shall delay the first readings by a random 0–4 weeks after lab creation.                                                                                                                       | Must     | Test                       | D32    |
| FR-SEED-013 | The stress seeder shall generate observations for a 26-week window ending on the last completed Sunday in each organization's timezone.                                                                          | Must     | Test                       | D23    |
| FR-SEED-014 | The stress seeder shall generate 2–4 readings per sample per week while the sample has present bacteria.                                                                                                         | Must     | Test                       | D23    |
| FR-SEED-015 | The stress seeder shall assign each sample 0–6 level bacteria and 0–3 count bacteria.                                                                                                                            | Must     | Test                       | D23    |
| FR-SEED-016 | The stress seeder shall generate level-bacteria cycles of 2–8 weeks separated by 1–6 week gaps.                                                                                                                  | Must     | Test                       | D23    |
| FR-SEED-017 | The stress seeder shall generate growth levels with LOW 45%, MEDIUM 35%, and HIGH 20% probability.                                                                                                               | Must     | Test                       | D23    |
| FR-SEED-018 | The stress seeder shall generate counts log-uniformly between 10^1 and 10^6.                                                                                                                                     | Must     | Test                       | D23    |
| FR-SEED-019 | The stress seeder shall leave approximately 10% of samples with no positive bacteria.                                                                                                                            | Must     | Test                       | D23    |
| FR-SEED-020 | The stress seeder shall generate reappearances after `none` or `0` for some bacteria.                                                                                                                            | Must     | Test                       | D23    |
| FR-SEED-021 | The stress seeder shall use a fixed random seed for reproducible output.                                                                                                                                         | Must     | Inspection                 | D22    |
| FR-SEED-022 | The stress seeder shall use chunked bulk inserts for observations and bacteria observations.                                                                                                                     | Must     | Inspection                 | NFR    |
| FR-SEED-023 | The stress seeder shall report progress to the console.                                                                                                                                                          | Should   | Demonstration              | D22    |
| FR-SEED-024 | The stress seeder shall be idempotent by truncating and rebuilding demo data.                                                                                                                                    | Must     | Test                       | D22    |
| FR-SEED-025 | The stress seeder shall complete within 60 minutes and use no more than 512 MB of memory on the local environment.                                                                                               | Must     | Analysis, Performance test | NFR    |
| FR-SEED-026 | The stress seeder shall invoke `FilterSeeder` after lab generation, and `FilterSeeder` shall be runnable standalone to rebuild filters and readings.                                                             | Must     | Inspection                 | D43    |
| FR-SEED-027 | The stress seeder shall accept an observation scale multiplier; scale 10 shall produce roughly 10x readings (43,647,057 observations and 100,819,998 bacteria observations measured) for the 10x stress profile. | Should   | Demonstration              | D44    |

**Notes**

- The seeder is destructive to demo organizations and their data. It is documented as a local/demo tool and must not be run against production data.
- Users and the bacteria catalog survive truncation. The seeder attaches existing users to the new organizations with a mix of owner, admin, and member roles.
- The stress seeder may call the existing `BacteriaSeeder` when the catalog is empty.
- The random seed is fixed so repeated runs produce the same dataset.
- The seeder generates observations only where there is something to record. A reading with no present bacteria and no transitions is skipped.
- The seeder computes `iso_year` and `iso_week` in each organization's timezone, then stores `observed_at` in UTC.
- The seeder uses explicit bigint IDs for bulk-inserted observation rows and resets the PostgreSQL sequences after insertion.
- Each lab's hierarchy is generated inside one database transaction; observation batches commit in 1,000-observation chunks instead of one 100M-row transaction. On the local environment the normal dataset seeds in about 47 minutes with a peak of about 43 MB, within FR-SEED-025. The 10x stress profile (`demo:seed --scale=10`) takes roughly 8 hours and is not bound by the 60-minute normal-seed target.

### 4.9 Navigation and UI

| ID        | Requirement                                                                                                                                 | Priority | Verification        | Source       |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------- | ------------ |
| FR-UI-001 | The system shall provide Labs, Organizations, and Bacteria items in the main sidebar navigation.                                            | Must     | Demonstration       | D16, D34     |
| FR-UI-002 | The system shall display breadcrumbs for Lab and Manage Organizations pages.                                                                | Should   | Demonstration       | D16          |
| FR-UI-003 | The system shall display validation errors inline on all forms.                                                                             | Must     | Test                | Existing app |
| FR-UI-004 | The system shall require explicit confirmation before deleting an organization or lab.                                                      | Must     | Test                | D16          |
| FR-UI-005 | The system shall display an empty state when the user has no organizations or a lab has no samples or observations.                         | Should   | Demonstration       | D16          |
| FR-UI-006 | The system shall hide actions the current role cannot perform.                                                                              | Must     | Test                | D20          |
| FR-UI-007 | The system shall follow the existing Vue, Inertia, and shadcn-vue visual conventions.                                                       | Should   | Inspection          | Existing app |
| FR-UI-008 | The system shall not use emojis in the UI.                                                                                                  | Should   | Inspection          | DESIGN.md    |
| FR-UI-009 | The organization form shall provide an IANA timezone select.                                                                                | Must     | Demonstration       | D5           |
| FR-UI-010 | The lab creation form shall display the computed total level count before submission.                                                       | Should   | Demonstration       | D15          |
| FR-UI-011 | The lab edit form shall display the fixed structure counts as read-only.                                                                    | Must     | Test                | D15          |
| FR-UI-012 | The system shall not render a Dashboard page or a Dashboard navigation item.                                                                | Must     | Test, Inspection    | D34          |
| FR-UI-013 | The system shall place the organization switcher at the top of the sidebar in menu mode and hide it when the sidebar is collapsed to icons. | Must     | Demonstration       | D33          |
| FR-UI-014 | The Manage Organizations page shall offer create, edit, and delete actions allowed by the user's role.                                      | Must     | Test, Demonstration | D33          |

**Notes**

- The UI uses the existing app shell, sidebar, breadcrumbs, and shadcn-vue primitives.
- The organization switcher sits in the sidebar header and is hidden when the sidebar is collapsed to icons; the same selection is used by every page.
- The Dashboard page and its navigation item are removed. Sign-in and email verification redirect to the Labs page.
- The map uses a colour-and-shape scale; the legend repeats the shapes so colour is never the only indicator.
- The details modal uses the existing dialog component and offers both the corner close control and a footer Close button.
- Delete confirmations name the entity and its dependent counts.

### 4.10 Data integrity and retention

| ID          | Requirement                                                                                                                                                                                                                                          | Priority | Verification     | Source         |
| ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ---------------- | -------------- |
| FR-DATA-001 | The system shall define foreign keys with `ON DELETE CASCADE` for organization → lab → row → shelf → level → sample → observation → bacteria observation.                                                                                            | Must     | Inspection, Test | D16            |
| FR-DATA-002 | The system shall enforce unique constraints on organization name, lab name per organization, lab code per organization, row number per lab, shelf number per row, level number per shelf, sample code per lab, and bacterium result per observation. | Must     | Inspection, Test | Data integrity |
| FR-DATA-003 | The system shall enforce check constraints for exactly one of `growth_level`/`count`, non-negative counts, `iso_week` between 1 and 53, and `iso_year` greater than 0.                                                                               | Must     | Inspection, Test | Data integrity |
| FR-DATA-004 | The system shall create organizations, memberships, and labs with their hierarchies inside database transactions.                                                                                                                                    | Must     | Test             | Data integrity |
| FR-DATA-005 | The system shall not use soft deletes for organizations, labs, samples, or observations in this increment.                                                                                                                                           | Must     | Inspection       | D16            |
| FR-DATA-006 | The system shall index observations by `(sample_id, observed_at)` and bacteria observations by `(observation_id, bacteria_id)` and the state index `(sample_id, bacteria_id, observed_at DESC, id DESC)`.                                            | Must     | Inspection       | Performance    |
| FR-DATA-007 | The system shall use bigint primary keys for all new tables.                                                                                                                                                                                         | Must     | Inspection       | D11            |
| FR-DATA-008 | The system shall store explicit `iso_year` and `iso_week` on observations and a denormalized `lab_id` on samples to support the ClickHouse replica.                                                                                                  | Must     | Inspection       | D11            |
| FR-DATA-009 | Application writes shall target PostgreSQL only; ClickHouse shall be written only by the `ch:load` replica command.                                                                                                                                  | Must     | Inspection       | D11            |
| FR-DATA-010 | The system shall retain observations until explicitly deleted through organization or lab cascade or the demo seeder.                                                                                                                                | Must     | Inspection       | D16            |

**Notes**

- Cascade deletes are synchronous. With millions of rows, deleting a large organization or lab may take time. This is accepted for the demo increment and can be moved to a queued job later.
- There is no soft-delete or archive path in this increment.
- The demo seeder is the only supported bulk deletion path for observations.

### 4.11 ClickHouse lab map engine

| ID        | Requirement                                                                                                                                                                                                   | Priority | Verification        | Source        |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------- | ------------- |
| FR-CH-001 | The system shall store a denormalized `lab_observations` MergeTree table in ClickHouse with lab, sample, bacterium, timestamp, ISO week, week start, growth result, and bacterium metadata.                   | Must     | Inspection          | D37           |
| FR-CH-002 | The system shall truncate the ClickHouse replica tables and rebuild them from PostgreSQL with the `php artisan ch:load` command using ClickHouse's `postgresql()` table function.                             | Must     | Demonstration       | D37, D41, D45 |
| FR-CH-003 | The ClickHouse engine shall compute each sample+bacterium's current state with `argMax(value, observed_at)` filtered to the selected week start or earlier.                                                   | Must     | Test                | D37           |
| FR-CH-004 | The ClickHouse engine shall compute the per-sample summary with conditional aggregates (`countIf`, `maxIf`, `anyIf`) and return the same shape as the PostgreSQL engine.                                      | Must     | Test                | D37           |
| FR-CH-005 | The sample bacteria endpoint shall use the currently selected engine.                                                                                                                                         | Must     | Test                | D38           |
| FR-CH-006 | The lab map shall offer a PostgreSQL / ClickHouse raw / ClickHouse snapshot switch that stores the selection in the server-side session and defaults to PostgreSQL.                                           | Must     | Test, Demonstration | D38           |
| FR-CH-007 | The lab map shall display the cumulative state-query time for the loaded rows for each engine that has served the current lab and week.                                                                       | Should   | Demonstration       | D38           |
| FR-CH-008 | A ClickHouse query failure shall show an error on the lab map without changing the PostgreSQL engine.                                                                                                         | Should   | Test                | D38           |
| FR-CH-009 | The system shall maintain a `lab_weekly_summaries` snapshot with one plain row per sample with present bacteria or a placed filter per week, built from the raw observation and filter tables.                | Must     | Test, Inspection    | D41           |
| FR-CH-010 | The ClickHouse snapshot engine shall return the same summaries through a direct `lab_id + week_start` read and delegate the sample details modal to the raw engine.                                           | Must     | Test                | D41           |
| FR-CH-011 | The system shall provide a `ch:bench` command that measures each engine and supports a synthetic 5x per-lab scale for the ClickHouse engines.                                                                 | Should   | Demonstration       | D42           |
| FR-CH-012 | The ClickHouse schema shall be defined in versioned migrations under `database/migrations` using the package Blueprint and applied by `php artisan migrate`; `ch:load` shall check the schema before loading. | Must     | Inspection, Test    | D45           |

**Notes**

- The ClickHouse tables are read-only replicas. PostgreSQL remains the source of truth and the only write path.
- The load command truncates the raw, filter, and summary tables and reloads them; it is run explicitly after re-seeding PostgreSQL.
- ClickHouse migrations live in the regular `database/migrations` directory with `protected $connection = 'clickhouse'`; `php artisan migrate` runs them alongside the PostgreSQL migrations, so ClickHouse must be running when migrating. The migrations drop and recreate their tables, which is the adoption path from old loader-created tables.
- The loader DML is inline in `LoadLabObservationsCommand`; the per-week snapshot statements live in `App\Services\LabMap\Replica\WeeklySnapshot`, which the loader and the equivalence test share. The four tables have Eloquent models under `App\Models\ClickHouse`.
- The ClickHouse engines and the PostgreSQL engine must return identical summaries and sample payloads. The automated tests compare them on small fixtures; the seeded dataset was checked exhaustively (452 summary checks across all labs/weeks and 1,620 drawer checks, zero mismatches).
- The 10x raw table has 100,819,998 rows and 762.51 MiB on disk; the filter table has 972 rows and 16.98 KiB, filter readings have 7,906 rows and 99.55 KiB, and the summary snapshot has 1,537,147 rows and 21.82 MiB. The 10x load took 137.3 s including 31.7 s for snapshots.
- The benchmark measured 1x medians of PostgreSQL 8,053 ms (8,176 ms p95), ClickHouse raw 878 ms (951 ms p95), and snapshot 144 ms (165 ms p95). On synthetic per-lab 5x data: raw 3,946 ms (4,085 ms p95), snapshot 656 ms (693 ms p95).
- PostgreSQL remains the default engine. Its full-lab query median is 8,053 ms after the denormalized state index, so the PostgreSQL page takes ~10–12 s; the snapshot page takes ~0.75 s. NFR-PERF-008 (raw, Must) passes at 951 ms p95; NFR-PERF-009 (snapshot on synthetic 5x, Should) measures 693 ms p95 against its 500 ms target.
- The one-request map page for the largest lab (6,400 levels, 19,200 samples) returns in ~0.75 s with the snapshot engine, ~1.6 s with raw ClickHouse, and ~10–12 s with PostgreSQL, with a ~3.39 MB payload; the engine queries alone measure snapshot 144 ms, raw 878 ms, and PostgreSQL 8,053 ms median. The 3,600-sample lab measures ~644 KB, ~239 ms TTFB and ~313 ms load. An async structure-first variant (visible-row summaries plus a deferred full-summary prop) was measured and removed: snapshot took ~4.5 s for the full map and cold PostgreSQL row batches reached 39 s.
- The 5x benchmark duplicates the selected lab five times with shifted sample ids and filter ids; it does not re-seed PostgreSQL. The raw table keeps a `minmax` skip index on `sample_id` from the removed row-level experiment; the page no longer queries row subsets. The four bench tables are created by a ClickHouse migration and are only truncated and filled by `ch:bench`.
- The weekly AggregatingMergeTree rollup was evaluated and removed in 1.6: it was slower than the raw scan, so the snapshot is built directly from the raw table.
- The engine selection is per session, so all tabs share it. Switching redirects back to the same lab map.
- The detailed schema, feature list, query walkthrough, measurements, and experiments are in `docs/observations/clickhouse.md`.
- The `postgresql()` load embeds the PostgreSQL credentials in the generated SQL. This is a local/demo path; production would use a named collection or an external sync.

### 4.12 Filter sampling

| ID            | Requirement                                                                                                                                                                                                      | Priority | Verification        | Source |
| ------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ------------------- | ------ |
| FR-FILTER-001 | The system shall place at most one Filter per sample, permanently, with a stored first-placed week.                                                                                                              | Must     | Test                | D43    |
| FR-FILTER-002 | The stress seeder shall place filters on every 100th sample in hierarchy order (1% coverage) in every lab, with a per-lab interval stored on the lab.                                                            | Must     | Test, Demonstration | D43    |
| FR-FILTER-003 | A filter reading shall be an independent non-negative integer; a filter may be read multiple times in a week and only the latest reading within the selected ISO week is shown.                                  | Must     | Test                | D43    |
| FR-FILTER-004 | A filter reading shall not carry over to the next week; a placed filter remains visible with no value when the selected week has no reading.                                                                     | Must     | Test                | D43    |
| FR-FILTER-005 | A filter shall not change the bacteria summary counts (`present_count`, `level_count`, `count_count`, `highest_level`, `max_count`, or `single`).                                                                | Must     | Test                | D43    |
| FR-FILTER-006 | The lab map level cell shall show a yellow filter badge with the selected week's value, or the badge alone when there is no reading.                                                                             | Must     | Demonstration       | D43    |
| FR-FILTER-007 | The sample details modal shall show a separate Filter section from the placement week onward, with the first week placed and the selected week reading or "No reading this week"; it is hidden before placement. | Must     | Test, Demonstration | D43    |
| FR-FILTER-008 | The map legend shall include a Filter entry.                                                                                                                                                                     | Should   | Demonstration       | D43    |
| FR-FILTER-009 | The PostgreSQL, ClickHouse raw, and ClickHouse snapshot engines shall return identical filter summaries and filter details.                                                                                      | Must     | Test                | D43    |
| FR-FILTER-010 | The system shall provide an explicit `FilterSeeder` that can rebuild filters and readings independently of the full demo seed.                                                                                   | Should   | Inspection          | D43    |

**Notes**

- The map renders one cell per level, so the yellow badge appears on a level cell when any of its three samples has a filter; the modal marks the filtered sample and shows its Filter section.
- Filters are placed on a random completed ISO week after lab creation; the current in-progress week is excluded.
- The seeder writes a reading in about 70% of weeks from the placement week onward, with values 0–100.
- There is no filter placement UI in this increment; placements and readings are seeded or written programmatically.
- The ClickHouse snapshot stores `filter_id` (`0` = none) and `filter_value` (`-1` = no reading); the snapshot build starts at the earliest filter placement week even when no bacteria observations exist that early.

---

## 5. Non-functional requirements

### 5.1 Performance

| ID           | Requirement                                                                                                                                                            | Priority | Verification               |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | -------------------------- |
| NFR-PERF-001 | The lab map shall load within 2 seconds at p95 for a lab with 3,600 samples on the local environment.                                                                  | Must     | Analysis, Performance test |
| NFR-PERF-002 | The sample details optional prop shall respond within 500 ms at p95.                                                                                                   | Must     | Analysis, Performance test |
| NFR-PERF-003 | The Manage Organizations and Labs pages shall respond within 500 ms at p95 with 3 organizations and 9 labs.                                                            | Must     | Analysis, Performance test |
| NFR-PERF-004 | The stress seeder shall complete within 60 minutes and use no more than 512 MB of memory on the local environment.                                                     | Must     | Analysis, Performance test |
| NFR-PERF-005 | The lab map shall eager-load the hierarchy and execute one summary query per page request; it shall not produce N+1 queries.                                           | Must     | Analysis, Test             |
| NFR-PERF-006 | The current-state query shall use the state index and shall not perform a full scan or a 20M-row sort for a single lab.                                                | Must     | Analysis, Performance test |
| NFR-PERF-007 | The lab map shall load within 6 seconds at p95 for the largest seeded lab (6,400 levels, 19,200 samples) on the local environment with the ClickHouse snapshot engine. | Must     | Analysis, Performance test |
| NFR-PERF-008 | The ClickHouse raw state query shall complete within 1 second at p95 for the largest seeded lab on the local environment.                                              | Must     | Analysis, Performance test |
| NFR-PERF-009 | The ClickHouse snapshot query shall complete within 500 ms at p95 for synthetic 5x per-lab data on the local environment.                                              | Should   | Analysis, Performance test |

### 5.2 Scalability

| ID            | Requirement                                                                                                                                   | Priority | Verification               |
| ------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | -------- | -------------------------- |
| NFR-SCALE-001 | The PostgreSQL schema and map query shall support at least 97,200 samples, 4,400,000 observations, and 10,600,000 bacteria observations.      | Must     | Analysis, Performance test |
| NFR-SCALE-002 | The schema shall use bigint IDs and explicit ISO week columns so a future ClickHouse table can mirror the observation shape.                  | Must     | Inspection                 |
| NFR-SCALE-003 | The lab map shall remain usable without pagination at 6,400 levels and 19,200 samples.                                                        | Must     | Demonstration              |
| NFR-SCALE-004 | The system shall support a 10x stress profile with at least 43M observations and 100M bacteria observations for storage and raw-load testing. | Should   | Analysis, Performance test |

### 5.3 Security

| ID          | Requirement                                                                                   | Priority | Verification     |
| ----------- | --------------------------------------------------------------------------------------------- | -------- | ---------------- |
| NFR-SEC-001 | All organization and lab routes shall require authentication and a verified email address.    | Must     | Test             |
| NFR-SEC-002 | Authorization shall be enforced server-side through Laravel policies.                         | Must     | Test             |
| NFR-SEC-003 | Cross-tenant access shall return HTTP 403 without revealing whether the resource exists.      | Must     | Test             |
| NFR-SEC-004 | All input shall be validated through form requests; mass assignment shall be protected.       | Must     | Test, Inspection |
| NFR-SEC-005 | State-changing requests shall be protected by CSRF tokens.                                    | Must     | Inspection       |
| NFR-SEC-006 | No secrets or sensitive configuration shall be sent to the client.                            | Must     | Inspection       |
| NFR-SEC-007 | The selected organization shall be validated against the user's memberships on every request. | Must     | Test             |

### 5.4 Reliability

| ID          | Requirement                                                          | Priority | Verification |
| ----------- | -------------------------------------------------------------------- | -------- | ------------ |
| NFR-REL-001 | Lab creation and hierarchy generation shall be atomic.               | Must     | Test         |
| NFR-REL-002 | Organization creation and owner membership creation shall be atomic. | Must     | Test         |
| NFR-REL-003 | The stress seeder shall be deterministic and idempotent.             | Must     | Test         |
| NFR-REL-004 | Invalid week parameters shall fall back safely to the current week.  | Must     | Test         |
| NFR-REL-005 | Cascade deletes shall not leave orphan rows.                         | Must     | Test         |

### 5.5 Usability and accessibility

| ID         | Requirement                                                                                                   | Priority | Verification              |
| ---------- | ------------------------------------------------------------------------------------------------------------- | -------- | ------------------------- |
| NFR-UX-001 | The UI shall be responsive from 320 px to 1280 px and above.                                                  | Must     | Demonstration             |
| NFR-UX-002 | The UI shall meet WCAG 2.1 AA basics: labels, keyboard access, visible focus, and contrast.                   | Must     | Inspection, Demonstration |
| NFR-UX-003 | Growth level and presence shall not be conveyed by colour alone; each state shall also have a distinct shape. | Must     | Inspection, Demonstration |
| NFR-UX-004 | Forms shall display inline validation errors and preserve user input.                                         | Must     | Test, Demonstration       |
| NFR-UX-005 | The map shall provide a legend and clear empty states.                                                        | Must     | Demonstration             |

### 5.6 Maintainability

| ID            | Requirement                                                                                                                 | Priority | Verification |
| ------------- | --------------------------------------------------------------------------------------------------------------------------- | -------- | ------------ |
| NFR-MAINT-001 | The implementation shall follow existing Laravel conventions: enums, policies, form requests, factories, and Inertia pages. | Must     | Inspection   |
| NFR-MAINT-002 | `php artisan test` shall pass.                                                                                              | Must     | Test         |
| NFR-MAINT-003 | Pint and PHPStan/Larastan shall pass.                                                                                       | Must     | Analysis     |
| NFR-MAINT-004 | The TypeScript type check and frontend build shall pass.                                                                    | Must     | Analysis     |
| NFR-MAINT-005 | No new runtime dependency shall be added without a documented justification.                                                | Should   | Inspection   |

### 5.7 Portability

| ID           | Requirement                                                                                                                                              | Priority | Verification     |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | ---------------- |
| NFR-PORT-001 | The module shall run on PostgreSQL 16 or later.                                                                                                          | Must     | Inspection       |
| NFR-PORT-002 | The module shall run on the existing lerd local environment.                                                                                             | Must     | Demonstration    |
| NFR-PORT-003 | ClickHouse shall be required for migrations and the ClickHouse lab-map engines; PostgreSQL-only use shall survive an unreachable or unloaded ClickHouse. | Must     | Inspection, Test |

### 5.8 Observability

| ID          | Requirement                                                 | Priority | Verification  |
| ----------- | ----------------------------------------------------------- | -------- | ------------- |
| NFR-OBS-001 | The stress seeder shall report progress to the console.     | Should   | Demonstration |
| NFR-OBS-002 | Application errors shall be written to the Laravel log.     | Must     | Inspection    |
| NFR-OBS-003 | Logs shall not contain passwords, tokens, or other secrets. | Must     | Inspection    |

### 5.9 Compliance

| ID           | Requirement                                                                                                              | Priority | Verification |
| ------------ | ------------------------------------------------------------------------------------------------------------------------ | -------- | ------------ |
| NFR-COMP-001 | ISO week calculations shall follow ISO 8601.                                                                             | Must     | Unit test    |
| NFR-COMP-002 | Timezone identifiers shall be valid IANA identifiers.                                                                    | Must     | Test         |
| NFR-COMP-003 | The module shall not introduce regulatory or personal data beyond existing user accounts and organization business data. | Must     | Inspection   |

---

## 6. External interface requirements

### 6.1 User interfaces

| Interface            | Description                                                                                                           |
| -------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Manage Organizations | Lists the user's organizations with role, lab count, and create/edit/delete actions allowed by role.                  |
| Organization create  | Form with name, address, and IANA timezone.                                                                           |
| Organization edit    | Form with name, address, and IANA timezone.                                                                           |
| Labs index           | Lists the labs of the selected organization with structure and sample counts; create-lab action for owner/admin.      |
| Lab create           | Form with name, code, notes, `row_count`, `shelves_per_row`, and `levels_per_shelf`; total-level preview.             |
| Lab detail / map     | Header with lab data, week selector, legend, engine switch with timings, full level matrix, and sample details modal. |
| Lab edit             | Form with name, code, and notes; read-only structure counts.                                                          |
| Delete dialogs       | Confirmation dialogs for organization and lab deletion with dependent counts.                                         |
| Navigation           | Labs, Organizations, and Bacteria items; organization switcher in the sidebar header; no Dashboard item.              |

### 6.2 Software interfaces

| Interface       | Description                                                                         |
| --------------- | ----------------------------------------------------------------------------------- |
| PostgreSQL      | Primary data store through Laravel's `pgsql` connection.                            |
| Laravel Fortify | Existing authentication and verification.                                           |
| Inertia.js      | Server-to-client page props and forms.                                              |
| Wayfinder       | Typed route helpers for the frontend.                                               |
| shadcn-vue      | UI primitives: buttons, inputs, selects, cards, badges, sheets, alert dialogs.      |
| ClickHouse      | Read-only analytics replica for the lab map; see `docs/observations/clickhouse.md`. |

### 6.3 API interfaces

| Method | Path                                                  | Purpose                                          | Auth                |
| ------ | ----------------------------------------------------- | ------------------------------------------------ | ------------------- |
| GET    | `/labs`                                               | Labs of the selected organization.               | Verified member     |
| GET    | `/labs/create`                                        | Lab creation form.                               | Owner/admin         |
| POST   | `/labs`                                               | Create a lab under the selected organization.    | Owner/admin         |
| GET    | `/labs/{lab}`                                         | Lab map.                                         | Organization member |
| GET    | `/labs/{lab}/edit`                                    | Lab edit form.                                   | Owner/admin         |
| PATCH  | `/labs/{lab}`                                         | Update lab metadata.                             | Owner/admin         |
| DELETE | `/labs/{lab}`                                         | Delete a lab.                                    | Owner/admin         |
| POST   | `/labs/engine`                                        | Switch the lab map query engine in the session.  | Organization member |
| GET    | `/labs/{lab}/samples/{sample}/bacteria?week=YYYY-Www` | Sample bacteria details JSON.                    | Organization member |
| GET    | `/organizations`                                      | Manage Organizations page.                       | Verified user       |
| GET    | `/organizations/create`                               | Organization creation form.                      | Verified user       |
| POST   | `/organizations`                                      | Create an organization and owner membership.     | Verified user       |
| POST   | `/organizations/select`                               | Switch the selected organization in the session. | Organization member |
| GET    | `/organizations/{organization}/edit`                  | Organization edit form.                          | Owner               |
| PATCH  | `/organizations/{organization}`                       | Update an organization.                          | Owner               |
| DELETE | `/organizations/{organization}`                       | Delete an organization.                          | Owner               |

The organization id appears only in the Manage Organizations routes. Lab routes read the selected organization from the session.

### 6.4 Data formats

| Format                | Rule                                                          |
| --------------------- | ------------------------------------------------------------- |
| ISO week              | `YYYY-Www`, for example `2026-W45`.                           |
| Timezone              | IANA identifier, for example `America/Sao_Paulo`.             |
| Timestamp             | ISO 8601 in UTC for storage and transport.                    |
| Growth level          | `high`, `medium`, `low`, `none`.                              |
| Count                 | Non-negative integer.                                         |
| Sample code           | `R{row}-S{shelf}-L{level}-{sequence:02}`.                     |
| Lab structure         | `{row_count} × {shelves_per_row} × {levels_per_shelf}`.       |
| Selected organization | Server-side session value; never a URL segment for lab pages. |

### 6.5 Hardware interfaces

None. The module is browser-based and server-rendered through the existing application stack.

---

## 7. Data requirements

### 7.1 Entity relationship overview

```
User ──< organization_user >── Organization
                                  │
                                  └──< Lab
                                        │
                                        └──< LabRow
                                              │
                                              └──< LabShelf
                                                    │
                                                    └──< LabLevel
                                                          │
                                                          └──< Sample
                                                                │
                                                                └──< Observation
                                                                      │
                                                                      └──< BacteriaObservation >── Bacteria
```

### 7.2 Enumerations

| Enum                      | Values                          | Usage                                |
| ------------------------- | ------------------------------- | ------------------------------------ |
| `OrganizationRole`        | `owner`, `admin`, `member`      | `organization_user.role`             |
| `GrowthLevel`             | `high`, `medium`, `low`, `none` | `bacteria_observations.growth_level` |
| `BacteriaObservationType` | `level`, `count`                | `bacteria.observation_type`          |

### 7.3 Table definitions

#### 7.3.1 `organizations`

| Column       | Type         | Null | Default | Constraints                         | Description            |
| ------------ | ------------ | ---- | ------- | ----------------------------------- | ---------------------- |
| `id`         | bigint       | No   | auto    | PK                                  | Primary key.           |
| `name`       | varchar(255) | No   |         | Unique                              | Organization name.     |
| `address`    | text         | No   |         |                                     | Single-field address.  |
| `timezone`   | varchar(64)  | No   |         | Valid IANA identifier (application) | Organization timezone. |
| `created_at` | timestamptz  | Yes  |         |                                     | Creation timestamp.    |
| `updated_at` | timestamptz  | Yes  |         |                                     | Update timestamp.      |

#### 7.3.2 `organization_user`

| Column            | Type        | Null | Default  | Constraints                         | Description         |
| ----------------- | ----------- | ---- | -------- | ----------------------------------- | ------------------- |
| `id`              | bigint      | No   | auto     | PK                                  | Primary key.        |
| `organization_id` | bigint      | No   |          | FK → `organizations.id`, cascade    | Organization.       |
| `user_id`         | bigint      | No   |          | FK → `users.id`, cascade            | User.               |
| `role`            | varchar(16) | No   | `member` | Check in `owner`, `admin`, `member` | Membership role.    |
| `created_at`      | timestamptz | Yes  |          |                                     | Creation timestamp. |
| `updated_at`      | timestamptz | Yes  |          |                                     | Update timestamp.   |

Unique: `(organization_id, user_id)`. Index: `user_id`.

#### 7.3.3 `labs`

| Column             | Type         | Null | Default | Constraints                                     | Description                                           |
| ------------------ | ------------ | ---- | ------- | ----------------------------------------------- | ----------------------------------------------------- |
| `id`               | bigint       | No   | auto    | PK                                              | Primary key.                                          |
| `organization_id`  | bigint       | No   |         | FK → `organizations.id`, cascade                | Owning organization.                                  |
| `name`             | varchar(255) | No   |         | Unique `(organization_id, name)`                | Lab name.                                             |
| `code`             | varchar(50)  | Yes  | null    | Unique `(organization_id, code)` where not null | Optional lab code.                                    |
| `notes`            | text         | Yes  | null    |                                                 | Optional notes.                                       |
| `row_count`        | smallint     | No   |         | Check 1–20                                      | Number of rows created.                               |
| `shelves_per_row`  | smallint     | No   |         | Check 1–20                                      | Shelves per row at creation.                          |
| `levels_per_shelf` | smallint     | No   |         | Check 1–20                                      | Levels per shelf at creation.                         |
| `created_at`       | timestamptz  | Yes  |         |                                                 | Creation timestamp; used for the week selector start. |
| `updated_at`       | timestamptz  | Yes  |         |                                                 | Update timestamp.                                     |

Index: `organization_id`.

#### 7.3.4 `lab_rows`

| Column       | Type        | Null | Default | Constraints                          | Description         |
| ------------ | ----------- | ---- | ------- | ------------------------------------ | ------------------- |
| `id`         | bigint      | No   | auto    | PK                                   | Primary key.        |
| `lab_id`     | bigint      | No   |         | FK → `labs.id`, cascade              | Lab.                |
| `number`     | smallint    | No   |         | Unique `(lab_id, number)`, check ≥ 1 | Row number.         |
| `created_at` | timestamptz | Yes  |         |                                      | Creation timestamp. |
| `updated_at` | timestamptz | Yes  |         |                                      | Update timestamp.   |

Index: `lab_id`.

#### 7.3.5 `lab_shelves`

| Column       | Type        | Null | Default | Constraints                              | Description         |
| ------------ | ----------- | ---- | ------- | ---------------------------------------- | ------------------- |
| `id`         | bigint      | No   | auto    | PK                                       | Primary key.        |
| `lab_row_id` | bigint      | No   |         | FK → `lab_rows.id`, cascade              | Row.                |
| `number`     | smallint    | No   |         | Unique `(lab_row_id, number)`, check ≥ 1 | Shelf number.       |
| `created_at` | timestamptz | Yes  |         |                                          | Creation timestamp. |
| `updated_at` | timestamptz | Yes  |         |                                          | Update timestamp.   |

Index: `lab_row_id`.

#### 7.3.6 `lab_levels`

| Column         | Type        | Null | Default | Constraints                                | Description         |
| -------------- | ----------- | ---- | ------- | ------------------------------------------ | ------------------- |
| `id`           | bigint      | No   | auto    | PK                                         | Primary key.        |
| `lab_shelf_id` | bigint      | No   |         | FK → `lab_shelves.id`, cascade             | Shelf.              |
| `number`       | smallint    | No   |         | Unique `(lab_shelf_id, number)`, check ≥ 1 | Level number.       |
| `created_at`   | timestamptz | Yes  |         |                                            | Creation timestamp. |
| `updated_at`   | timestamptz | Yes  |         |                                            | Update timestamp.   |

Index: `lab_shelf_id`.

#### 7.3.7 `samples`

| Column         | Type        | Null | Default | Constraints                   | Description              |
| -------------- | ----------- | ---- | ------- | ----------------------------- | ------------------------ |
| `id`           | bigint      | No   | auto    | PK                            | Primary key.             |
| `lab_id`       | bigint      | No   |         | FK → `labs.id`, cascade       | Denormalized owning lab. |
| `lab_level_id` | bigint      | No   |         | FK → `lab_levels.id`, cascade | Fixed level.             |
| `code`         | varchar(32) | No   |         | Unique `(lab_id, code)`       | Generated sample code.   |
| `created_at`   | timestamptz | Yes  |         |                               | Creation timestamp.      |
| `updated_at`   | timestamptz | Yes  |         |                               | Update timestamp.        |

Indexes: `lab_id`, `lab_level_id`.

#### 7.3.8 `observations`

| Column        | Type        | Null | Default | Constraints                | Description                            |
| ------------- | ----------- | ---- | ------- | -------------------------- | -------------------------------------- |
| `id`          | bigint      | No   | auto    | PK                         | Primary key.                           |
| `sample_id`   | bigint      | No   |         | FK → `samples.id`, cascade | Sample.                                |
| `observed_at` | timestamptz | No   |         |                            | Reading timestamp in UTC.              |
| `iso_year`    | smallint    | No   |         | Check > 0                  | ISO year in the organization timezone. |
| `iso_week`    | smallint    | No   |         | Check 1–53                 | ISO week in the organization timezone. |
| `created_at`  | timestamptz | Yes  |         |                            | Creation timestamp.                    |
| `updated_at`  | timestamptz | Yes  |         |                            | Update timestamp.                      |

Unique: `(sample_id, observed_at)`.
Indexes: `(sample_id, observed_at)`, `(sample_id, iso_year, iso_week, observed_at)`.

#### 7.3.9 `bacteria_observations`

| Column           | Type        | Null | Default | Constraints                              | Description                              |
| ---------------- | ----------- | ---- | ------- | ---------------------------------------- | ---------------------------------------- |
| `id`             | bigint      | No   | auto    | PK                                       | Primary key.                             |
| `observation_id` | bigint      | No   |         | FK → `observations.id`, cascade          | Reading.                                 |
| `sample_id`      | bigint      | Yes  | null    | Derived from the parent observation      | Denormalized sample for the state index. |
| `observed_at`    | timestamptz | Yes  | null    | Derived from the parent observation      | Denormalized time for the state index.   |
| `bacteria_id`    | bigint      | No   |         | FK → `bacteria.id`, restrict             | Bacterium.                               |
| `growth_level`   | varchar(8)  | Yes  | null    | Check in `high`, `medium`, `low`, `none` | Level result.                            |
| `count`          | integer     | Yes  | null    | Check ≥ 0                                | Count result.                            |
| `created_at`     | timestamptz | Yes  |         |                                          | Creation timestamp.                      |
| `updated_at`     | timestamptz | Yes  |         |                                          | Update timestamp.                        |

Unique: `(observation_id, bacteria_id)`.
Check: exactly one of `growth_level` or `count` is non-null.
Indexes: `observation_id`, `bacteria_id`, and the state index below.
`sample_id` and `observed_at` are filled by the model hook and the seeder; existing datasets are backfilled with `php artisan observations:backfill-state`.

#### 7.3.10 `bacteria` (existing table extension)

| Column             | Type       | Null | Default | Constraints               | Description                      |
| ------------------ | ---------- | ---- | ------- | ------------------------- | -------------------------------- |
| `observation_type` | varchar(8) | No   | `level` | Check in `level`, `count` | Level or count observation type. |

Existing columns remain: `id`, `name`, `short_name`, `description`, `color`, `icon`, `created_at`, `updated_at`.

### 7.4 Indexes and constraints

| Table                   | Index / Constraint                                                                          | Purpose                                                       |
| ----------------------- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| `organizations`         | Unique `name`                                                                               | Organization lookup and uniqueness.                           |
| `organization_user`     | Unique `(organization_id, user_id)`                                                         | One membership per user per organization.                     |
| `organization_user`     | Index `user_id`                                                                             | List a user's organizations.                                  |
| `labs`                  | Unique `(organization_id, name)`                                                            | Lab name uniqueness per organization.                         |
| `labs`                  | Unique `(organization_id, code)` where `code` not null                                      | Lab code uniqueness per organization.                         |
| `labs`                  | Index `organization_id`                                                                     | List labs for an organization.                                |
| `lab_rows`              | Unique `(lab_id, number)`                                                                   | Row numbering.                                                |
| `lab_shelves`           | Unique `(lab_row_id, number)`                                                               | Shelf numbering.                                              |
| `lab_levels`            | Unique `(lab_shelf_id, number)`                                                             | Level numbering.                                              |
| `samples`               | Unique `(lab_id, code)`                                                                     | Sample code uniqueness per lab.                               |
| `samples`               | Index `lab_id`, `lab_level_id`                                                              | Lab and level lookups.                                        |
| `observations`          | Unique `(sample_id, observed_at)`                                                           | One reading per sample per timestamp.                         |
| `observations`          | Index `(sample_id, observed_at)`                                                            | Latest-state and week queries.                                |
| `observations`          | Index `(sample_id, iso_year, iso_week, observed_at)`                                        | Weekly grouping and analytics.                                |
| `bacteria_observations` | Unique `(observation_id, bacteria_id)`                                                      | One result per bacterium per reading.                         |
| `bacteria_observations` | Check exactly one of `growth_level`/`count`                                                 | Result shape integrity.                                       |
| `bacteria_observations` | Index `observation_id`, `bacteria_id`                                                       | Join and bacterium lookups.                                   |
| `bacteria_observations` | Index `(sample_id, bacteria_id, observed_at DESC, id DESC)` INCLUDE `(growth_level, count)` | One index probe per sample+bacterium for the map state query. |

### 7.5 Data quality rules

| Rule                                                                        | Enforcement                                        |
| --------------------------------------------------------------------------- | -------------------------------------------------- |
| Level bacteria have `growth_level` and no `count`.                          | Application validation; DB exactly-one check.      |
| Count bacteria have `count` and no `growth_level`.                          | Application validation; DB exactly-one check.      |
| `none` is recorded only on a positive-to-absent transition.                 | Application/seeder logic.                          |
| `0` is recorded only on a positive-to-absent transition.                    | Application/seeder logic.                          |
| No rows while a bacterium remains absent.                                   | Application/seeder logic.                          |
| A reading records every present bacterium and every transition to absent.   | Application/seeder logic.                          |
| No observation with zero bacteria observations.                             | Application/seeder logic.                          |
| `observed_at` is not in the future.                                         | Application validation.                            |
| `iso_year` and `iso_week` match `observed_at` in the organization timezone. | Write-time computation and application validation. |
| Sample code is unique per lab.                                              | DB unique constraint.                              |
| Sample location is fixed.                                                   | Application: no update path.                       |
| Lab structure counts are immutable.                                         | Application: update form excludes them.            |
| No observation before lab creation.                                         | Application/seeder logic.                          |
| Count is non-negative.                                                      | DB check.                                          |
| Exactly one result field is set.                                            | DB check.                                          |

### 7.6 Data volume estimates

See Section 3.4. The stress dataset is a planning target, not a hard acceptance limit. The map must remain usable at that scale.

### 7.7 Migration order

1. `create_organizations_table`
2. `create_organization_user_table`
3. `create_labs_table`
4. `create_lab_rows_table`
5. `create_lab_shelves_table`
6. `create_lab_levels_table`
7. `create_samples_table`
8. `create_observations_table`
9. `create_bacteria_observations_table`
10. `add_observation_type_to_bacteria_table`

Each migration must be reversible. Foreign keys are added in the migration that creates the child table.

### 7.8 Retention and deletion

- Organizations and labs are hard-deleted after confirmation.
- Deletion cascades to all dependent rows.
- Observations are retained until an organization or lab is deleted or the demo seeder truncates demo data.
- No soft deletes.
- The demo seeder is the only supported bulk deletion path for observations.
- Users and the bacteria catalog are preserved by the demo seeder.

---

## 8. Constraints

### 8.1 Technical constraints

- Laravel 13, PHP 8.5.
- PostgreSQL is the application database; ClickHouse is a disposable analytical replica that `php artisan migrate` also provisions.
- Inertia.js 3, Vue 3, shadcn-vue, Wayfinder, Fortify.
- Pest for tests.
- PHPStan/Larastan, Pint, TypeScript checks, and the existing build pipeline.
- No new runtime dependency without justification.
- Existing starter-kit structure and conventions.

### 8.2 Business constraints

- PostgreSQL remains the source of truth; ClickHouse is a read-only replica.
- The demo seeder is for local/demo use, not production.
- Manual observation creation is deferred.
- Membership management UI is deferred.
- Sample CRUD is deferred.
- Lab structure counts are fixed at creation.

### 8.3 Design constraints

- Follow the existing app shell, sidebar, breadcrumbs, and shadcn-vue components.
- No emojis in the UI.
- Use text labels alongside colors.
- The lab map must be a full map, not paginated or collapsed.
- The map's visual language is a heat scale for growth levels plus numeric badges for counts.

### 8.4 Regulatory constraints

None identified. The module stores user accounts and organization business data only.

### 8.5 Schedule and budget

Not specified in this document.

---

## 9. Assumptions and dependencies

### 9.1 Assumptions

| ID    | Assumption                                                                                             |
| ----- | ------------------------------------------------------------------------------------------------------ |
| A-001 | The existing bacteria catalog of 10 organisms is the complete catalog for this increment.              |
| A-002 | Organization users have verified email addresses.                                                      |
| A-003 | The organization timezone applies to all its labs.                                                     |
| A-004 | A reading is per sample; there are no batch readings covering multiple samples.                        |
| A-005 | All bacteria results in one observation share the same `observed_at`.                                  |
| A-006 | The demo seeder is the only writer of observations in this increment.                                  |
| A-007 | The current ISO week is partial; its state is the latest observation so far.                           |
| A-008 | Lab creation date is the lab's `created_at` timestamp.                                                 |
| A-009 | Seeded labs receive randomized `created_at` values; manually created labs use the current time.        |
| A-010 | ClickHouse is required for migrations and the ClickHouse lab-map engines, not for the rest of the app. |
| A-011 | The test database is PostgreSQL.                                                                       |
| A-012 | The existing `DatabaseSeeder` continues to create the base user and bacteria catalog.                  |
| A-013 | The demo seeder is run explicitly and may be destructive to demo data.                                 |
| A-014 | The map's sample details are an Inertia optional prop, not a separate JSON endpoint.                   |
| A-015 | The 1–20 dimension limit is acceptable for manual lab creation; the seeder bypasses the form.          |
| A-016 | The selected organization is per user session, so all open tabs share the same selection.              |
| A-017 | A user with no memberships sees an empty Labs page and can create an organization from there.          |
| A-018 | Organization ids remain in Manage Organizations URLs only; lab URLs never contain them.                |
| A-019 | The Dashboard page is not part of this increment and sign-in redirects to Labs.                        |

### 9.2 Dependencies

| ID      | Dependency                                                                                           |
| ------- | ---------------------------------------------------------------------------------------------------- |
| DEP-001 | Existing Laravel application and starter kit.                                                        |
| DEP-002 | Existing PostgreSQL service and credentials.                                                         |
| DEP-003 | Existing authentication and verified-email middleware.                                               |
| DEP-004 | Existing bacteria catalog and `BacteriaSeeder`.                                                      |
| DEP-005 | Existing shadcn-vue component library.                                                               |
| DEP-006 | Existing Wayfinder route generation.                                                                 |
| DEP-007 | Existing Pest test setup.                                                                            |
| DEP-008 | Existing lerd local environment.                                                                     |
| DEP-009 | `itsgoingd/clockwork` for local request profiling of the lab-map page (NFR-MAINT-005 justification). |

---

## 10. Verification and acceptance

### 10.1 Verification methods

| Method        | Meaning                                                                               |
| ------------- | ------------------------------------------------------------------------------------- |
| Test          | Automated Pest test with assertions.                                                  |
| Demonstration | Manual walkthrough of the UI or seeder output.                                        |
| Inspection    | Code, schema, or configuration review.                                                |
| Analysis      | Reasoning about performance, complexity, or data volume, optionally with measurement. |

### 10.2 Requirement verification summary

| Feature area                 | Requirements                  | Primary methods                                    |
| ---------------------------- | ----------------------------- | -------------------------------------------------- |
| Organization management      | FR-ORG-001 – FR-ORG-020       | Feature tests, demonstration                       |
| Membership and authorization | FR-AUTH-001 – FR-AUTH-015     | Feature tests                                      |
| Lab management and hierarchy | FR-LAB-001 – FR-LAB-020       | Feature tests, unit tests                          |
| Samples                      | FR-SMP-001 – FR-SMP-009       | Feature tests, unit tests                          |
| Observations                 | FR-OBS-001 – FR-OBS-025       | Feature tests, unit tests, DB inspection           |
| Weekly state and cycles      | FR-WK-001 – FR-WK-010         | Unit tests, feature tests                          |
| Lab map and week selector    | FR-MAP-001 – FR-MAP-029       | Feature tests, demonstration, performance analysis |
| Filter sampling              | FR-FILTER-001 – FR-FILTER-010 | Feature tests, demonstration, performance analysis |
| Seeding                      | FR-SEED-001 – FR-SEED-027     | Feature tests, demonstration, performance analysis |
| ClickHouse lab map engine    | FR-CH-001 – FR-CH-012         | Feature tests, demonstration, performance analysis |
| Navigation and UI            | FR-UI-001 – FR-UI-014         | Demonstration, feature tests                       |
| Data integrity               | FR-DATA-001 – FR-DATA-010     | DB inspection, feature tests                       |

### 10.3 Test plan

#### 10.3.1 Unit tests

| Test area        | Cases                                                                                                                                                                                   |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Enums            | `GrowthLevel` rank and labels; `OrganizationRole` permissions; `BacteriaObservationType` labels.                                                                                        |
| ISO week         | Monday–Sunday boundaries; week 1 containing January 4; timezone boundary (for example, 2025-12-29 01:30 UTC in America/Sao_Paulo belongs to 2025-W52 while UTC is already in 2026-W01). |
| Sample code      | `R1-S2-L3-01` format; per-level sequence.                                                                                                                                               |
| Weekly state     | Latest observation before week end; carry-forward; no observation ever; `none`/`0` absent.                                                                                              |
| Cycles           | Positive run terminated by `none`/`0`; reappearance starts a new cycle.                                                                                                                 |
| Seeder generator | Small deterministic dataset with expected counts and transitions.                                                                                                                       |

#### 10.3.2 Feature tests

| Test area                  | Cases                                                                                                                                                                                              |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Organization CRUD          | Create, list own organizations, update, delete; validation; unique name; invalid timezone.                                                                                                         |
| Organization authorization | Owner can update/delete; admin/member cannot; non-member 403; guest redirect.                                                                                                                      |
| Selected organization      | Switch persists in session; non-member switch 403; fallback to first organization; deletion clears selection; lab URLs contain no organization id.                                                 |
| Labs page                  | Lists only the selected organization's labs; empty state without a membership; lab outside the selected organization returns 404.                                                                  |
| Lab CRUD                   | Create with hierarchy under the selected organization; name/code uniqueness; counts immutable; update metadata; delete cascade.                                                                    |
| Lab authorization          | Owner/admin can create/update/delete; member cannot; non-member 403.                                                                                                                               |
| Lab map                    | Week selector range; default current week; invalid week fallback; empty state.                                                                                                                     |
| Map state                  | Carry-forward; `none`/`0` hidden; reappearance; level mark; count-only mark; mixed samples in the modal; filter badge with value and without reading; filter does not alter bacteria counts.       |
| Sample details             | Correct selected-week state; authorization; missing sample 404; filter first week and no-carry-over; filter hidden before placement.                                                               |
| Seeder                     | Small configuration; truncation; deterministic output; preserves users/bacteria; `FilterSeeder` placement coverage and readings.                                                                   |
| ClickHouse engine          | Temp tables; raw and snapshot summaries/sample payloads match PostgreSQL and each other; filter tables and filter summaries match; engine switch persists in the session; invalid engine rejected. |
| Existing bacteria pages    | Still pass with the new `observation_type` attribute.                                                                                                                                              |

#### 10.3.3 Manual verification

- Create an organization and a lab through the UI.
- Switch the selected organization from the sidebar and confirm the Labs page lists only that organization's labs.
- Confirm the organization id never appears in a lab URL.
- Confirm the Dashboard page and menu item are absent and sign-in lands on Labs.
- Confirm the hierarchy counts and sample count display.
- Run the stress seeder and open a lab map.
- Change the week selector and confirm carry-forward.
- Open a level mark and confirm its sample list and bacteria details.
- Open a level with a filter and confirm the yellow badge, the filtered sample marker, and the Filter section's first week and value.
- Change to a week with no filter reading and confirm the badge has no value and the modal says "No reading this week."
- Check the map at 320 px, 768 px, and 1280 px.
- Check keyboard navigation and visible focus.
- Confirm role-based button visibility.
- Confirm delete confirmations and cascade behavior on a small dataset.

### 10.4 Acceptance scenarios

#### AS-001 — Owner creates an organization and lab

Given a verified user,
When they create an organization with a name, address, and timezone,
Then they become the owner,
And the organization appears on the Manage Organizations page,
And the new organization becomes the selected organization,
And the Labs page is empty until they create a lab with 5 rows × 6 shelves × 8 levels,
And the lab map shows 240 levels and the generated structure.

#### AS-002 — Admin manages labs but not the organization

Given an admin of an organization,
When they open the Manage Organizations page,
Then they cannot update or delete the organization,
And the organization edit/delete controls are hidden,
And when they open the Labs page they can create, edit, and delete labs.

#### AS-003 — Member views but cannot modify

Given a member of an organization,
When they open the Labs page and a lab,
Then they can view the labs and the lab map,
And they cannot create, edit, or delete labs,
And direct requests to modify resources return 403.

#### AS-004 — Non-member is denied

Given an authenticated user who is not a member of an organization,
When they request a lab of that organization,
Then the system returns 403.

#### AS-005 — Stress seeder produces a usable map

Given an empty demo dataset,
When the operator runs `php artisan db:seed --class=DemoStressSeeder`,
Then three organizations, nine labs, 97,200 samples, and the observation dataset are created,
And the seeder completes within 10 minutes,
And the largest lab has 6,400 sample places and the lab map renders 6,400 level marks.

#### AS-006 — Week selector and carry-forward

Given a lab with observations in week 1 and no observation in week 2,
When the user selects week 2,
Then the map shows the week 1 state for each sample+bacterium.

#### AS-007 — NONE/zero hidden and reappearance

Given a level bacterium with a `none` observation in week 3,
When the user selects week 3,
Then the bacterium is hidden from the level mark and the level's sample list,
And when a new positive observation appears in week 5,
Then the bacterium is shown again in week 5.

Given a count bacterium with a `0` observation in week 3,
When the user selects week 3,
Then the bacterium is hidden,
And a later positive count shows it again.

#### AS-008 — Level summary rules

Given a level with one or more samples,
Then the map shows one mark for the level.
Given a level whose highest present growth level is HIGH,
Then the mark is a red square.
Given a level whose only present bacteria are counts,
Then the mark is a blue diamond.
Given a level with mixed level and count bacteria,
Then the mark shows the highest growth level.
Given a level with no present bacteria,
Then the mark is a muted ring.
When the user activates the level mark,
Then the details modal lists the level's samples with codes, present counts, and states,
And selecting a sample shows every present bacterium with its level or count.

#### AS-009 — Lab creation counts are immutable

Given an existing lab,
When the user opens the edit form,
Then the structure counts are read-only,
And a direct request attempting to change them does not alter the hierarchy.

#### AS-010 — Deletion cascades

Given an organization with labs, samples, and observations,
When the owner deletes the organization after confirmation,
Then all dependent rows are removed,
And no orphan rows remain.

#### AS-011 — Selected organization switch

Given a user who belongs to two organizations,
When they pick the second organization in the sidebar switcher,
Then the selection is stored in the server-side session,
And they land on the Labs page,
And only the second organization's labs are listed,
And no organization id appears in the URL.

#### AS-012 — Lab outside the selected organization

Given a member of two organizations with the first one selected,
When they request a lab that belongs to the second organization,
Then the system returns 404,
And when a non-member requests the same lab,
Then the system returns 403.

#### AS-013 — No dashboard

Given a signed-in user,
When they sign in or complete email verification,
Then they land on the Labs page,
And no dashboard page or navigation item exists.

#### AS-014 — Lab map engine switch

Given a signed-in member viewing a lab map,
When they select ClickHouse in the engine switch,
Then the next lab map request uses the raw ClickHouse engine,
And the map shows the same level marks as PostgreSQL,
And the switch shows the cumulative state-query time for the loaded rows for each engine that has served this lab and week.
When they select ClickHouse snapshot,
Then the map reads the pre-computed weekly summaries directly,
And the summaries match the raw engine,
And the sample details modal still loads from the raw engine.
When they switch back to PostgreSQL,
Then the map uses the PostgreSQL engine again.
When ClickHouse is unreachable or not loaded,
Then the page shows an error naming the selected engine and `ch:load`, and the PostgreSQL engine still works.

### 10.5 Definition of done

- All migrations run successfully on PostgreSQL and ClickHouse.
- The `DemoStressSeeder` completes and produces the expected dataset.
- The lab map renders the full structure and the week selector works; the page returns the week's summaries in the same request, and the modal details load through the `sampleDetails` optional prop.
- The dashboard page and navigation item are removed and sign-in redirects to Labs.
- The selected organization lives in the session, the switcher changes it, and lab URLs contain no organization id.
- `php artisan ch:load` rebuilds the ClickHouse replica and the ClickHouse engine returns the same summaries as PostgreSQL.
- The lab map engine switch stores its choice in the session and shows measured times.
- The summary snapshot builds and returns the same summaries and sample payloads as the raw engine, and `ch:bench` reports the 1x and synthetic 5x timings.
- `php artisan test` passes.
- `vendor/bin/pint --test` passes.
- `vendor/bin/phpstan analyse` passes.
- `npm run types:check` and the frontend build pass.
- No N+1 queries on the lab map.
- The README documents the new models and the stress seeder command.
- The SRS is approved and the implementation matches the approved scope.

---

## 11. Traceability

### 11.1 Source decisions

| ID  | Decision                                                                                                                                                                    |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | The three dimensions are named Row, Shelf, and Level.                                                                                                                       |
| D2  | The dimensions are modeled as three hierarchy tables: `lab_rows` → `lab_shelves` → `lab_levels`.                                                                            |
| D3  | Samples have a generated code and are fixed to their level for life.                                                                                                        |
| D4  | Observations are raw readings: one parent reading with many bacteria results.                                                                                               |
| D5  | ISO year and week are stored, computed in the organization timezone.                                                                                                        |
| D6  | Level bacteria use `growth_level` with `high`, `medium`, `low`, `none`.                                                                                                     |
| D7  | Count observations use a nullable count with a catalog flag; superseded by D18/D19 for the final split.                                                                     |
| D8  | Users belong to organizations; tenancy is scoped by membership.                                                                                                             |
| D9  | Organization address is a single text field.                                                                                                                                |
| D10 | Lab fields are name, optional code, and optional notes.                                                                                                                     |
| D11 | PostgreSQL first; ClickHouse is deferred.                                                                                                                                   |
| D12 | `none` is recorded only on a positive-to-absent transition; no rows while absent.                                                                                           |
| D13 | Weekly state is the latest observation before week end, carried forward.                                                                                                    |
| D14 | Cycles are derived from the event stream, not stored.                                                                                                                       |
| D15 | Lab structure counts are fixed at creation and immutable.                                                                                                                   |
| D16 | Organization and Lab CRUD are in scope; the lab detail page is the full map.                                                                                                |
| D17 | Organization roles are owner, admin, and member.                                                                                                                            |
| D18 | Six level bacteria and four count bacteria.                                                                                                                                 |
| D19 | Count bacteria are count-only; `0` closes a cycle.                                                                                                                          |
| D20 | Owner all, admin labs, member read-only; any user can create an organization.                                                                                               |
| D21 | The lab map is a full rows → shelves → levels → samples map with a highest-level summary.                                                                                   |
| D22 | The stress seeder is a dedicated, explicit, destructive demo seeder.                                                                                                        |
| D23 | Seeding uses the realistic mixed distribution and the larger stress volume.                                                                                                 |
| D24 | The lab map has a week selector.                                                                                                                                            |
| D25 | Map marks show the level state; the details modal shows sample codes, present counts, and bacterium values.                                                                 |
| D26 | The initial map payload contains summaries; details are lazy-loaded.                                                                                                        |
| D27 | The current-state query runs on the fly with indexes.                                                                                                                       |
| D28 | The map layout is rows → shelves → levels.                                                                                                                                  |
| D29 | The week selector starts at the lab creation week; lab creation dates are randomized in the seeder.                                                                         |
| D30 | The selector runs through the current week and defaults to the current week.                                                                                                |
| D31 | Seeded labs are created 26–20 weeks ago.                                                                                                                                    |
| D32 | First readings occur after a random 0–4 week delay.                                                                                                                         |
| D33 | The selected organization lives in the server-side session; lab URLs carry no organization id.                                                                              |
| D34 | The Dashboard page is removed; sign-in and email verification redirect to Labs.                                                                                             |
| D35 | Labs and Manage Organizations are separate pages: Labs scopes to the selected organization.                                                                                 |
| D36 | Switching the selected organization redirects to the Labs page.                                                                                                             |
| D37 | ClickHouse holds a denormalized `MergeTree` lab observation replica; the load uses the `postgresql()` table function.                                                       |
| D38 | The lab map engine is switched per session from the map UI, with measured state-query timings.                                                                              |
| D39 | PostgreSQL remains the source of truth; ClickHouse is read-only and refreshed by `ch:load`.                                                                                 |
| D40 | The weekly AggregatingMergeTree experiment was removed; the snapshot is built directly from raw observations.                                                               |
| D41 | The summary snapshot stores the final per-sample/week map payload in a plain MergeTree for a direct point lookup.                                                           |
| D42 | The benchmark command measures all engines at 1x and the ClickHouse engines on synthetic per-lab 5x tables.                                                                 |
| D43 | A Filter is a separate permanent per-sample sampler: 1% coverage, weekly readings, no carry-over, yellow map badge.                                                         |
| D44 | A 10x stress profile scales readings per sample/week and commits observation chunks instead of one giant transaction.                                                       |
| D45 | ClickHouse schema is migration-owned via package Blueprint and Eloquent models; `php artisan migrate` runs it, and `ch:load` truncates and reloads data.                    |
| D46 | The PostgreSQL map query uses denormalized `sample_id`/`observed_at` on bacteria observations and a state index built after bulk seeding; existing data is backfilled once. |

### 11.2 Requirement-to-design traceability

| Feature area                 | Requirements                  | Models                                      | Controllers / Actions                                                                                                                                                            | Pages / Components                                                             | Tests                                                                                                            |
| ---------------------------- | ----------------------------- | ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Organization management      | FR-ORG-001 – FR-ORG-020       | `Organization`, `OrganizationMembership`    | `OrganizationController`, form requests, `OrganizationPolicy`                                                                                                                    | `organizations/Index.vue`, `Create.vue`, `Edit.vue`                            | `OrganizationPageTest`                                                                                           |
| Selected organization        | FR-ORG-014 – FR-ORG-020       | `Organization`, `User`                      | `ResolveSelectedOrganization`, `OrganizationController@select`                                                                                                                   | `OrganizationSwitcher.vue`                                                     | `OrganizationPageTest`, `LabPageTest`                                                                            |
| Membership and authorization | FR-AUTH-001 – FR-AUTH-015     | `OrganizationMembership`, `User`            | Policies, middleware                                                                                                                                                             | Role-aware UI                                                                  | `AuthorizationTest`                                                                                              |
| Lab management and hierarchy | FR-LAB-001 – FR-LAB-020       | `Lab`, `LabRow`, `LabShelf`, `LabLevel`     | `LabController`, `CreateLab` action, `LabPolicy`                                                                                                                                 | `labs/Index.vue`, `Create.vue`, `Edit.vue`                                     | `LabPageTest`                                                                                                    |
| Samples                      | FR-SMP-001 – FR-SMP-009       | `Sample`                                    | Seeder, future ingestion                                                                                                                                                         | —                                                                              | `SampleTest`                                                                                                     |
| Observations                 | FR-OBS-001 – FR-OBS-025       | `Observation`, `BacteriaObservation`, enums | Seeder, future ingestion                                                                                                                                                         | —                                                                              | `ObservationTest`                                                                                                |
| Weekly state and cycles      | FR-WK-001 – FR-WK-010         | `Observation`, `BacteriaObservation`        | `LabMapQuery` / service                                                                                                                                                          | Map                                                                            | `WeeklyStateTest`                                                                                                |
| Lab map and week selector    | FR-MAP-001 – FR-MAP-032       | —                                           | `LabController@show`, `SampleBacteriaController`                                                                                                                                 | `labs/Show.vue`, map components, details modal                                 | `LabMapTest`                                                                                                     |
| Seeding                      | FR-SEED-001 – FR-SEED-027     | All models                                  | `DemoStressSeeder`, `FilterSeeder`, `SeedDemoStressCommand`, generator service                                                                                                   | —                                                                              | `ObservationGeneratorTest`                                                                                       |
| Filter sampling              | FR-FILTER-001 – FR-FILTER-010 | `Filter`, `FilterObservation`               | `FilterSeeder`, `LabController@show`, `SampleBacteriaController`, lab map engines                                                                                                | `FilterBadge.vue`, `LabMapLevel.vue`, `LevelDetailsModal.vue`, `MapLegend.vue` | `LabMapTest`, `FilterSeederTest`                                                                                 |
| ClickHouse lab map engine    | FR-CH-001 – FR-CH-012         | `App\Models\ClickHouse\*`, migrations       | `LoadLabObservationsCommand`, `BenchmarkLabMapCommand`, `WeeklySnapshot`, `LabMapService`, `PostgresLabMapService`, `ClickHouseLabMapService`, `ClickHouseSnapshotLabMapService` | `labs/LabEngineSwitch.vue`                                                     | `ClickHouseLabMapEngineTest`, `ClickHouseSnapshotLabMapEngineTest`, `LabMapEngineEquivalenceTest`, `LabPageTest` |
| Navigation and UI            | FR-UI-001 – FR-UI-014         | —                                           | —                                                                                                                                                                                | Sidebar, organization switcher, forms, dialogs                                 | `NavigationTest`, manual                                                                                         |
| Data integrity               | FR-DATA-001 – FR-DATA-010     | Migrations, models                          | —                                                                                                                                                                                | —                                                                              | `SchemaTest`, feature tests                                                                                      |

### 11.3 Requirement-to-verification traceability

Each requirement table in Section 4 includes its verification method. The summary:

| Verification method | Requirement groups                                                                                                |
| ------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Automated tests     | FR-ORG, FR-AUTH, FR-LAB, FR-SMP, FR-OBS, FR-WK, FR-MAP, FR-SEED, FR-CH, FR-UI, FR-DATA                            |
| Demonstration       | FR-ORG-013, FR-MAP-001/002/016/017/022/023/024, FR-SEED-023, FR-CH-002/006/007, FR-UI-001/002/005/009/010/013/014 |
| Inspection          | FR-AUTH-008, FR-OBS-018/019/020, FR-SEED-001/002/003/021/022, FR-DATA-005/007/008/009/010, NFR-SCALE-002          |
| Analysis            | NFR-PERF, NFR-SCALE, seeder performance                                                                           |

---

## 12. Appendices

### Appendix A — Decision log

| ID  | Decision                                                                                                                                                                    | Round |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----- |
| D1  | Row / Shelf / Level naming.                                                                                                                                                 | 1     |
| D2  | Three hierarchy tables.                                                                                                                                                     | 1     |
| D3  | Sample code + fixed location.                                                                                                                                               | 1     |
| D4  | Raw readings.                                                                                                                                                               | 1     |
| D5  | ISO year/week in organization timezone.                                                                                                                                     | 1     |
| D6  | `growth_level` + `none` semantics.                                                                                                                                          | 1     |
| D7  | Nullable count + catalog flag.                                                                                                                                              | 1     |
| D8  | Users belong to organizations.                                                                                                                                              | 1     |
| D9  | Single address field.                                                                                                                                                       | 1     |
| D10 | Lab name + code + notes.                                                                                                                                                    | 1     |
| D11 | PostgreSQL first, ClickHouse later.                                                                                                                                         | 1     |
| D12 | `none` transition-only.                                                                                                                                                     | 2     |
| D13 | Weekly state carry-forward.                                                                                                                                                 | 2     |
| D14 | Cycles derived.                                                                                                                                                             | 2     |
| D15 | Lab counts fixed at creation.                                                                                                                                               | 2     |
| D16 | Organization + Lab CRUD; lab shows map.                                                                                                                                     | 2     |
| D17 | Roles owner/admin/member.                                                                                                                                                   | 2     |
| D18 | Six level, four count split.                                                                                                                                                | 3     |
| D19 | Count-only, `0` closes cycle.                                                                                                                                               | 3     |
| D20 | Permission matrix.                                                                                                                                                          | 3     |
| D21 | Full lab map with highest-level summary.                                                                                                                                    | 3     |
| D22 | Dedicated stress seeder.                                                                                                                                                    | 3     |
| D23 | Realistic mixed distribution and larger stress volume.                                                                                                                      | 3     |
| D24 | Week selector.                                                                                                                                                              | 4     |
| D25 | Level mark summary; sample codes and values in the details modal.                                                                                                           | 4     |
| D26 | Summary payload + lazy details.                                                                                                                                             | 4     |
| D27 | Query on the fly.                                                                                                                                                           | 4     |
| D28 | Rows → shelves → levels layout.                                                                                                                                             | 4     |
| D29 | All weeks since lab creation; randomize lab creation date.                                                                                                                  | 5     |
| D30 | Through current week, default current.                                                                                                                                      | 6     |
| D31 | Seeded labs created 26–20 weeks ago.                                                                                                                                        | 6     |
| D32 | First readings after 0–4 week delay.                                                                                                                                        | 6     |
| D33 | Selected organization in the server session.                                                                                                                                | 7     |
| D34 | Dashboard removed; sign-in redirects to Labs.                                                                                                                               | 7     |
| D35 | Labs and Manage Organizations are separate pages.                                                                                                                           | 7     |
| D36 | Organization switch redirects to Labs.                                                                                                                                      | 7     |
| D37 | ClickHouse MergeTree replica, loaded via `postgresql()`.                                                                                                                    | 8     |
| D38 | Per-session lab map engine switch with timings.                                                                                                                             | 8     |
| D39 | PostgreSQL source of truth; ClickHouse read-only replica.                                                                                                                   | 8     |
| D40 | Weekly AggregatingMergeTree rollup removed; snapshot from raw.                                                                                                              | 8     |
| D41 | Plain per-sample/week summary snapshot.                                                                                                                                     | 9     |
| D42 | ch:bench with synthetic per-lab 5x scale.                                                                                                                                   | 9     |
| D43 | Separate permanent Filter sampler with weekly, non-carrying values.                                                                                                         | 10    |
| D44 | A 10x stress profile scales readings per sample/week and commits observation chunks instead of one giant transaction.                                                       | 11    |
| D45 | ClickHouse schema is migration-owned via package Blueprint and Eloquent models; `php artisan migrate` runs it, and `ch:load` truncates and reloads data.                    | 12    |
| D46 | The PostgreSQL map query uses denormalized `sample_id`/`observed_at` on bacteria observations and a state index built after bulk seeding; existing data is backfilled once. | 13    |

### Appendix B — Example observation timelines

#### B.1 Level bacterium cycle

| Week | Observation | Weekly state | Map    |
| ---- | ----------- | ------------ | ------ |
| 1    | HIGH        | HIGH         | Shown  |
| 2    | MEDIUM      | MEDIUM       | Shown  |
| 3    | NONE        | Absent       | Hidden |
| 4    | —           | Absent       | Hidden |
| 5    | —           | Absent       | Hidden |
| 6    | LOW         | LOW          | Shown  |
| 7    | NONE        | Absent       | Hidden |

#### B.2 Count bacterium cycle

| Week | Observation | Weekly state | Map    |
| ---- | ----------- | ------------ | ------ |
| 1    | 1200        | 1200         | Shown  |
| 2    | 800         | 800          | Shown  |
| 3    | 0           | Absent       | Hidden |
| 4    | —           | Absent       | Hidden |
| 5    | 5000        | 5000         | Shown  |
| 6    | 0           | Absent       | Hidden |

#### B.3 Carry-forward

| Week | Observation | Weekly state |
| ---- | ----------- | ------------ |
| 1    | HIGH        | HIGH         |
| 2    | —           | HIGH         |
| 3    | —           | HIGH         |
| 4    | NONE        | Absent       |
| 5    | —           | Absent       |

#### B.4 Mixed level

Given a level with three samples in the selected week: sample 01 has Salmonella HIGH and E. coli LOW, sample 02 has C. perfringens count 1200, and sample 03 has no present bacteria:

- Level mark: red square (highest growth level HIGH).
- Count presence is shown by the mark only when no level bacterium is present at the level.
- Details modal: sample 01 (HIGH, 2 present), sample 02 (Count, 1 present), sample 03 (Empty, 0 present).
- Selecting sample 02 shows C. perfringens with the value 1200.

### Appendix C — Migration list

| Order | Migration                                | Purpose                    |
| ----- | ---------------------------------------- | -------------------------- |
| 1     | `create_organizations_table`             | Organizations.             |
| 2     | `create_organization_user_table`         | Memberships and roles.     |
| 3     | `create_labs_table`                      | Labs and structure counts. |
| 4     | `create_lab_rows_table`                  | Rows.                      |
| 5     | `create_lab_shelves_table`               | Shelves.                   |
| 6     | `create_lab_levels_table`                | Levels.                    |
| 7     | `create_samples_table`                   | Samples.                   |
| 8     | `create_observations_table`              | Observation parents.       |
| 9     | `create_bacteria_observations_table`     | Bacteria results.          |
| 10    | `add_observation_type_to_bacteria_table` | Level/count catalog type.  |

### Appendix D — Route list

| Method | Path                                    | Name                    |
| ------ | --------------------------------------- | ----------------------- |
| GET    | `/labs`                                 | `labs.index`            |
| GET    | `/labs/create`                          | `labs.create`           |
| POST   | `/labs`                                 | `labs.store`            |
| POST   | `/labs/engine`                          | `labs.engine`           |
| GET    | `/labs/{lab}`                           | `labs.show`             |
| GET    | `/labs/{lab}/edit`                      | `labs.edit`             |
| PATCH  | `/labs/{lab}`                           | `labs.update`           |
| DELETE | `/labs/{lab}`                           | `labs.destroy`          |
| GET    | `/labs/{lab}/samples/{sample}/bacteria` | `labs.samples.bacteria` |
| GET    | `/organizations`                        | `organizations.index`   |
| GET    | `/organizations/create`                 | `organizations.create`  |
| POST   | `/organizations`                        | `organizations.store`   |
| POST   | `/organizations/select`                 | `organizations.select`  |
| GET    | `/organizations/{organization}/edit`    | `organizations.edit`    |
| PATCH  | `/organizations/{organization}`         | `organizations.update`  |
| DELETE | `/organizations/{organization}`         | `organizations.destroy` |

### Appendix E — File and component inventory

#### Backend

| Path                                                  | Purpose                                                                    |
| ----------------------------------------------------- | -------------------------------------------------------------------------- |
| `app/Enums/OrganizationRole.php`                      | Role enum and permission helpers.                                          |
| `app/Enums/GrowthLevel.php`                           | Growth level enum, rank, labels.                                           |
| `app/Enums/BacteriaObservationType.php`               | Level/count enum.                                                          |
| `app/Enums/LabMapEngine.php`                          | PostgreSQL / ClickHouse engine enum.                                       |
| `app/Models/Organization.php`                         | Organization model and relations.                                          |
| `app/Models/OrganizationMembership.php`               | Pivot model with role cast.                                                |
| `app/Models/Lab.php`                                  | Lab model and relations.                                                   |
| `app/Models/LabRow.php`                               | Row model.                                                                 |
| `app/Models/LabShelf.php`                             | Shelf model.                                                               |
| `app/Models/LabLevel.php`                             | Level model.                                                               |
| `app/Models/Sample.php`                               | Sample model.                                                              |
| `app/Models/Observation.php`                          | Observation model.                                                         |
| `app/Models/BacteriaObservation.php`                  | Bacteria result model.                                                     |
| `app/Policies/OrganizationPolicy.php`                 | Organization authorization.                                                |
| `app/Policies/LabPolicy.php`                          | Lab authorization.                                                         |
| `app/Http/Controllers/OrganizationController.php`     | Organization CRUD.                                                         |
| `app/Http/Controllers/LabController.php`              | Lab CRUD and map.                                                          |
| `app/Console/Commands/LoadLabObservationsCommand.php` | ClickHouse replica and snapshot load command.                              |
| `app/Console/Commands/BenchmarkLabMapCommand.php`     | 1x and synthetic 5x engine benchmark.                                      |
| `app/Support/LabMap/*`                                | Engine contract, PostgreSQL and ClickHouse engines, table helper, manager. |
| `app/Http/Controllers/SampleBacteriaController.php`   | Sample details JSON.                                                       |
| `app/Http/Middleware/ResolveSelectedOrganization.php` | Selected-organization session context.                                     |
| `app/Http/Requests/StoreOrganizationRequest.php`      | Organization create validation.                                            |
| `app/Http/Requests/UpdateOrganizationRequest.php`     | Organization update validation.                                            |
| `app/Http/Requests/StoreLabRequest.php`               | Lab create validation.                                                     |
| `app/Http/Requests/UpdateLabRequest.php`              | Lab update validation.                                                     |
| `app/Actions/CreateLab.php`                           | Transactional lab + hierarchy creation.                                    |
| `app/Support/LabMapQuery.php`                         | Current-state query and summaries.                                         |
| `database/seeders/DemoStressSeeder.php`               | Stress seeder.                                                             |
| `database/seeders/ObservationGenerator.php`           | Observation timeline generator.                                            |
| `database/factories/*`                                | Factories for new models.                                                  |

#### Frontend

| Path                                                 | Purpose                                       |
| ---------------------------------------------------- | --------------------------------------------- |
| `resources/js/pages/organizations/Index.vue`         | Manage Organizations page.                    |
| `resources/js/pages/organizations/Create.vue`        | Organization form.                            |
| `resources/js/pages/organizations/Edit.vue`          | Organization edit.                            |
| `resources/js/pages/labs/Index.vue`                  | Labs of the selected organization.            |
| `resources/js/pages/labs/Create.vue`                 | Lab creation form.                            |
| `resources/js/pages/labs/Show.vue`                   | Lab map.                                      |
| `resources/js/pages/labs/Edit.vue`                   | Lab edit.                                     |
| `resources/js/components/organizations/*`            | Organization cards, dialogs.                  |
| `resources/js/components/OrganizationSwitcher.vue`   | Sidebar organization switcher.                |
| `resources/js/components/labs/LabEngineSwitch.vue`   | PostgreSQL / ClickHouse switch with timings.  |
| `resources/js/components/labs/LabMapRow.vue`         | Row matrix with shelf headers and level rail. |
| `resources/js/components/labs/LabMapLevel.vue`       | Level mark cell.                              |
| `resources/js/components/labs/LevelMark.vue`         | Shared colour-and-shape state mark.           |
| `resources/js/components/labs/LevelDetailsModal.vue` | Lazy sample details modal.                    |
| `resources/js/components/labs/MapLegend.vue`         | Legend.                                       |
| `resources/js/types/organization.ts`                 | Organization types.                           |
| `resources/js/types/lab.ts`                          | Lab and map types.                            |

### Appendix F — Risks and mitigations

| Risk                                                              | Impact                                | Mitigation                                                                                                                    |
| ----------------------------------------------------------------- | ------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Map query is slow at millions of rows.                            | Poor UX.                              | Indexes, `DISTINCT ON`, summary-only payload; fallback to a `current_bacteria_states` table if measured p95 exceeds target.   |
| Cascade delete of a large lab is slow.                            | Request timeout.                      | Accept for demo; document; move to a queued deletion job later.                                                               |
| Stress seeder exceeds time or memory target.                      | Poor developer experience.            | Chunked bulk inserts, explicit IDs, sequence reset, progress output; measure and tune.                                        |
| Test DB configuration mismatch (`phpunit.xml` vs `.env.testing`). | Tests fail or hit the wrong database. | Verify and align the test database configuration during implementation.                                                       |
| Existing bacteria pages break from the new attribute.             | Regression.                           | Add the attribute with a default; run existing tests.                                                                         |
| Map DOM is heavy at 19,200 samples.                               | Slow rendering.                       | One mark per level (6,400 marks), CSS grid, content-visibility, and lazy sample details.                                      |
| Week selector range grows unbounded for old labs.                 | Long selector.                        | Seeded labs are 20–26 weeks old; for real labs, consider a range cap later.                                                   |
| Timezone/DST edge cases.                                          | Wrong week assignment.                | Unit tests at year and DST boundaries; store explicit week columns.                                                           |
| Ambiguity between "not tested" and "absent".                      | Misleading map.                       | `none`/`0` are transition-only; no row means never positive/not tested; both are hidden. Documented.                          |
| Membership UI absent.                                             | Hard to add users outside seeding.    | Accepted for this increment; seeder assigns memberships; add UI later.                                                        |
| ClickHouse replica is stale after re-seeding PostgreSQL.          | The ClickHouse engine shows old data. | Run `php artisan ch:load` after `DemoStressSeeder`; the lab map shows an error if the table is missing.                       |
| ClickHouse is unreachable or not loaded.                          | The lab map request fails.            | The controller catches the error, reports it, and renders an error banner with the `ch:load` command; PostgreSQL still works. |
| Session-selected organization is shared by every tab.             | Switching in one tab changes another. | Accepted; switching redirects to Labs and the selection is revalidated on every request.                                      |
| Bookmarks to `/dashboard` break.                                  | Confusing 404 for existing users.     | Dashboard removal is intentional; sign-in redirects to Labs and the README documents the page list.                           |
| A user has no organization memberships.                           | Labs page has nothing to show.        | The Labs page renders an empty state with a link to Manage Organizations, where the user can create one.                      |

### Appendix G — Open issues

| ID     | Issue                                                                                            | Status                                                                                                                                                                                                                                                                                                                                                                                                       |
| ------ | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| OI-001 | Exact visual palette for growth levels.                                                          | Resolved: HIGH red, MEDIUM amber, LOW emerald, count sky; the map uses colour-and-shape marks and the legend names the states.                                                                                                                                                                                                                                                                               |
| OI-002 | Whether to add a `current_bacteria_states` table if the map query misses its performance target. | Not needed: the largest seeded lab (6,400 levels, 19,200 samples) measures ~0.75 s in the browser with the snapshot engine (3.39 MB payload) over the 10x profile; the 3,600-sample lab measures ~644 KB, ~239 ms TTFB and ~313 ms load. The table stays deferred.                                                                                                                                           |
| OI-003 | Membership invitation/management UI.                                                             | Deferred.                                                                                                                                                                                                                                                                                                                                                                                                    |
| OI-004 | Manual observation creation UI.                                                                  | Deferred.                                                                                                                                                                                                                                                                                                                                                                                                    |
| OI-005 | Sample CRUD UI.                                                                                  | Deferred.                                                                                                                                                                                                                                                                                                                                                                                                    |
| OI-006 | Live ClickHouse mirroring and ingestion for sources other than the lab map replica.              | Deferred; the lab map replica is loaded explicitly by `ch:load`.                                                                                                                                                                                                                                                                                                                                             |
| OI-007 | Non-uniform lab layout UI.                                                                       | Deferred; schema supports it.                                                                                                                                                                                                                                                                                                                                                                                |
| OI-008 | Production deployment and seeder safeguards.                                                     | Deferred.                                                                                                                                                                                                                                                                                                                                                                                                    |
| OI-009 | Per-tab organization selection.                                                                  | Deferred; the selection is stored per user session, so tabs share it.                                                                                                                                                                                                                                                                                                                                        |
| OI-010 | Incremental ClickHouse sync and next performance experiments.                                    | Three engines are in place: raw and snapshot. The snapshot is the fast path (144 ms at the 19,200-sample lab, 656 ms at synthetic 5x; PostgreSQL takes 8,053 ms). The weekly rollup was tried and removed. The page returns structure and summaries in one request; there is no Postgres prefill or bacteria dictionary, and filters are replicated in their own tables. Next: incremental snapshot refresh. |

### Appendix H — Approval

| Role             | Name | Signature | Date |
| ---------------- | ---- | --------- | ---- |
| Product owner    |      |           |      |
| Engineering lead |      |           |      |
| QA lead          |      |           |      |

---

_End of SRS-OBS-001._
