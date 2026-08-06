# File 04 v1.2.0 — Requirements Traceability

## Governing sources

- **G1:** Definitive Master Plan v3.0 (`SSH-PMP-2026-v3.0`).
- **G2:** Consolidated All-Chats Recovered Directives v2.1.
- **G3:** File 04 Complete Master Plan v1.0 (`SSH-F04-PLAN-2026-v1.0`).

| Requirement | Source | Implementation | Verification |
|---|---|---|---|
| File 21 sole canonical publication owner | G1 R-04; G3 Charter | File 21 adapter, no parallel publishing backend | architecture round 1/21 |
| File 04 temporary write-disabled source adapter | G1/G3 | legacy schema guards and inert retired boot | rounds 1/38 |
| File 00/File 21 fail-closed authority | G1/G2/G3 | capabilities + lock-time actor revalidation | rounds 3/14 |
| Compensating activation/handover | G2/G3 | signed snapshot, page/plugin restoration, new-table compensation | rounds 4/5/12 |
| Deterministic complete inventory | G1/G3 | keyset traversal, schema/data signatures, DB error checks | rounds 8–11 |
| Dry-run and source-only quarantine | G1/G3 | per-run candidate ledger and governed disposition | rounds 29/30 |
| Backup and isolated restore proof | G1/G2/G3 | source-bound verifier contract and exact counts | round 33 |
| Bounded/resumable migration | G1/G2/G3 | batch limits, idempotency, persistent interaction cursors | rounds 22–31 |
| Exact interactions migration | G1/G3 | reactions/saves/views/reports contribution ledger | rounds 23–28 |
| Full reconciliation/final delta | G1/G3 | complete source scan, mapping/target checks, audit chain | round 34 |
| Cache/search cutover proof | G2/G3 | verified provider evidence | round 35 |
| Canonical redirects and tombstones | G1/G3 | 301 targets, 410 source-only/gone state | round 36 |
| Non-destructive rollback | G1/G2/G3 | local checkpoint before canonical interaction reversal | round 37 |
| No permanent File 04 dependency | G1 R-04; G3 | route manifest handoff + verified self-deactivation | round 38 |
| Deterministic package/evidence | G1/G2 | canonical-folder build tool, dependency manifest, CycloneDX SBOM, inventory, checksums, release lock, CI | round 39 |
| Continuous fresh review law | G2/G3 | `FORTY-ROUND-AUDIT.md/json` and round gate | round 40 |
| Truthful status separation | G1/G2/G3 | `STATUS.md`, REST status and release lock | release review |

## Forty-round evidence

The complete review ledger is stored in `FORTY-ROUND-AUDIT.md` and `FORTY-ROUND-AUDIT.json`. Every round records its scope, finding, correction and retest result. A failure in any round blocks package verification.
