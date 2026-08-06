# File 04 — Forty-Round Review → Fix → Retest Audit

**Version:** 1.2.0  
**Rounds passed:** 40/40  
**Known unresolved blockers in locally reviewable source scope:** 0

Each round was treated as a fresh scoped review. Any identified defect was corrected before that round’s retest; the next round then reviewed the corrected source.

## Round 01 — Canonical scope and ownership
- **Finding:** Historical code risked acting as a second publishing system.
- **Correction:** Runtime is adapter-only; File 21 is declared canonical and no new feed/composer owner is present.
- **Retest:** PASS

## Round 02 — Version and schema identity
- **Finding:** Prior package/document identity remained at 1.2.0.
- **Correction:** Header, runtime and schema were aligned at 1.2.0.
- **Retest:** PASS

## Round 03 — File 00 and File 21 dependency gate
- **Finding:** Migration operations could not trust mere feature detection.
- **Correction:** Fail-closed current identity/2FA and File 21 package/runtime/class gates are required.
- **Retest:** PASS

## Round 04 — Multisite activation boundary
- **Finding:** Network-wide activation could produce cross-site ownership and migration ambiguity.
- **Correction:** Network-wide activation is rejected; activation is per site only.
- **Retest:** PASS

## Round 05 — Activation compensation
- **Finding:** A failed activation could leave newly-created adapter tables behind.
- **Correction:** Pre-activation table baseline and compensating removal of only newly-created adapter tables were added.
- **Retest:** PASS

## Round 06 — Cache invalidation
- **Finding:** Global cache flushing was over-broad and could disrupt unrelated modules.
- **Correction:** All global flushes were removed in favor of targeted File 21/cutover invalidation hooks.
- **Retest:** PASS

## Round 07 — Unsafe serialization
- **Finding:** Legacy evidence could be materialized through unsafe unserialization.
- **Correction:** Unsafe unserialize/maybe_unserialize paths were removed; evidence is treated as untrusted data.
- **Retest:** PASS

## Round 08 — Deterministic checksums
- **Finding:** Associative order, objects and non-finite values could yield unstable evidence hashes.
- **Correction:** Canonicalization and deterministic JSON encoding now cover arrays, objects, resources and special floats.
- **Retest:** PASS

## Round 09 — Inventory schema fingerprint
- **Finding:** Inventory evidence needed exact source-schema and data signatures.
- **Correction:** Inventory captures source records, comments, terms, attachments, interactions, routes and table fingerprints.
- **Retest:** PASS

## Round 10 — Bounded keyset traversal
- **Finding:** OFFSET pagination could skip or repeat records during concurrent legacy changes.
- **Correction:** All source traversal is cursor/keyset bounded; OFFSET is absent.
- **Retest:** PASS

## Round 11 — Inventory database failures
- **Finding:** Database read errors could be misread as empty inventory.
- **Correction:** Inventory and legacy-page discovery explicitly inspect database errors and fail closed.
- **Retest:** PASS

## Round 12 — Legacy page handover evidence
- **Finding:** Page quarantine evidence persistence failure could be ignored.
- **Correction:** Quarantine completion now requires exact restoration evidence to persist or activation fails.
- **Retest:** PASS

## Round 13 — Lifecycle concurrency
- **Finding:** State/version checks could become stale while waiting for locks.
- **Correction:** Lifecycle state/version is re-read under locks and transitions remain optimistic-version guarded.
- **Retest:** PASS

## Round 14 — Actor continuity
- **Finding:** The authenticated actor could change while a protected operation waited for a lock.
- **Correction:** A reusable revalidate_actor gate now compares the current canonical actor under every critical lock.
- **Retest:** PASS

## Round 15 — REST input schemas
- **Finding:** Mutation routes previously lacked uniform bounded schemas.
- **Correction:** State, version, IDs, hashes, limits and confirmation fields now have explicit REST validation.
- **Retest:** PASS

## Round 16 — REST idempotency
- **Finding:** Conflicting header/body keys or weak keys could permit replay ambiguity.
- **Correction:** Header/body equality, printable content and 16–190 length are enforced.
- **Retest:** PASS

## Round 17 — REST privacy and indexing
- **Finding:** Sensitive migration evidence could be cached or indexed.
- **Correction:** All REST success/error evidence is private no-store and noindex/noarchive.
- **Retest:** PASS

## Round 18 — CLI boolean handling
- **Finding:** A textual false value could still enable interaction migration.
- **Correction:** CLI now recognizes 0/false/no for --with-interactions.
- **Retest:** PASS

## Round 19 — Current File 21 compatibility
- **Finding:** An obsolete File 21 minimum could accept an incompatible target.
- **Correction:** Minimum package 1.0.3.2 and runtime contract 1.0.3 are frozen.
- **Retest:** PASS

## Round 20 — Canonical target provenance
- **Finding:** A target ID alone did not prove correct File 21 ownership.
- **Correction:** Target type, File 21 mapping and both legacy provenance metadata fields are verified.
- **Retest:** PASS

## Round 21 — No direct File 21 writes
- **Finding:** Containment fallback could mutate File 21 posts directly.
- **Correction:** Containment now calls File 21 LegacyPublicationRollback only; no direct post/table write exists in the adapter.
- **Retest:** PASS

