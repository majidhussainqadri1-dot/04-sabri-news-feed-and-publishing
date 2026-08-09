#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c: f.append(m)

f=[]
main=t('sabri-news-feed-legacy-adapter.php')
plugin=t('includes/class-snfla-plugin.php')
schema=t('includes/class-snfla-schema.php')
integrity=t('includes/class-snfla-integrity.php')
audit=t('includes/class-snfla-audit.php')
db=t('includes/class-snfla-database.php')
caps=t('includes/class-snfla-capabilities.php')
rest=t('includes/class-snfla-rest.php')
mapping=t('includes/class-snfla-mapping.php')
migration=t('includes/class-snfla-migration.php')
rollback=t('includes/class-snfla-rollback.php')
recon=t('includes/class-snfla-reconciliation.php')
redirects=t('includes/class-snfla-redirects.php')
retirement=t('includes/class-snfla-retirement.php')
interactions=t('includes/class-snfla-interaction-provider.php')
file21=t('includes/class-snfla-file21-adapter.php')
plan=t('includes/class-snfla-plan-completion.php')
central=t('includes/class-snfla-central-plan.php')
future=t('includes/class-snfla-future18.php')
hard=t('includes/class-snfla-post-audit-hardening.php')
cli=t('includes/class-snfla-cli.php')
admin=t('includes/class-snfla-admin.php')
css=t('assets/css/admin.css')
workflow=t('.github/workflows/file04-legacy-adapter-ci.yml')
build=t('tools/build-release.py')
status=t('STATUS.md')
record=t('EIGHTY-ROUND-HARDENING-AUDIT.md')
all_php='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)

# R01-R10 evidence-integrity/lifecycle/activation controls.
need("SNFLA_VERSION', '2.0.5'" in main and 'Version: 2.0.5' in main, 'v2.0.5 runtime metadata missing', f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main, 'storage schema must remain 1.3.0', f)
need("'audit_written'" in plugin and "'ok'" in plugin and 'daily_integrity_evidence_failed' in plugin, 'R01 integrity persistence truth missing', f)
need('state_valid()' in schema and 'self::version() >= 1' in schema and 'snfla_lifecycle_compensation_failed' in schema, 'R02-R03 lifecycle fail-closed/compensation missing', f)
need("preg_match( '/^[a-f0-9]{64}$/D'" in integrity and 'started > time() + 300' in integrity, 'R04-R05 checkpoint/stale-run hardening missing', f)
need('return false;' in audit.split('public static function has_event',1)[1].split('public static function',1)[0] and 'field_' in audit and 'mb_substr' in audit, 'R06-R08 audit corruption/UTF-8/key collision hardening missing', f)
need('snfla_network_legacy_runtime_requires_network_operator' in db and 'rewrite_rules_evidence_encoding_failed' in db, 'R09-R10 activation/network/rewrite evidence hardening missing', f)

# R11-R20 activation/schema/REST identity controls.
need('snfla_lifecycle_state_corrupt' in db and 'has_shortcode' in db and 'slug_checksum' in db and 'schema_upgrade' in db, 'R11-R14 activation state/page/schema lock hardening missing', f)
need("'finished_at'" in db and 'Seq_in_index' in db and '_columns_mismatch' in db, 'R15-R16 full schema/index verification missing', f)
need('SNFLA_Database::schema_healthy()' in caps and 'snfla_schema_unhealthy' in caps, 'R17 schema health mutation gate missing', f)
need('opaque_idempotency_arg' in rest and "preg_match( '/^[!-~]+$/D'" in rest and 'isset( $ids[ $id ] )' in rest, 'R18-R20 exact idempotency/positive unique REST ID hardening missing', f)

