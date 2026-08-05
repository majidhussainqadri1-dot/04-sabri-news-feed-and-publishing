# Requirements Traceability

| Requirement | Implementation evidence |
|---|---|
| File 21 is canonical owner | `SNFLA_File21_Adapter`; no File 04 publication creation |
| Read-only legacy source | `SNFLA_Plugin` post/meta/comment/delete protections |
| Inventory and source signature | `SNFLA_Inventory`, `SNFLA_Checksum` |
| Dry-run before mutation | `SNFLA_Migration::dry_run()` and batch membership validation |
| Batch ≤100 | `SNFLA_Migration::MAX_BATCH`; File 21 bounded API |
| Comments preserved | File 21 `LegacyPublicationMigration` canonical method |
| Interactions preserved through canonical owner | `SNFLA_Interaction_Provider` using File 21 provider and repository |
| Deterministic mapping | `snfla_map`, File 21 provenance and mapping verification |
| Quarantine/conflicts | `snfla_conflicts`, fingerprints, admin evidence tab, resolve endpoint |
| Idempotency | `snfla_runs.operation_idempotency` and HMAC key |
| Concurrency control | MySQL advisory locks and lifecycle state version |
| Backup proof | `/backup-proof`, seven-day validity gate |
| Reconciliation | `SNFLA_Reconciliation` counts, mapping, provenance and checksums |
| Safe redirects | `SNFLA_Redirects` lifecycle, same-origin and loop gates |
| Read-only fallback | bounded 1–168 hour window, 404/410 without rendering |
| Rollback/replay | File 21 rollback plus interaction status ledger and batch recovery |
| Retirement | exact phrase, green report, no conflicts, rollback proof, expired fallback |
| Auditability | `snfla_audit` HMAC chain plus File 00 audit event |
| Privacy | redaction deny-list; no source bodies or report narratives in evidence |
| No public composer/feed/ranking | architecture tests and neutralized legacy shortcodes |
| No destructive uninstall | retention-only `uninstall.php` |
| REST contracts | `SNFLA_REST::NAMESPACE` and stable response envelopes |
| WP-CLI | `SNFLA_CLI` inventory/dry-run/migrate/reconcile/rollback/retire |
