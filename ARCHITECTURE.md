# File 04 v1.2.0 Architecture

## Constitutional role

**One canonical publication owner: File 21. One temporary legacy adapter: File 04.**

File 04 owns only migration evidence and compatibility lifecycle. It does not own publication truth after cutover.

## Components

- `SNFLA_Database`: schema, activation preflight, signed handover, compensation and locks.
- `SNFLA_Inventory`: bounded source inventory and deterministic source signature.
- `SNFLA_Migration`: complete dry-run, restore proof and bounded File 21 migration orchestration.
- `SNFLA_Interaction_Provider`: resumable interaction bridge and exact rollback ledger.
- `SNFLA_Mapping`: mappings, persistent dry-run rows, conflicts and interaction provenance.
- `SNFLA_Reconciliation`: complete verification, final delta and cutover evidence.
- `SNFLA_Redirects`: temporary lifecycle-gated 302 redirects and read-only fallback before retirement.
- `SNFLA_Rollback`: checkpointed publication/interaction rollback and optional handover restore.
- `SNFLA_Retirement`: bounded redirect/gone manifest handoff, final evidence, inert retired state and self-deactivation.
- `SNFLA_Plugin`: hidden legacy schema and mutation suppression.

## Invariants

- Legacy source is never deleted by migration or uninstall.
- No File 04 public composer/feed/ranking/moderation/interactions backend exists.
- Every mutation requires current File 00/File 21 authority and idempotency/locking where applicable.
- Every selected migration ID must appear unchanged in the current complete dry-run.
- Unsupported/ambiguous rich metadata, patient cases, attachments or comment metadata are explicitly quarantined, not silently coerced.
- Historical meta-only view aggregates are preserved exactly through the File 21 interaction repository; derived viral ranking is recomputed.
- Publication and approved-comment comparison uses bounded-memory deterministic digests and validates the expected File 21 workflow status.
- Cutover is impossible without green reconciliation, final delta and downstream cache/search evidence.
- Rollback is non-destructive, resumable and provenance checked.
- Retirement is blocked until a canonical route owner verifies the complete manifest; File 04 owns no permanent redirect runtime after retirement.
