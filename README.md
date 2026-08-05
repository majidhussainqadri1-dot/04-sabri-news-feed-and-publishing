# File 04 — Sabri News Feed Legacy Foundation Adapter

File 04 version **1.0.0** is the time-bounded migration and compatibility adapter for historical `snp_publication` records. It is not a second publishing system. Canonical ownership belongs to **File 21 — Sabri Complete Home and News Feed**.

## Canonical boundary

File 04 may inventory, checksum, dry-run, map, quarantine, migrate, reconcile, redirect, roll back, replay and retire legacy records. It must not create new legacy publications, render a feed, rank content, own comments/reactions/saves/reports, expose a public composer, mutate global navigation, dual-write, delete the legacy source or repair File 21's schema.

## Release identity

- Plugin: `Sabri News Feed Legacy Foundation Adapter`
- Slug: `sabri-news-feed-legacy-adapter`
- Version: `1.0.0`
- PHP prefix: `SNFLA_`
- Canonical destination: File 21 package `1.0.3.2+`, runtime `1.0.3+`
- Legacy source post type: `snp_publication`
- Legacy source taxonomy: `snp_topic`

## Safety properties

- File 00 fresh identity and two-factor assurance is required for operational actions.
- Canonical capabilities are required; ordinary WordPress administrators receive no automatic migration authority.
- Source posts, metadata, comments and interactions remain read-only and are never deleted automatically.
- Every batch requires a matching dry-run, unchanged source signature, recent backup/restore proof, idempotency key and database lock.
- File 21 alone creates canonical posts and canonical interaction rows.
- File 04 records checksums, mappings, conflicts, checkpoints and a tamper-evident audit chain.
- Rollback is non-destructive: canonical posts become non-public through File 21 and interaction rows return to their prior state or a safe inactive state.
- Redirects are lifecycle-gated, same-origin, loop-protected and non-cacheable before final retirement.
- Retirement requires green reconciliation, zero conflicts, backup proof, a rollback rehearsal, an expired fallback observation window and exact typed confirmation.

## Operational interfaces

REST namespace: `sabri/v1/legacy/file-04`

- `GET /status`
- `POST /inventory`
- `POST /dry-run`
- `POST /backup-proof`
- `POST /migrate`
- `POST /reconcile`
- `POST /cutover`
- `POST /fallback`
- `POST /rollback`
- `POST /retire`
- `POST /conflicts/resolve`

WP-CLI namespace: `wp snfla`.

The WordPress administrative evidence center is available under **Tools → File 04 Legacy Adapter**. Mutations intentionally remain REST/WP-CLI operations so their machine contracts, nonces, idempotency keys and evidence are explicit.

## Deployment status

Automated source QA can establish source integrity and architecture alignment. WordPress staging, real File 00/File 21 integration, backup restoration, browser/cache behavior, database concurrency, migration data reconciliation and rollback rehearsal must still pass before merge, production or retirement authorization.
