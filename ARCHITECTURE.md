# File 04 v2.0.8 — Legacy Migration Adapter Architecture (current-source evidence)

**Authoritative source for this description:** File 04 `main` `1e244e85c793314f8eda6c501af1b61c1dba6cfb`, inspected 2026-11-14. This is a *repository architecture*, not an assertion that this plugin is installed in production.

## Constitutional role

- **File 21**: sole canonical publication, Home, News and feed records. It owns authoritative post/comment/media publishing workflows.
- **File 04**: temporary, auditable and reversible legacy migration/compatibility adapter. It may inventory historic source, resolve controlled mappings and orchestrate bounded canonical migrations through File 21's governed commands.
- **File 26**: canonical search/discovery; File 04's legacy resolution may expose a verified non-indexable relationship only.
- **File 00**: membership identity/permission source. **File 19**: notifications/events. **File 20**: shell/routing UI. **File 22**: authoring composer. **File 23**: dashboard. **File 24**: assurance. **File 25**: visual design. **CF-04**: media lifecycle.
- Source contract SHAs live in `COMPANION-CONTRACT-LOCK.json`; drift must be investigated before refreshing pins.

## Present runtime classes

| Component | Responsibility |
|---|---|
| `SNFLA_Database` / `SNFLA_Schema` | source schema, lifecycle, signed migration checkpoints/locks |
| `SNFLA_Inventory` | bounded source inventory and deterministic source signature |
| `SNFLA_Migration` / `SNFLA_File21_Adapter` | dry-run, approved batches, canonical File 21 command boundary |
| `SNFLA_Interaction_Provider` | approved historical interactions and resumable reconciliation |
| `SNFLA_Mapping` | exact historic-to-canonical mapping, conflicts and provenance |
| `SNFLA_Reconciliation` | complete counts, digests, final delta, search/cache cutover evidence |
| `SNFLA_Redirects` | state-dependent 301, private 404/410/503 and limited read-only outage fallback |
| `SNFLA_Rollback` | non-destructive, checkpointed canonical rollback with changed-target protection |
| `SNFLA_Retirement` | canonical route owner handoff, fallback expiry, inert retirement |
| `SNFLA_Central_Plan` / `SNFLA_Cross_File_Contracts` | plan registry, release-readiness evidence, File 00/01/19/20/21/23/24/26 governed interoperability |
| `SNFLA_Future18` / `SNFLA_Plan_Completion` | advisory safety/operations capabilities, subject to external acceptance |

## Exact public legacy redirect behavior (verified in source)

`includes/class-snfla-redirects.php::handle()` acts only on the designated legacy singular post type.

1. Only lifecycle `redirect_cutover` or `read_only_fallback` may resolve historic public routes. Other states return private/noindex **404**.
2. Migration ledger read failure is a private/noindex **503**; an explicitly quarantined record returns private/noindex **410**.
3. Public canonical **301** requires: positive legacy and target IDs; mapping status `migrated`; exact target agreement; both 64-character checksums; File 21 canonical provenance; zero open migration conflicts; a currently public canonical target; a safe same-origin URL with no self-loop; and a successful `wp_safe_redirect(..., 301, ...)`.
4. If WordPress declines the redirect response, File 04 must produce private/noindex **503**, not exit with false success.
5. The `read_only_fallback` state can temporarily expose an unchanged, previously migrated public legacy source with no-store/noindex **200** only during an authorized unexpired fallback window, only when File 21 is unavailable, the canonical resolver has no target, the original source checksum matches and the local conflict ledger is clear. It does not restore File 04 as a publishing owner.
6. Missing or invalid canonical provenance cannot create an indexable duplicate post. Non-fallback failures remain private/noindex **404** or **410** as appropriate.

**Historical note:** Older `ARCHITECTURE.md` described temporary `302` redirects in v1.2.0. This was superseded by source code implementing verified canonical `301`; preserve history in prior commits, do not present 302 as the v2.0.8 runtime rule.

## Safety invariants

- No new File 04 public composer, feed, ranking, comments/reactions truth store, independent search engine or standing dual-write system.
- A failed or unknown authority/provider/schema check must fail closed for privileged mutations.
- Legacy source data must not be destroyed by migration or uninstall.
- No cutover without audited inventory/dry-run, fresh reconciliation/final delta, cache and File 26 search evidence, validated old→new routing and backup/restore confidence.
- Rollback is resumable, bounded and preserves valid newer File 21 edits. Retirement requires verified handoff, fallback expiry and founder authorization.

## Source test boundary and V12.3 gates

The last observed exact-main workflow was run `43357015677` on `1e244e85c793314f8eda6c501af1b61c1dba6cfb`, 4/4 successful jobs, with 14 redirect-regression and 16 File26-resolution scenarios. This does not cover real Hostinger browsers, databases, media, permissions, CDN/DNS, rollback, production or the V12.3 `M11/QA-24` live-parity receipt.

Use `F04-V12.3-EXECUTION-EVIDENCE.md` and the dated `STATUS.md` for evidence classes. **Deployed version, actual DB schema and migration state remain unverified.**
