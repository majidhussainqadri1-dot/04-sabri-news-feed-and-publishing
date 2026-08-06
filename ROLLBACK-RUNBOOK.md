# File 04 v1.2.0 Rollback Runbook

## Principles

Rollback is non-destructive. Legacy records remain intact. File 21 targets are privated through File 21's native rollback command; File 04 interaction contributions are restored/neutralized from the exact ledger.

## Procedure

1. Verify current lifecycle version and backup/restore proof.
2. Select at most 100 mappings and provide a new stable idempotency key.
3. File 21 rolls back publication mappings.
4. File 04 immediately stores `publication_rolled_back_interactions_pending` checkpoints.
5. Interaction groups restore the first captured baseline exactly once.
6. Partial operations remain resumable and open blocker conflicts.
7. Lifecycle returns to a safe batch state only after every selected publication and interaction rollback completes.
8. Optionally request full activation-handover restoration only after no migrated/pending/conflicted mapping remains and the explicit confirmation string is supplied.
9. Re-run inventory and verify source, page, plugin and rewrite state.

Never restore the obsolete runtime while any canonical migration remains active.

## Retirement boundary

Retirement is permitted only after the complete redirect/gone manifest has been accepted and independently verified by a canonical route owner. After the `retired` transition, File 04 registers no routes, REST endpoints, migration hooks or redirect handler and attempts safe self-deactivation. Permanent redirects and gone responses are therefore not owned by File 04.
