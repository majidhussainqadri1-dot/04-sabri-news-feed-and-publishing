# File 04 v1.3.0 — File-Specific Plan Traceability

This matrix supplements the central CV/CEN/AJ trace. It maps the File 04 master plan's native functional/non-functional requirements to the v1.3.0 source and current QA. External staging/live evidence remains separate.

| Requirement | Source implementation | Verification |
|---|---|---|
| F04-FR-001 Legacy inventory | `class-snfla-inventory.php`, source/data signatures, bounded traversal | unit + architecture + own-plan QA |
| F04-FR-002 Eligibility classification | `SNFLA_Migration::candidate_conflicts`, dry-run item ledger, quarantine reasons | own-plan QA + two fresh reviews |
| F04-FR-003 Canonical mapping | `class-snfla-mapping.php`, File 21 provenance validation | unit + reconciliation + own-plan QA |
| F04-FR-004 Dry-run | signed complete scan with counts/conflicts/dispositions/storage/time/sample preview; no canonical mutation | own-plan QA checks all required evidence fields |
| F04-FR-005 Batch migration | `MAX_BATCH`, operation/advisory locks, dry-run row binding, resumable interaction cursors, idempotency/lifecycle checks | unit + architecture + own-plan QA |
| F04-FR-006 Authorship mapping | File 00 immutable UUID contract `sabri_file00_platform_uuid_v1`; governed deleted/unknown-author placeholder contract | own-plan QA + migration preflight |
| F04-FR-007 Media/reference migration | bounded keyset attachment traversal, SHA-256/reference manifest, File 21 rights/ownership/alt/dedup/broken-link preflight and post-migration verification | own-plan QA + canonical provider acceptance in staging |
| F04-FR-008 Interaction reconciliation | File 21 interaction provider boundary and contribution ledger | unit/reconciliation + staging provider acceptance |
| F04-FR-009 Quarantine | source-only reasoned quarantine/conflict ledger; no silent coercion/delete | unit + architecture + migration acceptance |
| F04-FR-010 Cutover | final reconciliation/delta, verified cache invalidation, File 26 search reindex/handoff, lifecycle transition | architecture/own-plan QA + staging provider evidence |
| F04-FR-011 Redirect integrity | permanent canonical redirects, loop/nonpublic/tombstone handling | unit + browser/staging acceptance |
| F04-FR-012 Rollback | checkpointed non-destructive rollback, changed-target protection, canonical File 21 rollback command | unit + rollback rehearsal |
| F04-FR-013 Retirement | route manifest handoff, fallback expiry, zero conflicts, rollback proof, self-deactivation | unit + operational acceptance |

## Non-functional requirements

| Requirement | Implementation / evidence |
|---|---|
| F04-NFR-001 Object/field authorization | File 00 current-action/capability revalidation, strict REST schemas, nonce/authorization separation |
| F04-NFR-002 Privacy lifecycle | private/no-store/noindex evidence, redaction, source-only quarantine, privacy-safe audit; real export/delete/retention integration remains staging evidence |
| F04-NFR-003 Reliability | idempotency, locks, bounded batches, reconciliation, provider fail-closed behavior, rollback/retirement gates |
| F04-NFR-004 Performance | bounded keyset traversal, bounded queries, p75/p95/error-rate instrumentation, provider-supplied bounded media storage estimate |
| F04-NFR-005 Accessibility | logical RTL, visible focus, 44px controls, bidi isolation, reduced motion, forced-colors/reflow source safeguards; human WCAG acceptance remains staging |
| F04-NFR-006 Observability | System Check, privacy-safe endpoint metrics, operational alert hook, audit-chain verification |
| F04-NFR-007 Migration/Rollback | fresh/upgrade schema path, inventory lock, migration ledger, reconciliation, backup/restore proof, rollback checkpoints |
| F04-NFR-008 Operability | `/plan/system-check`, `/plan/metrics`, migration/rollback/operations runbooks, cache/search handoff evidence |
| F04-NFR-009 Compatibility | PHP 8.1/8.3 CI, WordPress API boundaries, explicit File 21 package/runtime gate; WordPress 7.0.1/PHP 8.3 Hostinger acceptance external |
| F04-NFR-010 Localization | RTL logical CSS/bidi isolation and versioned File 20/25 ownership; real Urdu/Arabic/English browser corpus external |

## Source QA gate

`tests/run-file04-own-plan.py` is the deterministic source-level gate for the above matrix. It is executed directly by exact-head CI and indirectly inside the two fresh review rounds. It checks that source completion does not silently broaden File 04 into a duplicate publication, community, visual, shell or search backend.

## External acceptance that cannot be encoded as a truthful source PASS

The following remain real-environment evidence, not missing source code: Hostinger fresh install/upgrade; real File 00/File 21/File 26 contract acceptance; representative live-data dry-run/migration/reconciliation; restore rehearsal; browser/mobile/RTL/WCAG/slow-network corpus; cache/search provider acknowledgement; Founder approval; controlled live rollout; monitored rollback window; final retirement evidence.
