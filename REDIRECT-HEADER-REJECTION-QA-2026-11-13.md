# File04 — explicit failure response when a canonical 301 redirect is rejected (2026-11-13)

## Reproduced cause and scope

This is an **independent source defect** discovered after source-level remediation of conflicted migration mappings in PR #23. Exact baseline main: `080bae8a49a364a2ab22dce06089802d1b3a19d2` (4/4 main CI green; runtime deployment unverified). `SNFLA_Redirects::handle()` unconditionally called `exit` after `wp_safe_redirect(...,301,...)`. WordPress's redirect function returns false when a filter/interceptor rejects redirect. In that case the handler exited without a status or canonical content rather than furnishing an explicit private failure.

Reproduction: extend the isolated handler fixture with `redirect_rejected`, making `wp_safe_redirect` return false. Baseline generated `status=0, redirect=''` (test expected 503). This is a source behavior proof, **not** proof of a live WordPress error.

## Narrow correction

Only when the previously verified mapping/provenance checks and safe URL check pass, treat `wp_safe_redirect` true as success; otherwise issue the existing `private_legacy_response(503)` with no-store and noindex headers. Do not generate a different public URL or reveal the legacy source. All prior 13 tests retained; now 14 cases including rejected headers. The build-release gate still exercises the regression via the same runner; both PHP matrix jobs call it.

## Acceptance and release boundary

- Locally before correction: 1/14 new test failed; after correction: 14/14 pass; syntax, architecture and deterministic double-build pass.
- Require exact PR-head green CI jobs + retained candidate artifact + independent patch scope check, followed by merged main same-HEAD verification.
- No schema, migration, File21 ownership, WordPress settings or live deployment changed. Source package v2.0.8 is an **unreleased candidate** identified by exact SHA/checksum.
- Live Hostinger exact code/version, DB/options, migration, restore and incident logs remain unknown. HTTP 530/Jetpack disconnection continues to block staging/live acceptance (issue #19).
