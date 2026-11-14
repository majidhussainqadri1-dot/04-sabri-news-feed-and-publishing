# File 04 v2.0.8 — V12.3 Exact-Source and Deployment Evidence Status

**Evidence date:** 2026-11-14 (Asia/Karachi). **Scope:** source repository only unless explicitly marked otherwise.

Canonical lifecycle flags (also consumed by the historical R80 source gate):

```text
staging_accepted_pending=true
live_deployed=false
operational=false
```


> **Never use this file to infer a deployed plugin version or a current live database.** GitHub, staging, and live evidence are independent. The approved governing Word document is *Sabri-Website-Master-Plan-V12.3.docx* dated 2026-11-13; the locally inspected attachment SHA-256 is `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`. This digest identifies the supplied artifact, not a deployed artifact.

## Exact source baseline (confirmed)

| Field | Evidence |
|---|---|
| Repository `main` at audit | `1e244e85c793314f8eda6c501af1b61c1dba6cfb` |
| Source plugin version | `2.0.8` (`sabri-news-feed-legacy-adapter.php`) |
| Source *declared* storage schema | `1.3.0` (not a live DB reading) |
| Last observed exact-main CI | Workflow run `43357015677`, success `4/4` jobs on `1e244e85c793314f8eda6c501af1b61c1dba6cfb` (2026-11-13) |
| Stored source candidate | Artifact ID `14006631811`, name bound to `1e244e85c793314f8eda6c501af1b61c1dba6cfb`; downloadable artifact's independent checksum and staging installation remain unverified |
| Source functionality | File 04 owns temporary legacy inventory, migration, reconciliation, redirects, rollback, retirement; File 21 alone owns canonical publication/feed truth |
| Active release contract | `CEN-REL-001`: zero known open defects in in-scope functionality and shared dependencies; two review/fix/retest rounds; live retest after approved deployment |
| Live deployment / operational status | **UNVERIFIED / NOT ACCEPTED** |
| Live DB version / migration state / installed plugin hash | **UNKNOWN**, not `1.3.0` by assumption |

## Current discrepancy register

- **DOC-001**: Previous `STATUS.md` reported older exact-head CI `43342118266` on `9a83f65d...`. Corrected here using run `43357015677`; source docs correction only, pending PR-head CI.
- **DOC-002**: Previous `ARCHITECTURE.md` described `v1.2.0` and temporary `302` routing, while present v2.0.8 runtime implements conditional canonical `301` on independently validated mapping. Corrected in the accompanying architecture document; source docs only.
- **PAR-001 (File 00)**: locked `2fa7c022ee9cd1b65432e900579512f304532442`; observed `main` `1fad051c86d530d558be3eb1890d3c6e67bce6e8` (13 commits ahead). Primarily purpose-scoped File 09 doctor-verification intake; **impact on File 04 provider contracts requires review; no auto-pin**.
- **PAR-002 (File 19)**: locked `04078025b643ab7696e4cb4e37826bf152defa18`; observed `main` `66a4de6077e32c4c6cd692ae3d906afc4a5f3eb2` (70 commits ahead). Changes include notification privacy, provider receipts, Safe Mode, registry readiness, and read-only evidence collector; **contract review required; no auto-pin**.
- **PAR-003 (File 22)**: locked `e8da36355f4cf94f49c960ee4f8330ca1e313722`; observed `main` `ed672a0d271f10e2cf224951d9f3622a44a70152` (5 commits ahead). Upload expiry, REST body bound, RTL textarea, citation safety; **contract review required; no auto-pin**.
- Nine other locked companions matched their observed `main` during this audit; this does **not** establish staging or live contract acceptance.
- **LIVE-001**: WordPress connector reports Jetpack site disconnected; WPVibe site_info had no connected credentials, and connect attempt returned HTTP 530. The failure hop is not proven. Existing incident `#19` remains open.

The above are **2 confirmed documentation discrepancies, 3 version-drift observations, and 1 live-evidence access blocker**, not six independently verified runtime bugs.

## V12.3 source compliance versus external gates

The source registry currently enumerates 71 applicable CV IDs, 2 `F04-CEN` IDs, 15 AJ journeys, 13 `F04-FR` IDs, 10 `F04-NFR` IDs and 18 optional `F04-FUT` IDs. These are requirements, not numbers of user-facing features. Source tests exist; **V12.3 M11/QA-24 production acceptance is not demonstrated by CI**.

The required production sequence is:
`live symptom → authorized read-only deployed evidence → deployed version and DB/migration → parity audit → reproducible source root cause → minimal fix/test → package → staging backup/restore/migration/reconciliation → founder approval → limited deployment → live re-test → deployed-hash parity confirmation`.

Until the live evidence bundle is available, **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Status by phase

| Phase | Current status |
|---|---|
| P00 source baseline / V12.3 mandate | Evidence reviewed; this PR provides updated records |
| P01 Live Reality Freeze | BLOCKED: HTTP 530 / disconnected Jetpack / no authenticated live artifact |
| P02 deployed/source parity | UNVERIFIED |
| P03 documentation correction | Proposed in this branch; mark fixed only after PR checks/review and merge |
| P04 V12.3 mapping | Source/evidence registry in `F04-V12.3-EXECUTION-EVIDENCE.md`; external tests pending |
| P05 root-cause source corrections | No additional reproducible File 04 runtime defect established by this audit |
| P06 exact source CI / release candidate | Historical exact-main 4/4 PASS; this PR-head **not presumed green** |
| P07–P13 staging, inventory, migration, restore, integration, acceptance | BLOCKED / NOT PERFORMED in this session |
| P14 controlled live rollout | NOT PERFORMED; founder approval and parity required |
| P15 monitoring / retirement | NOT PERFORMED; separately authorized retirement required |

**Defect accounting:** Found=2 documented content defects; fixed-in-branch=2 pending review; source runtime defects confirmed in this review=0; live-fixed=0; active external blockers=1; version drifts requiring review=3. Never close the live incident based solely on this source correction.
