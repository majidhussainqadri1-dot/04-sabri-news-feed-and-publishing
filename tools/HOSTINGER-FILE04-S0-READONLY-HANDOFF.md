# File 04 — V12.3 S0 Live Reality Freeze / Hostinger Operator Handoff

**2026-11-14 | Source-only operational aid, not an executed live diagnostic**

**Governing rules:** *Sabri-Website-Master-Plan-V12.3.docx*, File04 p.38, release QA-24 pp.79–81, CEN-REL-001; and *File04-V12.3-Complete-Execution-and-Acceptance-Plan-UR.docx*, P01–P02, G01–G02. Live evidence must precede any site-specific patch or production deployment.

## Current blocker and source truth

- GitHub source HEAD when this collector was added: `3e7a32d780b2eeb6f67e385914f441020ee99c4e`, File04 source plugin `v2.0.8`, **declared source schema** `1.3.0`.
- Direct WPVibe site connection still fails with `HTTP 530`. WordPress.com Jetpack reports `site_disconnected`, and site-scoped operations are unavailable. This does **not** prove a site-specific WAF, DNS, WordPress, provider or plugin root cause.
- GitHub CI and downloaded ZIP hashes prove **only source/package evidence**. They are not current installed plugin, live database, migration, restore or rollout proof.
- **Stop condition:** no plugin reinstall/upgrade, DB modification, data migration, deactivation, cache purge or disabling security layers based on the 530 alone.

## 1. Filesystem-only read-only collector (no WordPress bootstrap)

An authorized hosting operator should copy the **source-only** file `tools/collect_file04_deployed_readonly.php` to a private temporary directory outside public web access and, using PHP CLI, execute:

```sh
php collect_file04_deployed_readonly.php /absolute/path/to/wordpress-root
```

The tool validates the WordPress directory layout, reads WordPress core *version file* and the installed File04 plugin's main file and bounded package files, checks the plugin's `PACKAGE-CHECKSUMS.sha256` entries, then prints sanitized JSON. It does **not** load `wp-config.php`, execute WordPress PHP, connect to a database or network, read visitor/post records, create files, or change plugin state. Do not invoke through a web server; the program rejects non-CLI use.

The plugin is searched by expected main filename, under `wp-content/plugins/*/sabri-news-feed-legacy-adapter.php`. `signature_file_found=false` means **not found by that filename**, not uninstalled/inactive. Nonstandard plugin layouts must be investigated by the operator without changing production files.

Interpretation:

- `manifest_parity_verified=true` means every listed installed plugin file matches the **installed local** manifest, with no unexpected files, under bounded scan constraints. This is stronger than a single version string but does **not** prove that the manifest itself is the approved source artifact.
- Compare the returned `package_manifest_sha256` and `entry_file_sha256` against the same named entries extracted from the **approved, independently hashed release ZIP** to establish parity. An installed directory is not the original ZIP byte stream.
- `manifest_parity_verified=false`, missing manifest, unexpected files, symlinks, truncation or multiple candidates means **UNVERIFIED / MISMATCH investigation**, not permission to delete or repair.
- `php_cli_version` is not a claim about the PHP version serving HTTP.
- The collector explicitly leaves `plugin_active`, `actual_db_schema`, `migration_state`, and `runtime_logs` as **UNVERIFIED**; do not fill these from the source constant.

Keep the JSON evidence in a private, access-controlled incident folder. Do not post raw filesystem paths, passwords, WP salts, config files, private runtime logs, unredacted clinical/post data or DB dumps to public GitHub issues.

## 2. Database-only read observations, separately authorized

In Hostinger's *phpMyAdmin* or a safe DB read-only account, confirm the **actual table prefix** before selecting anything. Do not guess `wp_`; examples below use `PREFIX_` as a **placeholder**. Run only SELECT/SHOW commands, do not run migrations, plugin activation or UPDATE/ALTER statements. If a table is absent, record the error and continue the audit; do not create it.

```sql
SHOW TABLES LIKE 'PREFIX_snfla_%';

SELECT option_name, option_value
FROM PREFIX_options
WHERE option_name IN
  ('snfla_schema_version','snfla_plugin_version',
   'snfla_lifecycle_state','snfla_lifecycle_version')
ORDER BY option_name;

SELECT status, COUNT(*) AS records
FROM PREFIX_snfla_map GROUP BY status ORDER BY status;

SELECT status, COUNT(*) AS records
FROM PREFIX_snfla_conflicts GROUP BY status ORDER BY status;

SELECT operation, status, COUNT(*) AS runs
FROM PREFIX_snfla_runs GROUP BY operation, status ORDER BY operation, status;

SELECT post_status, COUNT(*) AS records
FROM PREFIX_posts WHERE post_type='snp_publication' GROUP BY post_status;
```

Only these allowlisted scalar option values and **aggregate counts** belong in the sanitized report. Full `wp_options`, `wp_users`, `wp_posts`, `wp_postmeta`, audit payloads, migration rows or clinical information do **not** belong in the public issue. Collect custom table *column/index descriptions* via `information_schema` or `SHOW COLUMNS` as a private schema snapshot, preserving the exact DB timestamp and environment.

For active plugin status, collect the relevant **File04, File00, File21 and File26** activated versions and installed file hashes from the actual host; do not infer activeness from filenames or a stale Jetpack inventory.

## 3. HTTP 530 / host routing evidence

Record request time (UTC), requested scheme/hostname, response status and any reported 1xxx code or provider correlation headers. Ask Hostinger or the responsible DNS/CDN/connection operator to distinguish origin DNS/routing, TLS, server response, external relay and Jetpack registration. Do not assume which provider created HTTP 530. Do not remove WAF controls or reinstall plugins without independent evidence of cause.

## 4. Freeze and parity decision

Capture these five distinct fields in the private S0 receipt:

| Field | Allowed status |
|---|---|
| Repository HEAD | Current exact SHA from GitHub main |
| Deployed Version | Actual installed plugin version and file/manifest checksums, or UNVERIFIED |
| DB Version | Actual sanitized `snfla_schema_version` + structural proof, or UNKNOWN |
| Migration State | Actual lifecycle option, run/mapping/conflict counts and checkpoints, or UNKNOWN |
| Live Verification Status | VERIFIED / BLOCKED / FAILED; never source CI as live verified |

**Decision tree:**

1. No verified host evidence → `G01=BLOCKED`, existing issue #19 remains open, no production patch or migration.
2. Host evidence exists but GitHub ZIP/file/schema mismatch → deployment-parity audit before debugging application code; exact deployed build takes precedence.
3. Host artifact and DB align → only then isolate actual error, reproduce on matching staging, fix minimal proven root cause and validate.
4. Staging plus isolated restore, 15 applicable AJ journeys, M11/QA-24, two full review/fix/retest rounds and founder authorization → controlled live canary; *then* exact live re-test and SHA parity receipt.
5. A zero-legacy-record source requires a separately signed zero-data/no-migration decision. Do not manufacture sample data on the live site.

## 5. Source-only collector acceptance and limitations

`tests/run-file04-live-reality-collector.py` proves seven synthetic positive/negative cases: intact local manifest, tampering, extra file, missing file, absent main filename, invalid WP root and no private path/secret output or writes. It cannot verify real Hostinger/DB conditions without an operator-run evidence bundle.

**No site mutation was performed by authoring this source-only tool.** All external deployment, migration, live acceptance and retirement gates remain **PENDING** until proven. Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
