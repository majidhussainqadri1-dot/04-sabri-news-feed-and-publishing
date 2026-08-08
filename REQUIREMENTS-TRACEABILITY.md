# File 04 v1.3.0 — Governing Requirements Traceability

## Governing sources

1. **Modern consolidated governing plan** — `SSH-CENTRAL-CONSOLIDATED-2026-08-07` (later central-plan decisions prevail over older non-conflicting material only where they conflict).
2. **File 04 — News Feed and Publishing — Legacy Foundation Adapter Complete Master Plan v1.0** — `SSH-F04-PLAN-2026-v1.0`.
3. Historical v1.2.0 forty-round source audit is retained as ancestry evidence, not as proof that the later central-plan delta was already implemented.

## Canonical constitutional boundary

- **File 21 is the sole canonical publication/Home/News/feed owner.**
- **File 26 is the canonical search/discovery owner.** File 04 exposes only a versioned legacy-ID → canonical-File-21 resolution contract; it contributes no ranking truth or private legacy content to search.
- **File 17 / canonical community owners** retain community/forum/AMA truth. File 04 does not create a community backend.
- **File 20 owns shell/routing layout; File 25 owns visual tokens/components; File 24 owns assurance coordination while File 04 preserves native enforcement.**
- File 04 owns only historical inventory, deterministic mapping, dry-run/quarantine, bounded migration through File 21 commands, reconciliation, cutover redirects, non-destructive rollback, time-bounded read-only fallback and retirement.

## Exact central-plan applicability

The current File 04 plan assigns **71 CV requirements** to File 04's release trace:

- `CV-037`–`CV-049` — 13 requirements: Home/feed/discovery/card/correction/ranking concerns. **Enforcement:** integration regression only; no duplicate File 21/File 26 backend.
- `CV-074`–`CV-084` — 11 requirements: communities/channels/forum/AMA/wiki/moderation/events/health concerns. **Enforcement:** integration regression only; no duplicate community backend.
- `CV-239`–`CV-285` — 47 requirements: language/bidi/accessibility/low-bandwidth/safety/privacy/security/SDLC/DR/SLO/observability/degradation/release/support/capacity/vendor/runbook concerns. **Enforcement:** adapter-surface, migration-safety, native-assurance or release-evidence according to canonical ownership.

The machine-readable per-ID registry is `SNFLA_Central_Plan::requirements()` in `includes/class-snfla-central-plan.php`. It expands every individual ID — not merely the ranges — and records `priority`, `owner`, `enforcement`, `code_location`, `contract` and deterministic `test_id=CP-CV-xxx`. `tests/run-central-plan.php` fails if any of the 71 rows is absent or loses one of those fields.

## File-specific central requirements

| ID | Required behavior | Implementation | Verification |
|---|---|---|---|
| `F04-CEN-01` | New File 04 post/comment/feed truth writes are forbidden; historical IDs deterministically resolve/migrate to File 21 canonical IDs/URLs. | `class-snfla-plugin.php`, `class-snfla-mapping.php`, `class-snfla-migration.php`, `class-snfla-file21-adapter.php`, `class-snfla-central-plan.php` | architecture QA + central-plan QA + migration/reconciliation tests |
| `F04-CEN-02` | Any exceptional migration overlap must be explicit, bounded, evidence-governed and Founder-approved; after cutover File 04 becomes read-only → redirect-only → retired. | schema lifecycle, reconciliation, redirects, rollback, retirement and central-plan manifest | architecture QA + lifecycle/rollback/retirement tests |

File 04 does **not** introduce a standing dual-write service. Canonical mutation remains a File 21 command boundary; the legacy source itself is write-disabled.

## Acceptance journeys

The File 04 plan maps these **15** relevant journeys: `AJ-07`, `AJ-10`, `AJ-24`, `AJ-25`, `AJ-28`, `AJ-31`–`AJ-40`.

- `AJ-07` is native release-critical: legacy record → File 21 canonical route, with no duplicate write/ID.
- `AJ-10`, `AJ-24`, `AJ-25`, `AJ-28`, `AJ-34`, `AJ-35` are cross-file integration regressions; File 04 must not duplicate the canonical notification/donation/search/identity/privacy owners.
- `AJ-31`–`AJ-33` are adapter-surface/accessibility/network regressions.
- `AJ-36`–`AJ-37` are degraded-provider and restore/reconciliation gates.
- `AJ-38`–`AJ-40` are release gates: blockers stop release, screenshot/role/state corpus is required in staging, and two consecutive corrective review/fix/retest rounds precede rollout.

## Modern implementation additions in v1.3.0

1. `SNFLA_Central_Plan` — exact 71-CV registry, 2 F04-CEN IDs, 15 AJ IDs, canonical-owner map and truthful release gate.
2. File 26 compatibility — `sabri_file26_legacy_resolution_v1` resolves a migrated legacy ID only to a verified, public File 21 canonical object. Unmapped/private targets are explicitly non-indexable and no File 04 ranking backend is created.
3. Accessibility/localization source safeguards — logical RTL properties, strong `:focus-visible`, 44px controls, LTR isolation for code/JSON, reduced-motion handling, forced-colors support and mobile reflow safeguards.
4. Release truth separation — source trace completion is distinct from Hostinger staging, backup/restore, File 26 integration acceptance, accessibility acceptance, degraded-provider drills and Founder production approval.
5. Two fresh post-central-plan source review rounds — requirements/ownership first; adversarial/source regression second.
6. Deterministic v1.3.0 package metadata includes central-plan IDs, canonical owner boundaries, dependency contracts and review counts.

## Required trace chain

For every applicable item the governing trace is:

**Central CV/CEN/AJ ID → File 04 requirement → design/data/API/event boundary → test ID → defect/fix/commit → package/checksum → staging evidence → Founder approval → rollout/monitoring.**

The repository can prove the chain only through source/package/CI until the external steps actually occur. Hostinger staging, real-data migration, browser/RTL/WCAG acceptance, restore rehearsal, Founder approval, controlled live deployment and operational monitoring MUST NOT be fabricated in this document.
