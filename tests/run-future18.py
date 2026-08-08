#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT = Path(__file__).resolve().parents[1]

def text(path):
    return (ROOT / path).read_text(encoding='utf-8')

def check(condition, message, failures):
    if not condition:
        failures.append(message)

future = text('includes/class-snfla-future18.php')
main = text('sabri-news-feed-legacy-adapter.php')
trace = text('FUTURE18-TRACEABILITY.md')
workflow = text('.github/workflows/file04-legacy-adapter-ci.yml')
build = text('tools/build-release.py')
fail = []

ids = [f'F04-FUT-{i:03d}' for i in range(1, 19)]
for feature_id in ids:
    check(feature_id in future, f'Missing runtime registry/implementation trace for {feature_id}', fail)
    check(feature_id in trace, f'Missing human traceability row for {feature_id}', fail)
check(len(re.findall(r"'F04-FUT-\d{3}'\s*=>", future)) == 18, 'Runtime registry must contain exactly 18 stable Future18 IDs.', fail)

for method in [
    'digital_twin', 'contract_drift', 'content_fidelity', 'visual_diff', 'lineage_graph', 'risk_score',
    'quarantine_advice', 'shadow_read', 'canary_control', 'invariant_guardian', 'create_receipt',
    'replay_checkpoint', 'blast_radius', 'redirect_observatory', 'unicode_fidelity', 'gameday',
    'retirement_confidence', 'mission_control'
]:
    check(f'function {method}' in future, f'Missing Future18 method: {method}', fail)

for token in ['File 21', 'File 26', 'File 20', 'File 25', 'File 24']:
    check(token in future, f'Canonical owner boundary missing: {token}', fail)
check('wp_insert_post(' not in future, 'Future18 must not directly create canonical posts.', fail)
check('$wpdb->posts' not in future, 'Future18 must not directly write/read File 21 canonical tables.', fail)
check('dual_write' in future and 'false' in future, 'Shadow-read contract must explicitly preserve dual_write=false.', fail)
check('SNFLA_Migration::migrate(' not in future, 'Canary controller must never invoke migration automatically.', fail)

for token in [
    'sabri_file00_contract_descriptor_v1', 'sabri_file21_contract_descriptor_v1', 'sabri_file26_contract_descriptor_v1',
    'snfla_semantic_fidelity_provider_v1', 'snfla_visual_migration_diff_provider_v1', 'snfla_ai_quarantine_advisor_v1',
    'sabri_file21_shadow_read_v1', 'snfla_dependency_blast_radius_v1', 'snfla_redirect_citation_observatory_v1',
    'snfla_disaster_recovery_gameday_v1', 'SNFLA_Integrity::sign_evidence', 'SNFLA_Audit::record'
]:
    check(token in future, f'Missing Future18 integration/evidence contract: {token}', fail)
check('disposable_staging' in future and 'production_chaos_allowed' in future, 'GameDay must be disposable-staging only and forbid production chaos.', fail)
check('founder_approval_required' in future and 'automatically_authorizes_retirement' in future, 'Retirement score must not replace Founder approval.', fail)
check('silent_normalization_performed' in future, 'Unicode guard must explicitly refuse silent normalization.', fail)
check('MAX_RECEIPTS' in future and 'MAX_CHECKPOINTS' in future, 'Evidence option growth must be bounded.', fail)
check('Cache-Control' in future and 'no-store, no-cache, must-revalidate, private' in future, 'Future18 REST responses must be private/no-store.', fail)
check('X-Robots-Tag' in future, 'Future18 REST responses must be noindex.', fail)

# Plugin patch release is 2.0.1; the Future18 feature contract itself remains 2.0.0.
check('Version: 2.0.1' in main and "SNFLA_VERSION', '2.0.1'" in main, 'Runtime must be promoted to hardened patch 2.0.1.', fail)
check("SNFLA_SCHEMA_VERSION', '1.3.0'" in main, 'Hardening must not fabricate an unnecessary storage-schema bump.', fail)
check('class-snfla-future18.php' in main and 'SNFLA_Future18::boot' in main, 'Future18 runtime must load and boot.', fail)
check('class-snfla-post-audit-hardening.php' in main and 'SNFLA_Post_Audit_Hardening::boot' in main, 'Post-audit hardening runtime must load and boot.', fail)
check('run-future18.py' in workflow, 'Exact-head CI must execute Future18 QA.', fail)
check("VERSION='2.0.1'" in build and 'FUTURE18_COUNT=18' in build, 'Deterministic release builder must identify v2.0.1 and 18 enhancements.', fail)
check('future18' in build, 'Release manifest/lock must contain Future18 evidence.', fail)

if fail:
    print('Future18 checks failed:', file=sys.stderr)
    for item in fail:
        print('-', item, file=sys.stderr)
    sys.exit(1)

print('Future18 source checks passed: 18/18 capabilities, ownership boundaries, safety invariants, hardened v2.0.1 wiring, traceability and deterministic-release integration.')
