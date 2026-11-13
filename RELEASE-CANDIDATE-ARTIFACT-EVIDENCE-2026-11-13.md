# File04 v2.0.8 candidate artifact preservation — 2026-11-13

## Verified gap
Earlier exact-head CI built installable and complete-source ZIPs twice in `/tmp` and compared bytes, but the run's artifact listing was empty. Thus no retained ZIP/checksum was available to carry into the later **separate** staging acceptance gate. This is a source/release traceability gap, **not** a demonstrated runtime or deployment defect.

## Correction
After build verification and privacy/secret indicator scan, the integrity-and-architecture job now writes `/tmp/file04-checksums.sha256` and uploads the two identical candidate ZIP outputs, JSON manifest and checksum manifest with a SHA-pinned `actions/upload-artifact` action. Artifacts expire after 14 days; unique artifact names bind to `github.sha`.

## Checks required
1. Both ZIP double-build `cmp` checks remain mandatory; checksum manifest matches the downloaded ZIP bytes.
2. Upload step must fail when files are absent.
3. Exact PR-head 4/4 workflow passes and yields exactly one named candidate artifact.
4. Run artifact is **not** a GitHub Release, not a Hostinger-deployed plugin, and not permission to perform migration/cutover.
5. Before staging or live use, validate downloaded ZIP SHA and all authorizations, DB version, wp_options and backups. Keep private Hostinger evidence outside the public repo.

Deployment version, database version and live status remain **unknown** because P0 read-only site access is blocked by HTTP 530. See issue #19.
