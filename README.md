# File 04 — News Feed and Publishing — Legacy Foundation Adapter v2.0.5

File 04 remains a **temporary, write-disabled, auditable and reversible migration/compatibility adapter**. File 21 is the sole canonical publication/Home/News/feed owner; File 26 owns search/discovery; File 20 owns the shell; File 25 owns the visual system; File 24 coordinates assurance. File 04 does not create a second composer, feed, ranking service, comments/reactions store, moderation backend, search engine or permanent public route system.

## Governing scope

The source trace covers the consolidated governing plan, File 04 FR-001..013 and NFR-001..010, 71 CV requirements (all applicable central requirements), F04-CEN-01..02, 15 acceptance journeys and F04-FUT-001..018.

## v2.0.5 fresh eighty-round hardening

A new sequential 80-round audit reopened the corrected source and applied **Review → immediate correction → affected regression** before every next round. **Rounds 1–65 discovered genuine repository/source defects and each was corrected before the next round. Rounds 66–80 were then completed as fresh focused regression reviews and found no new repository/source defect.** Those final clean rounds rechecked canonical ownership, File21/File26 boundaries, authorization, privacy, bounded queries, concurrency/idempotency, recovery, redirects/fallback, quarantine, PHP 8.1/8.3, accessibility/RTL, deterministic packaging and lifecycle truth. See `EIGHTY-ROUND-HARDENING-AUDIT.md`.

Storage schema remains **1.3.0** because these corrections do not require a custom-table schema migration.

## Truthful lifecycle status

Repository/source completion is separate from Hostinger staging, live deployment and operations. Source code must never self-promote `production_ready`; real File00/File21/File26 contracts, real legacy data, browsers/devices/WCAG/RTL, backup/isolated restore, rollback/DR rehearsal, Founder acceptance and production monitoring remain external gates.


## Second fresh eighty-round closure and cross-file contracts

The independent second eighty-round review is now represented through R80 at source-review level. R80 found and corrected residual cross-file gaps that the earlier source gates did not cover. The adapter now:

- resolves File 26 legacy IDs with strict canonical-positive identity semantics;
- publishes a File 01 module manifest, versioned File 04 migration contract and the restricted `/legacy-migration/report/` route through the canonical registry sync;
- binds that route to File 20's existing `system_recovery` context, whose canonical mode is `minimal`, rather than creating a second shell/layout system;
- exposes the canonical admin surface at `wp-admin/admin.php?page=sabri-legacy-feed` while retaining the historical slug only as a hidden compatibility alias;
- registers a File 19 producer for the four required File 04 lifecycle facts and uses a bounded retry outbox if File 19 is temporarily unavailable;
- publishes a bounded File 24 module-security manifest and `spcrc/file04_contract_state` assurance state without weakening native File 04 authorization or integrity controls;
- keeps privileged read-only status/System Check diagnostics available during File 21 outages while all state-changing operations remain fail-closed;
- executes `tests/run-cross-file-completion.py` in exact-head CI and deterministic release verification.

The plan event identities remain `LegacyMigrationBatchCompleted.v1`, `LegacyRecordQuarantined.v1`, `LegacyCutoverCompleted.v1` and `LegacyAdapterRetired.v1`. File 19's validator requires uppercase version segments, so the transport envelope maps these to the compatible `.V1` forms while retaining the exact plan event name in the source event metadata.

These are repository/source capabilities. File 01 registry synchronization, File 19 delivery, File 20/File 24 presence, File 26 acceptance, Hostinger staging, real-data migration, browser/accessibility checks, restore/rollback rehearsal, deployment and operational monitoring remain environment evidence and are not inferred from source.
