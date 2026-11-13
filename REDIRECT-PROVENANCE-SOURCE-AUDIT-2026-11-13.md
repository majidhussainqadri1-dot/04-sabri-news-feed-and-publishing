# File 04 — legacy redirect provenance: source-only reproduced defect and regression, 2026-11-13

## Root cause / evidence
File04 is a temporary legacy migration adapter; canonical published truth belongs exclusively to File21. Baseline main `69cc056b88e43f87b53cf0f01514e5e4d38415a9`: `SNFLA_Redirects::handle()` fetched the local migration mapping but its public 301 decision checked only whether the File21 target was public and the destination URL was safe. It did not require a `migrated` local ledger row with matching canonical target, stored source/target evidence digests, File21 source provenance, or zero open local conflicts.

In an isolated PHP CLI exercise of the real `handle()` (no WordPress DB or external access), a local mapping of `status=conflict` plus an open `canonical_target_invalid` conflict still emitted **301**. A 13-case black-box regression added in this PR reproduced **9 unsafe source cases** against the original version: conflict status, partial interaction state, mismatched target, missing mapping, missing source/target digest, invalid canonical provenance, open conflict, and conflict-ledger read failure. The healthy case legitimately emitted 301.

## Correction
The 301 branch now also requires `canonical_redirect_mapping_valid()`: local row in `migrated` status, exact local↔File21 target equality, valid persisted source/target digest shapes, File21 `migration_target_valid()` provenance and no open conflicts. The conflict-ledger query-error sentinel is nonempty, so fails closed. It intentionally **does not** require an unchanged current File21 publication checksum, because File21 is allowed to edit canonical content after a successful cutover. The separate explicitly authorized legacy read-only fallback is unchanged.

The regression executes 13 actual `handle()` cases in separate CLI processes using `php -n`, and runs in **both PHP CI matrix jobs** plus deterministic release source gates. Before correction 9 scenarios failed; after correction 13/13 passed locally. The existing 16 unit assertions, architecture and byte-identical package rebuild also remained green locally.

## Governance / release boundary
This is a **source-level** bug, not a verified incident in the live WordPress instance. The v2.0.8 software remains an unreleased candidate and must always be identified by exact repository SHA and artifact checksum; no package is presumed deployed. Hostinger actual plugin/runtime version and hash, DB schema/options, migration state, backups and live route behavior remain unavailable because the connection returned HTTP 530 and Jetpack is disconnected. Until those are verified, no live patch, migration, rollback or retirement is authorized. Refer to issue #19. All historical review/audit documents remain unchanged.

## Acceptance
- Exact Pull Request HEAD: PHP 8.1 and 8.3, 13 redirect scenarios, native existing tests, 12 frozen companion source contracts, 20 unchanged-HEAD review rounds, secret/PII indicator scan, deterministic ZIP rebuild and retained artifact all green.
- Independent diff review: only the redirect provenance condition, executable regression, source build gates and this dated record changed.
- Final main HEAD retest and independent artifact SHA-256/integrity checks before marking source remediation complete.
- Actual browser/staging/Hostinger/live acceptance **remains a separately blocked gate**, regardless of CI.
