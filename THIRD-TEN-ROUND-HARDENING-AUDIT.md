# File 04 — Third Fresh Ten-Round Hardening Audit

**Candidate release:** 2.0.3  
**Storage schema:** 1.3.0  
**Base reviewed:** main `54253e6de2dc68c2c57f7e0d4fd474bd0622de8e`  
**Method:** each round is a new Review → immediate correction → affected regression. Earlier ten-round audits are historical evidence only.

| Round | Focus | Result / correction |
|---|---|---|
| 1 | File04 plan behavior / controlled fallback | **Defect.** `read_only_fallback` was tombstone-only (410), conflicting with the plan's time-bounded read-only legacy fallback. Added immutable public-source fallback only while File21 target resolution is unavailable, with no-store/noindex and existing global write guards. |
| 2 | Evidence encoding / hashing / audit chain | **Defect.** JSON encoding failure could collapse checksums to SHA-256 of an empty string and audit context persistence could fail ambiguously. Added deterministic encode fallback for hashing and strict audit JSON rejection. |
| 3 | REST idempotency | **Defect.** `sanitize_text_field()` normalized opaque idempotency keys and could make distinct raw tokens collide. Keys are now exact printable ASCII bytes with strict length/character validation. |
| 4 | Backup/restore attestation | **Defect.** Restore verifier could return generic/stale `verified` evidence without proving the exact current request/source. Added request digest, source echo and fresh verification-time binding. |
| 5 | Cutover cache/search attestation | **Defect.** Cache/search provider evidence was not bound to the exact source/reconciliation request. Added source/reconciliation/request-digest/fresh-time verification. |
| 6 | Retirement route-handoff attestation | **Defect.** Batch/final route-handoff acknowledgements did not prove the exact request/previous-chain/source. Added request digests, source/chain echoes and freshness requirements. |
| 7 | Redirect loop integrity | **Defect.** Full-URL comparison could accept the same legacy path when only query/fragment differed. Redirect targets now require the same origin and a different normalized path. |
| 8 | Mapping/interactions evidence serialization | **Defect.** `wp_json_encode()` failure could persist blank/malformed ledger evidence while the write reported success. Mapping and interaction evidence now fail closed before DB writes. |
| 9 | Version/release/QA integration | **Defect.** Material third-audit changes were still represented as v2.0.2 and no deterministic third-audit gate existed. Runtime/docs/tests/builder/CI are aligned to v2.0.3 and the third-audit gate is first-class release evidence. |
| 10 | Final fresh adversarial regression after Round 9 | **Defect.** `target_for()==0` was ambiguous: it could mean a genuine File21 outage or a healthy File21 with missing/inconsistent canonical mapping. The Round-1 fallback could therefore expose an unmigrated/inconsistent legacy record. Fallback now requires an actual File21 outage, local status `migrated`, a prior nonzero canonical target ID, an unchanged source checksum, and zero open/conflict-ledger errors. Healthy-File21 mapping inconsistency fails closed. |

**Round 10 status: DEFECT FOUND AND CORRECTED.** The correction preserves File21 canonical ownership and limits public legacy fallback to a bounded outage bridge for records already proven to have migrated. Exact-head CI and deterministic packaging remain the final repository-level regression gate before merge.

## Evidence boundary

This audit proves repository/source behavior only. Hostinger staging, real File00/File21/File26 providers and data, browser/device accessibility, isolated restore, rollback/DR rehearsal, Founder approval, live deployment and measured operations remain separate release gates.
