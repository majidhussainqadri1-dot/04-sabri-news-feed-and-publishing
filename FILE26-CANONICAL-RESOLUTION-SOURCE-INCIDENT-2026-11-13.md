# File 04 / File 26 — Canonical legacy-resolution fail-closed evidence (2026-11-13)

## Baseline, trigger, reproducible symptom

Starting exact File04 repository main: `ef4e8dd4495f394a840fcc100d1e994a5b51ad4e`. It already had public legacy 301 provenance hardening in PRs #23 and #24. However `SNFLA_Central_Plan::file26_resolution()` still returned a public canonical File21 URL and target ID even when the local File04 migration mapping was `conflict`, an open conflict remained, evidence hashes were empty, or cutover had not occurred. The resolver only checked that File21's target existed, passed File21 provenance and was public, without verifying File04's own ledger/cutover gate.

Isolated source reproduction: invoke the **actual** `file26_resolution()` method using private CLI fixture and 16 scenarios (no network/database or user information). Exact baseline failed **10/16** negative tests, including `pre_cutover`, `conflict_status`, `interaction_pending`, `target_mismatch`, `mapping_error`, `missing_mapping`, `source_digest_missing`, `target_digest_missing`, `open_conflict`, and `conflict_ledger_failed`. It returned `canonical` in each. This is **source-contract proof** only; it does not prove live deployment or a user-facing production incident.

## Root-cause correction

File26 resolution must not be a second, weaker route to publicly identify a canonical successor. Before returning a canonical URL, require:
1. File04 lifecycle is `redirect_cutover` or `read_only_fallback`;
2. The mapping can be read without error;
3. Exactly the same mapping, positive target, checksum format, File21 provenance and zero-open-conflict conditions required by the already hardened public 301 handler, now shared as `SNFLA_Redirects::canonical_redirect_mapping_valid()`;
4. File21 target is public and has a nonempty URL.

All other cases return a bounded `unmapped` or `unavailable` status with `indexable=false` and without canonical URL or canonical ID. An existing upstream-filter result stays authoritative. Valid read-only canonical targets remain discoverable as a relationship, never as a second search-indexed legacy publication.

## Explicit acceptance gates

- Source baseline before correction: **10/16 File26 scenarios FAIL**.
- Locally after correction: **16/16 File26 PASS**, **14/14 existing 301 redirect tests PASS**, PHP lint, cross-file contract, File04 own-plan and deterministic twice-build PASS.
- Both PHP 8.1/8.3 GitHub matrix jobs and source package build must execute the 16-case test; review exact PR-head CI, retained artifact checksum, and post-merge exact main green before source acceptance.
- No schema, WP options, File21 content, user data, Hostinger deployment or migration state is changed by this PR.

## Live boundary

The exact deployed File04 source, installed plugin version/checksum, DB version, migration ledger, wp_options and live runtime logs are all **unverified**. WPVibe connection returned HTTP 530 and WordPress Jetpack's 2026-07-14 cached plugin list is stale. The live blocked state and evidence request remain in issue #19. Never label this as fixed in production without exact deployed-artifact parity and live re-test.
