# File 04 v2.0.8 — V12.3 Exact-Source and Deployment Evidence Status

**Evidence date:** 2026-11-14 (Asia/Karachi). **Scope:** source repository only unless explicitly marked otherwise.

Canonical source review identity and lifecycle flags (also consumed by both historical R80 source gates):

```text
source_status=v2.0.8-current-contract-corrective-candidate
staging_accepted_pending=true
live_deployed=false
operational=false
```


> **Never use this file to infer a deployed plugin version or a current live database.** GitHub, staging, and live evidence are independent. The approved governing Word document is *Sabri-Website-Master-Plan-V12.3.docx* dated 2026-11-13; the locally inspected attachment SHA-256 is `c061d9c9964d2434b040b814120d446e9004bd3b2f853eb355df1ccc4648e1cf`. This digest identifies the supplied artifact, not a deployed artifact.

## Exact source baseline (confirmed)

| Field | Evidence |
|---|---|
| Original audited repository `main` (historical snapshot) | `1e244e85c793314f8eda6c501af1b61c1dba6cfb` |
| Source plugin version | `2.0.8` (`sabri-news-feed-legacy-adapter.php`) |
| Source *declared* storage schema | `1.3.0` (not a live DB reading) |
| Last observed exact-main CI | Workflow run `43357015677`, success `4/4` jobs on `1e244e85c793314f8eda6c501af1b61c1dba6cfb` (2026-11-13) |
| Stored source candidate | Artifact ID `14006631811`, name bound to `1e244e85c793314f8eda6c501af1b61c1dba6cfb`; downloadable artifact's independent checksum and staging installation remain unverified |
| Source functionality | File 04 owns temporary legacy inventory, migration, reconciliation, redirects, rollback, retirement; File 21 alone owns canonical publication/feed truth |
| Active release contract | `CEN-REL-001`: zero known open defects in in-scope functionality and shared dependencies; two review/fix/retest rounds; live retest after approved deployment |
| Live deployment / operational status | **UNVERIFIED / NOT ACCEPTED** |
| Live DB version / migration state / installed plugin hash | **UNKNOWN**, not `1.3.0` by assumption |

## Subsequent verified source snapshots (historical, never deployed evidence)

- **2026-11-14** PR #27 merged at \`6b218049107a5789b218a9faeca67a950d950ef8\`; source documentation and V12.3 coverage/test guard updated.
- **2026-11-14** PR #28 merged at \`3e7a32d780b2eeb6f67e385914f441020ee99c4e\`; 12-source-companion parity CI run \`43593758072\` passed all 4 jobs.
- **2026-11-14** PR #29 merged at \`21422390d76cdc21386bebeb6138de0012e283bc\`; exact-main CI \`43599406648\` passed all 4 jobs, CI candidate artifact \`14115432678\`. Independently validated package and complete source ZIP bytes are source proof only.
- **New P02 deep-audit work:** documented separately in \`F04-V12.3-P02-MALFORMED-EVIDENCE-REGRESSION-2026-11-14.md\`. Do not infer its CI status from the preexisting green snapshots.
- **Live remains blocked:** HTTP 530; Jetpack site disconnected; deployed code hash, actual DB schema, migration state, staging, restore and live retest unverified.

Do not call any snapshot the live version or assume this document updates itself after subsequent commits.

## Current discrepancy register

- **DOC-001**: Previous `STATUS.md` reported older exact-head CI `43342118266` on `9a83f65d...`. Corrected in the V12.3 source documentation using run `43357015677`; docs-only correction, not a staging/live fix.
- **DOC-002**: Previous `ARCHITECTURE.md` described `v1.2.0` and temporary `302` routing, while present v2.0.8 runtime implements conditional canonical `301` on independently validated mapping. Corrected in the architecture document to match inspected source; no production behavior is established.
- **PAR-001 (File 00)**: prior frozen ref `2fa7c022...` was 13 commits behind; exact current-main ref `1fad051c86d530d558be3eb1890d3c6e67bce6e8` is now proposed in source lock after verifying both File04-consumed identity contract blobs are identical. External provider acceptance remains pending.
- **PAR-002 (File 19)**: prior frozen ref `04078025...` was 70 commits behind; exact current-main ref `66a4de6077e32c4c6cd692ae3d906afc4a5f3eb2` is now proposed in source lock after confirming unchanged File04 event-consumer API and producer-registry blobs. Staging acceptance remains pending.
- **PAR-003 (File 22)**: prior frozen ref `e8da3635...` was 5 commits behind; exact current-main ref `ed672a0d271f10e2cf224951d9f3622a44a70152` is now proposed in source lock; File22's ownership README blob was identical. Staging acceptance remains pending.
- All 12 source companion refs were committed through PR #28 and verified against their observed audited main SHAs; this does **not** establish staging or live contract acceptance. See `F04-V12.3-COMPANION-PARITY-AUDIT-2026-11-14.md`. Exact PR-head CI and post-merge main check remain separate.
- **LIVE-001**: WordPress connector reports Jetpack site disconnected; WPVibe site_info had no connected credentials, and connect attempt returned HTTP 530. The failure hop is not proven. Existing incident `#19` remains open.

The above are **2 documentation corrections (merged PR #27), 3 reviewed version-drift observations (PR #28 merged; exact-main CI 43593758072 passed), and 1 live-evidence access blocker**, not six independently verified runtime bugs.

## V12.3 source compliance versus external gates

The source registry currently enumerates 71 applicable CV IDs, 2 `F04-CEN` IDs, 15 AJ journeys, 13 `F04-FR` IDs, 10 `F04-NFR` IDs and 18 optional `F04-FUT` IDs. These are requirements, not numbers of user-facing features. Source tests exist; **V12.3 M11/QA-24 production acceptance is not demonstrated by CI**.

The required production sequence is:
`live symptom → authorized read-only deployed evidence → deployed version and DB/migration → parity audit → reproducible source root cause → minimal fix/test → package → staging backup/restore/migration/reconciliation → founder approval → limited deployment → live re-test → deployed-hash parity confirmation`.

Until the live evidence bundle is available, **Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Status by phase

| Phase | Current status |
|---|---|
| P00 source baseline / V12.3 mandate | Historical source evidence reviewed; later source snapshots listed below |
| P01 Live Reality Freeze | BLOCKED: HTTP 530 / disconnected Jetpack / no authenticated live artifact |
| P02 deployed/source parity | UNVERIFIED |
| P03 documentation correction | Corrected and merged in PR #27; new branches require independent exact-head CI |
| P04 V12.3 mapping | Source/evidence registry in `F04-V12.3-EXECUTION-EVIDENCE.md`; external tests pending |
| P05 root-cause source corrections | No additional reproducible File 04 runtime defect established by this audit |
| P06 exact source CI / release candidate | Original and later exact-main snapshots PASS; any new HEAD must be verified separately |
| P07–P13 staging, inventory, migration, restore, integration, acceptance | BLOCKED / NOT PERFORMED in this session |
| P14 controlled live rollout | NOT PERFORMED; founder approval and parity required |
| P15 monitoring / retirement | NOT PERFORMED; separately authorized retirement required |

**Defect accounting:** Found=2 documented content defects; source-document corrections=2 (PR merge and later exact-head evidence separately); source runtime defects confirmed in this review=0; live-fixed=0; active external blockers=1; source-companion parity fixes merged=3 (verified on 2026-11-14 main run 43593758072). Never close the live incident based solely on this source correction.
