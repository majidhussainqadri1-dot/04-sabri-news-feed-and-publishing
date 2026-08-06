# File 04 — Sabri News Feed Legacy Foundation Adapter

File 04 version **1.2.0** is a temporary, write-disabled, auditable and reversible migration/compatibility adapter for historical `snp_publication` records. **File 21 — Sabri Complete Home and News Feed** remains the sole canonical owner of publications, Home/News rendering, comments, reactions, saves, views, reports, moderation, ranking and public routes.

## Governing basis

1. **Sabri Social Homeopathy Platform — Definitive Master Plan v3.0** (`SSH-PMP-2026-v3.0`).
2. **Consolidated All-Chats Recovered Directives v2.1**.
3. **File 04 — News Feed and Publishing — Legacy Foundation Adapter Complete Master Plan v1.0** (`SSH-F04-PLAN-2026-v1.0`).

## Canonical boundary

File 04 may inventory, checksum, dry-run, quarantine, migrate through versioned File 21 commands, reconcile, cut over legacy routes, provide a time-bounded read-only tombstone fallback, roll back/replay and retire. It does **not** own a new public feed, composer, ranking service, moderation database, interaction backend, public route family or permanent runtime dependency.

## Version 1.2.0 corrective controls

- File 00 current identity/step-up and File 21 package/runtime/class fail-closed gates.
- Per-site activation only; network-wide activation is rejected.
- Signed activation handover snapshot and compensation, including removal of only adapter tables created by a failed activation.
- Complete source/page/schema inventory with stable, deterministic checksums and keyset traversal.
- No unsafe unserialization, `OFFSET`, `TRUNCATE` or global cache flushing.
- Bounded complete dry-run and explicit source-only quarantine for unsupported/private/ambiguous records.
- Current actor and expected lifecycle version revalidated after every critical operation lock.
- Strict REST request schemas, nonce checks, printable 16–190 character idempotency keys, no-store/noindex evidence responses and privacy-safe errors.
- File 21 canonical target validation using target type, File 21 mapping and both provenance metadata fields.
- No direct File 21 post/table writes; canonical migration and rollback commands only.
- Bounded/resumable reactions, saves, views and reports migration with exact contribution/rollback ledger and fail-closed database reads.
- Independently verified, source-bound backup and isolated restore rehearsal evidence.
- Full reconciliation, final-delta proof, audit-chain verification, cache invalidation and search-reindex provider evidence before cutover.
- Permanent 301 canonical redirects; governed 410 tombstones for source-only quarantine/fallback records without false WordPress 404 state.
- Checkpointed non-destructive rollback that stops before interaction reversal if local rollback evidence cannot be persisted.
- Complete redirect/gone manifest handoff before retirement; retirement requires current authority, expired fallback window, rollback proof and confirmed plugin self-deactivation.
- Forty distinct Review → Fix → Retest rounds, deterministic source/package evidence, canonical `04-sabri-news-feed-legacy-adapter` ZIP folder, dependency/contract manifest, CycloneDX SBOM, PHP lint/unit/architecture tests and CI matrix for PHP 8.1/8.3.

## Controlled runtime sequence

1. Install only on staging with compatible File 00 and File 21 builds.
2. Capture and lock exact legacy source inventory.
3. Execute the complete dry-run; resolve or explicitly quarantine every exception.
4. Record independently verified backup and isolated restore evidence.
5. Migrate bounded batches through File 21; resume all interaction cursors.
6. Reconcile every source record, target projection and interaction contribution.
7. Prove final delta, cache invalidation and search reindex; then approve cutover.
8. Serve permanent canonical redirects and bounded read-only tombstones.
9. Rehearse non-destructive rollback and, where required, full activation-handover restoration.
10. Hand the complete redirect/gone manifest to the canonical route owner; retire and self-deactivate only after all gates and Founder approval.

## Truthful completion status

- **Specified:** complete.
- **Coded:** locally reviewable 1.2.0 staging candidate.
- **Packaged:** deterministic installable and complete-source candidates.
- **Automated-QA Green:** local PHP lint, deterministic unit checks, architecture checks and 40/40 scoped rounds pass; exact GitHub head CI remains a separate evidence gate until run.
- **Staging-Accepted / Live-Deployed / Operational:** not claimed. Hostinger, real File 00/File 21 contracts and data, browser/RTL/WCAG, backup/restore, rollback, Founder acceptance, deployment and monitoring remain mandatory.
