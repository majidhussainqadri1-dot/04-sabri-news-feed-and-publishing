# Architecture — File 04 Legacy Foundation Adapter

## 1. Ownership model

| Concern | Canonical owner | File 04 role |
|---|---|---|
| Identity, membership, step-up assurance, capabilities | File 00 | Read-only assurance consumer |
| Public profile and doctor verification | Files 03/09 | No ownership |
| Application shell and navigation | File 20 | No ownership |
| Home, News, publication objects, comments, interactions, public routes | File 21 | Source adapter and migration evidence |
| Universal composition | File 22 | No public composer |
| Notifications | File 19 | Emits no user-facing delivery system |
| Security assurance | File 24 | Exposes evidence only |

## 2. Lifecycle state machine

`legacy_active → inventory_locked → dry_run_ready → batch_migration → reconciliation → redirect_cutover → read_only_fallback → retired`

Repeated inventory, dry-run, batch and reconciliation operations are versioned self-transitions. A non-destructive rollback may recover an accepted non-retired state to `batch_migration`; no transition can leave `retired`.

Every transition requires the caller's expected state and state version. Stale requests fail with `snfla_state_conflict`.

## 3. Source custody

The adapter registers the historical post type and taxonomy only so existing data remains addressable. Their user interface, REST exposure, archive, sitemap, search inclusion, comments, pings, metadata writes, updates, trash and deletion are disabled. Old File 04 shortcodes and AJAX actions are neutralized.

The adapter does not copy source bodies into its own tables. Its evidence tables store only identifiers, checksums, bounded redacted context and canonical target references.

Activation performs a fail-closed ownership handover: any active historical `sabri-news-publishing.php` runtime is deactivated and verified inactive; only pages proven to be legacy File 04-managed are changed from public/future to private. The handover stores hashes, identifiers, prior status and content checksums, never page bodies or credentials.

## 4. File 21 integration

All canonical publication creation uses:

- `Sabri\HomeNewsFeed\LegacyPublicationMigration::preview()`
- `Sabri\HomeNewsFeed\LegacyPublicationMigration::migrate_selected()`
- `Sabri\HomeNewsFeed\LegacyPublicationRollback::rollback_selected()`
- `Sabri\HomeNewsFeed\LegacyPublicationMigration::target_for()`

Legacy interactions use File 21's explicit provider registry and `InteractionRepository` write boundary. File 04 never issues direct INSERT/UPDATE/DELETE statements against File 21 tables. Inserted, reactivated or merged canonical interaction rows are recorded with field-level original values and migration provenance, allowing exact restoration without overwriting canonical user intent.

## 5. Persistence

- `snfla_runs`: idempotent operation ledger and checkpoints.
- `snfla_map`: one legacy-to-canonical mapping, checksums and reversible interaction ledger.
- `snfla_conflicts`: deduplicated redacted quarantine records.
- `snfla_audit`: append-only HMAC-linked audit events.

No table duplicates File 21 content, feed, comment, reaction, save, report or ranking ownership.

## 6. Failure model

The adapter fails closed when File 21 is absent/incompatible, File 00 assurance is stale, a capability is missing, a nonce is invalid, source inventory changes, a dry-run is stale, backup proof is absent, an idempotency key is invalid, a lock is unavailable, conflicts remain, a target changed after migration, a lifecycle request is stale, or a stale run cannot be durably finalized as interrupted.

## 7. Retirement

Retirement disables mutation endpoints but preserves source data and all evidence. Destructive uninstall is intentionally unavailable. Removing retained data is a separate owner-approved legal, privacy and backup process outside this plugin.

## 8. Production package

The deterministic install ZIP contains only plugin runtime, assets, runbooks and signed release evidence. Development-only GitHub workflows, tests and build tooling remain in the repository and are excluded from the WordPress package.
