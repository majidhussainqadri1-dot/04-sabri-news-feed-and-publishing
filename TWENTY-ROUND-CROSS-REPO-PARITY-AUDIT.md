# File 04 — Fresh Twenty-Round Exact Companion-Parity Audit

**Candidate:** 2.0.7  
**Repository role:** temporary migration/compatibility adapter; File 21 remains canonical publishing owner.  
**Evidence class:** repository/source only. Staging, deployment, live database, migration state and operational acceptance are separate.

This audit was performed after the historical two eighty-round source-local hardening cycles. Its purpose was different: compare File 04 against the **current exact source contracts** of File 00, 01, 19, 20, 21, 24, 25 and 26 instead of accepting File 04's own mocks/string gates as cross-file proof.

| Round | Review boundary | Result before correction | Corrective disposition |
|---:|---|---|---|
| 1 | File04 canonical ownership / no second publishing backend | Clean | No change |
| 2 | FR-001..FR-013 end-to-end mapping | Defect | Cross-file gaps below corrected |
| 3 | NFR-001..NFR-010 end-to-end mapping | Defect | Exact companion and assurance gates added |
| 4 | File00 privileged-action identity semantics | Defect | File21 now honors File00's explicit retired-MFA assertion instead of demanding retired factors |
| 5 | File00 immutable author UUID / unknown-author path | Defect | File00 now provides File04 UUID and governed unknown/deleted-author placeholder contracts |
| 6 | File01 module/route registry | Defect | File04 now uses canonical trailing-slash routes, omits invalid page_id=0 and preserves owner/version controls |
| 7 | File19 producer/event ingestion | Clean | Existing File19 producer registry and ingestion contract retained |
| 8 | File20 shell/layout boundary | Defect | File04 route context aligned to canonical system_recovery vocabulary |
| 9 | File21 package/runtime/class availability gate | Clean | Supported baseline retained; exact companion SHA now separately pinned |
| 10 | File21 authorship/media migration contract | Defect | File21 consumes canonical author context and implements media preflight/post-migration verification; File04 forwards full evidence |
| 11 | File22 composer boundary | Clean | File04 remains migration-only and does not become a composer/write owner |
| 12 | File24 assurance manifest | Defect | Missing complete-contract fields added |
| 13 | File25 visual ownership boundary | Clean | File25 visual ownership and File20 structural ownership preserved |
| 14 | File26 search/reindex/legacy resolution handoff | Defect | File26 implements File04 acceptance, bounded File21 reindex receipt/verification and legacy-resolution compatibility |
| 15 | Inventory/dry-run/locks/state-machine internals | Clean | Existing controls retained |
| 16 | Cutover/reconciliation/redirect/rollback/retirement | Defect | Search handoff now binds to actual File26 contract; existing cache/rollback/retirement gates retained |
| 17 | Security/privacy/fail-closed native controls | Clean | Existing native controls retained |
| 18 | System Check / readiness truth | Defect | Exact companion parity is now a separate QA gate; source cannot self-promote to production |
| 19 | CI / regression evidence | Defect | Frozen companion lock + exact checkout parity test added |
| 20 | Final adversarial cross-file re-review | Defect | Release raised to 2.0.7 candidate and source truth/documentation corrected; exact PR-head CI required before green claim |

## Corrected companion repositories

- File 00: immutable File04 author UUID projection and governed unknown/deleted legacy-author placeholder.
- File 21: current File00 retired-MFA compatibility; canonical author-context consumption; File04 media/reference preflight and post-migration verification.
- File 26: File04 contract acceptance; bounded File21 search reindex acceptance receipt; exact cutover verification; legacy resolution wrapper.
- File 04: File01 route canonicalization; File20 context alignment; complete File24 assurance manifest; full File21 media evidence forwarding; exact companion lock and parity gate.

## Frozen exact companion evidence

The authoritative repository/source comparison set is stored in `COMPANION-CONTRACT-LOCK.json`. CI must check out those exact commits and run `tests/run-exact-companion-contract-parity.py`. A later companion main update is a **new reality** and requires parity re-review; this document does not claim that floating future heads remain compatible.

## Lifecycle boundary

This audit can establish only source/repository compatibility. It does **not** establish Hostinger staging acceptance, deployed plugin parity, live DB/schema state, live migration state, browser/WCAG acceptance, restore rehearsal, controlled deployment or operational monitoring.