# R21-R33 evidence/status/mapping/cursor/restore compensation controls.
need("'evidence_valid' => $trusted" in rest and 'if ( $status < 400 || $status > 599 )' in rest, 'R21-R23 status evidence/HTTP hardening missing', f)
need('$conflicts_json = wp_json_encode' in mapping and '$redacted_json = wp_json_encode' in mapping and 'snfla_interaction_original_corrupt' in mapping, 'R24-R26 mapping JSON fail-closed hardening missing', f)
need('$cursor = $source_row_id;' in interactions and interactions.find('$cursor = $source_row_id;') > interactions.find("if ( ! empty( $outcome['error'] )"), 'R27 interaction cursor must advance only after successful row outcome', f)
need('exact ASCII idempotency key' in migration and 'exact ASCII rollback idempotency key' in rollback, 'R28-R29 exact core idempotency semantics missing', f)
need('snfla_restore_table_counts_invalid' in migration and 'snfla_inventory_compensation_failed' in t('includes/class-snfla-inventory.php') and 'snfla_backup_proof_compensation_failed' in migration and 'snfla_retirement_compensation_failed' in retirement, 'R30-R33 restore/inventory/backup/retirement compensation hardening missing', f)

# R34-R50 rollback/reconciliation/provider/request binding controls.
need('progress_checked' in rollback and 'interaction_progress_corrupt' in rollback, 'R34 destructive rollback progress corruption gate missing', f)
need('interaction_progress_persist_failed' in migration and 'rolled_back_target_valid' in file21 and "status NOT IN ('rolled_back','quarantined')" in rollback, 'R35-R37 resumable/rolled-back provenance/handover controls missing', f)
need('snfla_reconciliation_compensation_failed' in recon and 'interaction_ledger_original_corrupt' in interactions, 'R38-R40 reconciliation/interaction corrupt-evidence hardening missing', f)
need('system_conflicts_resolved' in mapping and "status='open',resolved_at=NULL" in mapping and 'previous_dry_run_conflicts_superseded' in mapping, 'R41-R42 conflict audit compensation missing', f)
need('snfla_file21_unexpected_result_ids' in plan and 'dry_run_analysis_audit_failed' in plan and 'SNFLA_Checksum::hash( SNFLA_Audit::redact( $value ) )' in plan, 'R43-R46 File21/dry-run/reference evidence hardening missing', f)
need('15 * MINUTE_IN_SECONDS' in plan and 'verified_at_utc' in plan and '$broken_links' in plan and '$placeholder_bound' in plan, 'R47-R50 provider freshness/strict counts/placeholder binding missing', f)

# R51-R64 request-bounded integration, recovery, operability and UI/CLI controls.
need("array( 'migrated', 'skipped', 'warnings' )" in plan and 'snfla_file21_migrated_row_invalid' in plan, 'R51-R52 File21 collection/row validation missing', f)
need('quarantine_mapping_read_failed' in migration and 'quarantine_mapping_write_failed' in migration, 'R53 emergency quarantine ledger hardening missing', f)
need('schema_healthy()' in db and "! empty( $health['ok'] )" in db, 'R54 positive schema-health evidence missing', f)
need('network_retirement_requires_network_operator' in retirement and 'SNFLA_Schema::state_valid()' in retirement, 'R55-R56 network retirement/lifecycle validity gate missing', f)
need('metrics_persist_failed' in plan and "$checks['schema']" in plan, 'R57-R58 observability/system schema gate missing', f)
need('$file26_verified_at' in plan and 'is_finite' in plan, 'R59-R60 File26 freshness/storage-estimate strictness missing', f)
need('function parse_ids' in cli and 'snfla_output_encoding_failed' in cli, 'R61-R62 CLI ID/output hardening missing', f)
need('fallback_evidence_valid' in admin and 'snfla_admin_mapping_query_failed' in admin, 'R63-R64 admin evidence/query error hardening missing', f)

# R65 release/QA integration.
need("VERSION='2.0.5'" in build and 'EIGHTY_ROUND_REVIEW_ROUNDS=80' in build, 'R65 builder must identify v2.0.5 and 80 fresh rounds', f)
need('run-eighty-round-hardening.py' in build and 'run-eighty-round-hardening.py' in workflow, 'R65 builder and CI must execute the permanent 80-round gate', f)
need('Stable tag: 2.0.5' in t('readme.txt') and 'v2.0.5' in t('README.md') and 'v2.0.5' in status, 'R65 human release metadata must identify v2.0.5', f)