## Round 22 — Interaction resume race
- **Finding:** Interaction resume could rely on mapping state read before lock acquisition.
- **Correction:** Mapping and canonical target are re-read and validated after operation/migration locks.
- **Retest:** PASS

## Round 23 — Interaction source schema probes
- **Finding:** Missing/query-failed interaction schemas could silently look empty.
- **Correction:** Table/column probes and source queries now produce explicit fail-closed errors.
- **Retest:** PASS

## Round 24 — Reaction migration
- **Finding:** Reaction ledger reads/inserts needed explicit database-failure handling.
- **Correction:** Source-record, canonical-row and ledger operations return/propagate WP_Error.
- **Retest:** PASS

## Round 25 — Save migration
- **Finding:** Save deduplication and contribution evidence required the same fail-closed behavior.
- **Correction:** Save records use the bounded canonical interaction ledger and checked queries.
- **Retest:** PASS

## Round 26 — View migration
- **Finding:** Meta-only and table views could be undercounted or silently lost.
- **Correction:** Exact contribution-ledger handling and query-error gates cover aggregate and table views.
- **Retest:** PASS

## Round 27 — Report migration
- **Finding:** Reports require preserved reason/status and verified user provenance.
- **Correction:** Reports are migrated through the typed ledger with missing-user/schema blockers.
- **Retest:** PASS

## Round 28 — Interaction reconciliation and rollback
- **Finding:** Canonical group/contribution reads could convert DB failures to zero.
- **Correction:** Group, contribution and original-row reads now return WP_Error and rollback propagates failures.
- **Retest:** PASS

## Round 29 — Dry-run retention
- **Finding:** TRUNCATE could destroy unrelated/simultaneous evidence.
- **Correction:** Dry-run cleanup uses scoped DELETE operations; TRUNCATE is absent.
- **Retest:** PASS

## Round 30 — Quarantine governance
- **Finding:** Quarantine decisions needed fresh actor checks and mapping-ledger error handling.
- **Correction:** Actor is revalidated under lock; mapping reads are checked; source-only evidence stays nonpublic.
- **Retest:** PASS

## Round 31 — Migration idempotency race
- **Finding:** A duplicate idempotency key could be inserted after the pre-lock check.
- **Correction:** Existing run is checked both before and after locks; corrupt/stale ledgers fail closed.
- **Retest:** PASS

## Round 32 — Mapping/conflict ledger failures
- **Finding:** Mapping/conflict DB errors could masquerade as absent records or zero conflicts.
- **Correction:** get_checked, fail-closed conflict codes and PHP_INT_MAX blocker count were added.
- **Retest:** PASS

## Round 33 — Backup and restore proof
- **Finding:** Backup evidence could be recorded by a stale actor or without source-bound restore proof.
- **Correction:** Current actor, SHA-256, UTC recency, restored counts, source signature and independent verifier are required.
- **Retest:** PASS

## Round 34 — Full reconciliation
- **Finding:** Disposition count query errors and invalid canonical targets could produce a false green report.
- **Correction:** Count reads fail closed and every target uses full canonical provenance validation.
- **Retest:** PASS

## Round 35 — Cutover side effects
- **Finding:** Cutover could proceed with stale actor/state or assumed cache/search completion.
- **Correction:** Actor/state are revalidated and provider-verified cache invalidation plus search reindex evidence is mandatory.
- **Retest:** PASS

## Round 36 — Redirect and fallback semantics
- **Finding:** Temporary redirects and 404 marking of gone records weakened canonicality.
- **Correction:** Canonical targets use 301; quarantined/fallback tombstones use 410 without false 404 state.
- **Retest:** PASS

## Round 37 — Rollback checkpoints and new-data protection
- **Finding:** Interaction reversal could begin before local rollback checkpoint persistence.
- **Correction:** Checkpoint failures now stop before interaction rollback; full target checksum/provenance gates protect changed/new File 21 data.
- **Retest:** PASS

## Round 38 — Retirement and self-deactivation
- **Finding:** Retirement could report success even if actor/state changed or plugin deactivation failed.
- **Correction:** Retirement revalidates actor/state/source, verifies route handoff and requires confirmed deactivation.
- **Retest:** PASS

## Round 39 — Packaging, CI and evidence
- **Finding:** The prior artifact did not preserve a 40-round, exact-source reproducible evidence set.
- **Correction:** Deterministic canonical-folder build, dependency manifest, CycloneDX SBOM, source inventory, release lock, secret scan and PHP 8.1/8.3 CI are included.
- **Retest:** PASS

## Round 40 — Fresh adversarial regression
- **Finding:** A final cross-cutting pass was required after all corrections.
- **Correction:** Fresh static, unit, architecture, packaging and all 40 scoped checks pass with zero known source-scope blockers.
- **Retest:** PASS

## Truthful release boundary

Locally reviewable source and reproducible package only; Hostinger staging, real File 00/File 21 integrations, browser/WCAG/RTL, backup/restore rehearsal, Founder acceptance, live deployment and operations remain separate gates.
