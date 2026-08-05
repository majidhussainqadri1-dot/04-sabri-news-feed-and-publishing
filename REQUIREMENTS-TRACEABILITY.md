# Requirements Traceability

| Requirement | Implementation evidence |
|---|---|
| File 21 is canonical owner | `SNFLA_File21_Adapter`; no File 04 publication creation |
| Read-only legacy source | `SNFLA_Plugin` post/meta/comment/delete protections and final-priority schema registration |
| Inventory and source signature | `SNFLA_Inventory`, `SNFLA_Checksum` |
| Dry-run before mutation | `SNFLA_Migration::dry_run()` and batch membership validation |
| Batch ≤100 | `SNFLA_Migration::MAX_BATCH`; File 21 bounded API |
| Comments preserved | File 21 `LegacyPublicationMigration` canonical method |
| Interactions preserved through canonical owner | `SNFLA_Interaction_Provider` using File 21 provider/repository with field-level original-state ledger, exact merge checks and reversible rollback |
| Deterministic mapping | `snfla_map`, File 21 provenance and mapping verification |
| Quarantine/conflicts | `snfla_conflicts`, fingerprints, admin evidence tab, resolve endpoint |
| Idempotency | `snfla_runs.operation_idempotency` and HMAC key |
| Concurrency control | MySQL advisory locks and lifecycle state version |
| Backup proof | `/backup-proof`, seven-day validity, locked-source binding and audit-event binding |
| Reconciliation | `SNFLA_Reconciliation` semantic projections, exact interaction values, fresh report age, checksum and audit-event binding |
| Safe redirects | `SNFLA_Redirects` lifecycle, same-origin and loop gates |
| Read-only fallback | bounded 1–168 hour window, 404/410 without rendering |
| Rollback/replay | File 21 rollback plus field-level interaction restoration, changed-target blocking, signed source-bound proof and lifecycle-failure quarantine |
| Retirement | exact phrase, fresh audited green report, no conflicts, audited source-bound rollback proof, expired audited fallback and integrity-cron shutdown |
| Auditability | `snfla_audit` HMAC chain plus File 00 audit event |
| Privacy | redaction deny-list; no source bodies or report narratives in evidence |
| No public composer/feed/ranking | architecture tests and neutralized legacy shortcodes |
| No destructive uninstall | retention-only `uninstall.php` |
| REST contracts | `SNFLA_REST::NAMESPACE` and stable response envelopes |
| WP-CLI | `SNFLA_CLI` inventory/dry-run/backup-proof/migrate/reconcile/cutover/fallback/conflict-resolution/rollback/retire |
| Safe replacement handover | activation deactivates obsolete `sabri-news-publishing.php`, verifies deactivation, privately quarantines only proven legacy-owned pages and writes a hashed audit receipt |
| Production package hygiene | deterministic ZIP allowlist excludes `.github`, `tests` and `tools` while retaining runtime and signed release evidence |