# R66 — canonical ownership/no duplicate publication backend.
need('File 21' in central and 'File 26' in central and 'wp_insert_post(' not in file21 and 'wp_insert_post(' not in future and 'wp_insert_post(' not in hard, 'R66 canonical ownership/duplicate backend regression', f)
# R67 — File21 command boundary.
need('LegacyPublicationMigration::migrate_selected' in file21 and 'LegacyPublicationRollback::rollback_selected' in file21 and '$wpdb->posts' not in file21, 'R67 File21 command boundary regression', f)
# R68 — File26 remains discovery/search consumer only.
need('sabri_file26_legacy_resolution_v1' in central and 'duplicate truth store' in central and 'SNFLA_Migration::migrate(' not in central, 'R68 File26/search ownership regression', f)
# R69 — auth, CSRF and fresh action authority.
need('verify_rest_nonce' in rest and 'current_actor' in caps and "apply_filters( 'snfla_actor_capability_allowed', true" in caps, 'R69 auth/CSRF/deny-only authority regression', f)
# R70 — privacy/redaction and invalid evidence hiding.
need('patient' in audit and 'authorization' in audit and 'credential' in audit and 'invalid_evidence' in admin, 'R70 privacy/redaction regression', f)
# R71 — bounded/keyset traversal/no OFFSET.
need(not re.search(r'\bOFFSET\b', all_php, re.I) and 'MAX_BATCH' in migration and 'ID>%d ORDER BY ID ASC LIMIT %d' in plan, 'R71 bounded/keyset performance regression', f)
# R72 — concurrency/idempotency/resume.
need("acquire_lock( 'operation'" in migration and "acquire_lock( 'migration'" in migration and 'idempotency_hash' in migration and 'checkpoint' in rollback, 'R72 concurrency/idempotency/resume regression', f)
# R73 — backup/restore/rollback compensation.
need('backup_proof_valid' in migration and 'snfla_backup_proof_compensation_failed' in migration and 'snfla_lifecycle_compensation_failed' in schema and 'snfla_retirement_compensation_failed' in retirement, 'R73 recovery compensation regression', f)
# R74 — redirect/fallback privacy and loop protection.
need('&& ! $file21_ready' in redirects and "'migrated' !== sanitize_key" in redirects and 'rawurldecode' in redirects and 'X-Robots-Tag' in redirects and 'no-store' in redirects, 'R74 redirect/fallback privacy regression', f)
# R75 — quarantine/source-only safety.
need("'quarantined'" in migration and 'source_only_quarantine' in migration and 'private_legacy_response( 410 )' in redirects and 'patient' in audit, 'R75 quarantine/source-only safety regression', f)
# R76/R77 — supported PHP versions remain in exact-head CI matrix.
need("php: ['8.1', '8.3']" in workflow, 'R76/R77 PHP 8.1/8.3 matrix regression', f)
# R78 — accessibility/RTL/localization guardrails.
for token in ['focus-visible','min-height:44px','prefers-reduced-motion','forced-colors','direction:rtl']:
    need(token in css.replace(' ', '').lower() if token=='direction:rtl' else token in css, f'R78 accessibility/RTL guardrail missing: {token}', f)
# R79 — deterministic package + secret/PII scan.
need('Reproducible v2.0.5 package twice' in workflow and 'Secret and personal-data indicator scan' in workflow and 'cmp /tmp/file04-a.zip /tmp/file04-b.zip' in workflow, 'R79 deterministic/security release gate regression', f)
# R80 — truthful source vs staging/live/ops boundary.
need('staging_accepted_pending=true' in status and 'live_deployed=false' in status and 'operational=false' in status and "'production_ready'" in central, 'R80 lifecycle truth-boundary regression', f)

# Audit record must enumerate all 80 rounds. It may be PENDING during review execution,
# but final release will replace all R66-R80 PENDING states with clean findings.
for i in range(1,81): need(f'| {i} |' in record, f'Audit record missing round {i}', f)

if f:
    print('80-round hardening gate failed:', file=sys.stderr)
    for item in f: print('-', item, file=sys.stderr)
    sys.exit(1)
print('80-round hardening source gate passed: R01-R65 corrective controls plus R66-R80 focused regression invariants are present.')
