#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT=Path(__file__).resolve().parents[1]
def text(path): return (ROOT/path).read_text(encoding='utf-8')
def check(cond,msg,failures):
    if not cond: failures.append(msg)

migration=text('includes/class-snfla-migration.php')
completion=text('includes/class-snfla-plan-completion.php')
file21=text('includes/class-snfla-file21-adapter.php')
reconciliation=text('includes/class-snfla-reconciliation.php')
rollback=text('includes/class-snfla-rollback.php')
retirement=text('includes/class-snfla-retirement.php')
plugin=text('includes/class-snfla-plugin.php')
central=text('includes/class-snfla-central-plan.php')
css=text('assets/css/admin.css')
trace=text('REQUIREMENTS-TRACEABILITY.md')
readme=text('README.md')
workflow=text('.github/workflows/file04-legacy-adapter-ci.yml')
fail=[]

# F04-FR-001 / 002 / 003 — inventory, classification, deterministic canonical mapping.
for needle,label in [
    ('each_legacy_id','bounded/keyset source inventory'),
    ('source_signature','deterministic source signature'),
    ('candidate_conflicts','deterministic eligibility classification'),
    ('SNFLA_Mapping::dry_run_replace','per-run classification ledger'),
    ('mapping','canonical mapping'),
]:
    check(needle in migration or needle in trace,label+' is missing',fail)

# F04-FR-004 — dry-run must be non-destructive and contain counts/conflicts/storage/time/sample diffs.
for needle in ['candidate_count','eligible_count','conflict_count','estimated_dispositions','storage_estimate','time_estimate','sample_diffs',"'destructive'          => false"]:
    check(needle in migration,'Dry-run evidence missing: '+needle,fail)
check('dry_run_estimates' in completion,'Dry-run estimate engine missing',fail)
check('estimated_target_logical_bytes' in completion and 'planning_range_seconds' in completion,'Storage/time planning estimate detail missing',fail)
check('SNFLA_File21_Adapter::preview' in completion,'Canonical File 21 sample preview/diff evidence missing',fail)

# F04-FR-005 — bounded/resumable migration, no OFFSET traversal.
check('MAX_BATCH = 100' in migration,'Migration maximum batch missing',fail)
check('idempotency' in migration.lower() or 'idempot' in migration.lower(),'Migration idempotency evidence missing',fail)
check('checkpoint' in migration.lower() or 'cursor' in migration.lower(),'Resumable checkpoint/cursor evidence missing',fail)
check(re.search(r'\bOFFSET\b',migration,re.I) is None,'OFFSET traversal is forbidden',fail)

# F04-FR-006 — immutable File 00 author identity / governed placeholder.
for needle in ['sabri_file00_platform_uuid_v1','sabri_file00_legacy_author_placeholder_v1','platform_uuid','author_placeholder_contract_required','authorship_preflight']:
    check(needle in completion or needle in migration,'Authorship requirement missing: '+needle,fail)
check('authorship_preflight' in migration,'Eligibility classification must call immutable authorship preflight',fail)

# F04-FR-007 — media/reference migration and evidence.
for needle in ['sabri_file21_legacy_media_preflight_v1','rights_or_license_verified','alt_policy_verified','duplicate_hash_checked','broken_links','accepted_reference_ids','sabri_file21_verify_migrated_legacy_media_v1']:
    check(needle in completion,'Media/reference requirement missing: '+needle,fail)
check("'copy_media'            => true" in file21 and "'copy_references'       => true" in file21,'File 21 canonical migration call must request media/reference copying',fail)
check('verify_file21_result' in file21,'Post-migration media/reference verification missing',fail)
check("LIMIT %d" in completion and "ID>%d" in completion and '$batch_size = 200' in completion,'Attachment traversal must be bounded and keyset-based',fail)
check("numberposts' => -1" not in completion and "posts_per_page' => -1" not in completion,'Unbounded media traversal is forbidden',fail)
check("$source_bytes = $parts['publication_bytes'] + $parts['meta_bytes'] + $parts['comment_bytes'] + $parts['attachment_bytes'];" in completion,'Storage estimate must not count attachment-count units as bytes',fail)

# F04-FR-008 / 009 — canonical interactions + quarantine.
check('INTERACTION_PROVIDER' in file21 and 'migrate_interactions' in file21,'Canonical interaction migration provider missing',fail)
check('quarantine' in migration.lower(),'Quarantine path missing',fail)

# F04-FR-010 / 011 — cutover, cache/search proof, redirects.
for needle in ['snfla_verify_cutover_cache_invalidation','snfla_request_search_reindex','snfla_verify_cutover_search_reindex','final_delta_signature']:
    check(needle in reconciliation,'Cutover evidence requirement missing: '+needle,fail)
check('301' in text('includes/class-snfla-redirects.php'),'Permanent canonical redirect evidence missing',fail)

# F04-FR-012 / 013 — rollback and retirement.
for needle in ['checkpoint','target-change','post-cutover']:
    check(needle.lower() in rollback.lower(),'Rollback protection missing concept: '+needle,fail)
for needle in ['fallback','self-deactivation','route']:
    check(needle.lower() in retirement.lower(),'Retirement evidence missing concept: '+needle,fail)

# NFR authorization/security/privacy/reliability.
check('revalidate_actor' in migration,'Mutation path must revalidate File 00 actor',fail)
check('no-store, no-cache, must-revalidate, private' in completion,'New plan endpoints must use private no-store cache policy',fail)
check('X-Robots-Tag' in completion,'New plan endpoints must be noindex',fail)
check('production_ready' in central and 'blockers' in central,'Truthful production readiness gate missing',fail)

# NFR performance/observability/operability.
for needle in ['p75_ms','p95_ms','error_rate','snfla_operational_alert_v1','system_check','/plan/metrics','/plan/system-check']:
    check(needle in completion,'Performance/observability/operability control missing: '+needle,fail)
check('snfla_storage_estimate_media_bytes_v1' in completion,'Bounded external media storage estimator contract missing',fail)

# NFR accessibility/localization.
for needle in ['focus-visible','prefers-reduced-motion','direction:rtl','unicode-bidi','min-height:44px','forced-colors']:
    check(needle in css,'Accessibility/RTL source safeguard missing: '+needle,fail)

# Central-plan canonical ownership must remain intact.
for owner in ['File 21','File 26','File 20','File 25','File 24']:
    check(owner in central,'Canonical owner missing from modern-plan manifest: '+owner,fail)
check('wp_insert_post(' not in central and '$wpdb->posts' not in central,'Central-plan adapter must not introduce direct canonical writes',fail)
check('LegacyPublicationMigration::migrate_selected' in file21 and 'LegacyPublicationRollback::rollback_selected' in file21,'Canonical File 21 command boundaries missing',fail)

# Human evidence and CI coverage.
check('Central CV/CEN/AJ ID' in trace,'Required central trace chain missing from documentation',fail)
check('71 CV requirements' in readme,'README must state the exact modern-plan applicability',fail)
check('run-file04-own-plan.py' in workflow,'Exact-head CI must execute File04 own-plan QA',fail)

if fail:
    print('File 04 own-plan checks failed:',file=sys.stderr)
    for item in fail: print('-',item,file=sys.stderr)
    sys.exit(1)
print('File 04 own-plan source checks passed: FR-001..013, NFR security/privacy/reliability/performance/accessibility/observability/operability, canonical ownership and CI evidence are traced.')
