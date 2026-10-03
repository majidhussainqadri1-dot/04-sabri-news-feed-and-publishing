# File 04 v2.0.8 — Current Exact-Head Corrective Status Register

`source_status=v2.0.8-current-contract-corrective-candidate`
`known_unresolved_source_scope_blockers=0`
`exact_companion_parity_ci=pending`
`staging_accepted_pending=true`
`live_deployed=false`
`operational=false`

| Status | Decision | Evidence boundary |
|---|---|---|
| Specified | **Complete for current source scope** | Consolidated plan + File04 plan + CV/CEN/AJ + FR/NFR + Future18 |
| Coded | **Current exact-head defects corrected in v2.0.8 candidate** | File04 plus exact locked File00/01/17/19/20/21/22/23/24/25/26/CF04 source contracts |
| Packaged | **v2.0.8 candidate; exact-head deterministic build required** | Installable + complete-source byte-identical double build |
| Automated-QA Green | **Pending exact File04 PR-head CI** | Native gates + PHP 8.1/8.3 + exact locked companion parity + deterministic package |
| Staging-Accepted | **Pending** | Real WordPress/Hostinger integration, browser, restore, migration and rollback evidence |
| Live-Deployed | **Pending** | Founder-approved controlled deployment |
| Operational | **Pending** | Sustained monitoring/support/backup/incident/retirement evidence |

v2.0.8 follows the historical review cycles and a fresh current-head review. It corrects executable REST inventory/dry-run defects, adds a validated bounded inventory summary, completes File21 metadata and featured-media relation handoffs with post-migration verification/containment, publishes File23's read-only diagnostics, and freezes every materially related current companion head. Repository evidence does not prove staging, deployed code, database schema, migration execution, or live behavior.

The exact companion evidence is frozen in `COMPANION-CONTRACT-LOCK.json`. `tests/run-exact-companion-contract-parity.py` must verify the checked-out commits byte-for-source-contract, and `tests/run-twenty-round-current-head.py` must prove 20 sequential read-only green rounds on one unchanged candidate, before this candidate can be called Automated-QA Green.

File 04 remains a temporary migration/compatibility adapter only. File21 retains publication/feed truth; File26 retains search/discovery; File20 retains shell ownership; File25 retains visual ownership; File24 coordinates assurance while native controls remain active. Source/CI completion does not imply Staging-Accepted, Live-Deployed or Operational. Storage schema remains **1.3.0**.
