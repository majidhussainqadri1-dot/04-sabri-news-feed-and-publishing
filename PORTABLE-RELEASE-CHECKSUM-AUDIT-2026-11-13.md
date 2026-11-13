# File04 — portable SHA-256 artifact manifest correction (2026-11-13)

## Reproduced source/release-evidence defect
On the exact prior main SHA `f48b8546b840d53ad1d01edf75478f1f5643ec11`, GitHub Actions main run `43351132347` (4/4 jobs green), artifact `14004480337` was downloaded and extracted. Both ZIP archives were intact. Yet running the natural offline command `sha256sum -c file04-checksums.sha256` from the artifact directory failed: the manifest referenced `/tmp/file04-a.zip` and `/tmp/file04-source-a.zip` on the **GitHub runner**, paths which do not exist after download. Independent manual digest comparison showed both ZIP hashes are correct:
- Installable: `aa63ee34e1c2cd43a23240f7ef9ee743f7df43c5eed20e96a5893bc1c3a0a169`.
- Complete source: `3aa740cc290149097c8524efd0100478365e801b6b46a2602b4d805d033f40f0`.

Thus this is a portable evidence/verification defect, **not** a demonstrated package tampering, application runtime issue, or live-site failure.

## Correction and acceptance
The workflow generates hashes inside `/tmp` using only artifact filenames, checks the file itself with `sha256sum -c`, and explicitly fails if it contains slashes. This retains the existing deterministic double-build `cmp`, credential/PII scan, SHA-pinned upload action and 14-day retention. Zero production PHP/schema/migration files change.

**Gate:** 4/4 exact PR-head jobs must pass, artifact retained, download archive extracted in an independent environment, `sha256sum -c file04-checksums.sha256` must pass without rewriting filenames. Then merge and recheck exact main HEAD. This does not authorize installation, cutover, or retirement.

## Live block
WPVibe read-only connection to the live domain still failed HTTP 530; WordPress Jetpack site remains disconnected, old plugin listing is only 2026-07-14 cached sync. Exact deployed code/hash, DB schema/options, migration lifecycle, backup restore and live retesting remain unknown (issue #19). GitHub source/artifact truth remains distinct.
