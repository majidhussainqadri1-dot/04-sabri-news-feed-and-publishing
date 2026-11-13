# File 04 — current companion source parity refresh — 2026-11-13

**Evidence boundary:** source repository only. This is not a claim about installed WordPress/Hostinger plugin, database schema, migration, production operation, or successful browser behavior. File04 runtime/schema stay `2.0.8` / `1.3.0`.

## Trigger / root cause

The 2026-10-20 File04 frozen contracts remained at previous exact commits while five companion repositories subsequently advanced on main. Frozen-head CI green was valid for the old lock, not an independent confirmation against each newer main. This branch updates the frozen source snapshot and runs the existing exact-head source-contract + 20 unchanged-head regression checks. No application behavior, storage structure, migration routine or deploy artifact is changed by this PR.

| Module | Old fixed SHA | New verified main SHA | Compare / changed scope |
|---|---|---|---|
| File21 | f2eb7e95ddea327af36ea725ffb923b029f885e6 | 626b380328d4400014504e679032291cd023bbdb | 10 commits; 3 workflow files only |
| File22 | b7a7f2e69411cbd32f0574fd12d766fb70c01b7a | e8da36355f4cf94f49c960ee4f8330ca1e313722 | 12 commits; composer runtime/REST, policy, citations, tests |
| File23 | a8a8c805f4730998ccb44bd95c87591836561759 | dcae138e6073f4d0ff596623deb05b9940b8271b | 35 commits; publishing intelligence/other changes; read-only legacy diagnostics implementation file unchanged in compare |
| File25 | 59927df876dc92c7461351420c7b7c95c65c6a93 | 347a4ff4d4c233c5ea6cd82c7786ee5398ea9d1e | 19 commits; UI/integration, timeline and hardening; exact File20 integration contract file unchanged |
| File26 | fe405bda9cc30d8e1d8e76a9eb490c2d9b262521 | 1b064c6ea9f81195611ed001d5457e8ced65460a | 5 commits; indexer cursor/graph reindex safety and regression; File26 plugin contract unchanged |

Other seven source locks were equal to then-current `main` at this review. Verify again at each later release since main can advance.

## Required independent gates before source merge

1. Four CI jobs green on **this exact PR head**: architecture, PHP 8.1, PHP 8.3, and pinned 12-companion parity.
2. Exact File00/01/17/19/20/21/22/23/24/25/26/CF04 contract test green; 20 sequential unchanged-head read-only reviews green; deterministic installable and complete-source packages byte-identical.
3. Review File26 index/cursor impact, File22 mutation/REST behavior and File23 read-only adapter contract at actual staging. The current source test is vocabulary/contract oriented and does not prove runtime behavior.
4. Confirm File 04's public legacy migration remains strictly read-only toward old source and writes canonical content only through File21. No dual publisher.

## Live Reality Freeze / blocker

WPVibe could not connect to https://www.sabrihomeopathy.com; returned **HTTP 530**, root cause undetermined. WordPress/Jetpack site reported **site_disconnected**; plugin inventory was returned solely from an **old Jetpack snapshot synced 2026-07-14 01:33 UTC**, not current deployed evidence. Thus active File04 version, deployed checksum, DB/schema/wp_options, migration and error logs are all **UNKNOWN**, not PASS/FAIL.

**Do not deploy, publish, migrate, cut over or retire merely because CI passes.** First obtain authorized Hostinger exact deployed package/version/hash, schema/options, logs, staging restore, approvals and live reruns per master plan V12.1.

## Defect/blocker register

- F04-SRC-20261113-01: stale companion-source parity evidence for five repos, addressed by this review branch pending exact PR CI and reviewers; this is a governance/verification gap, not a demonstrated live-runtime bug.
- F04-LIVE-20261113-01: cannot freeze live deployed code/version/DB: WPVibe HTTP 530 + disconnected site; **BLOCKED**. Provider/origin diagnosis requires proper host evidence.

No production code patch, schema migration, deploy or user-data mutation authorized or attempted in this source review.
