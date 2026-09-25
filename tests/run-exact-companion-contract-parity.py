#!/usr/bin/env python3
from pathlib import Path
import json, subprocess, sys

ROOT=Path(__file__).resolve().parents[1]
LOCK=json.loads((ROOT/'COMPANION-CONTRACT-LOCK.json').read_text(encoding='utf-8'))
BASE=ROOT/'companions'
fail=[]

def check(cond,msg):
    if not cond: fail.append(msg)

def read(key,rel):
    p=BASE/key/rel
    check(p.is_file(),f'{key} missing source file: {rel}')
    return p.read_text(encoding='utf-8') if p.is_file() else ''

def git_head(key):
    p=BASE/key
    try:
        return subprocess.check_output(['git','-C',str(p),'rev-parse','HEAD'],text=True).strip()
    except Exception:
        return ''

# Exact frozen companion trees are part of the evidence, not a floating-main test.
for key,row in LOCK['companions'].items():
    expected=row['ref']
    actual=git_head(key)
    check(actual==expected,f'{key} exact-head mismatch: expected {expected}, got {actual or "unavailable"}')

file00_cf=read('file00','source/sabri-membership-core/includes/class-smc-cf01-contract.php')
file00_contracts=read('file00','source/sabri-membership-core/includes/class-smc-contracts.php')
for needle in ['sabri_file00_platform_uuid_v1','sabri_file00_legacy_author_placeholder_v1','ensure_subject_uuid','file04_legacy_author_placeholder']:
    check(needle in file00_cf,'File00/File04 identity contract missing: '+needle)
for needle in ["'mfa_required'", "'two_factor_ready'", "'session_two_factor'"]:
    check(needle in file00_contracts,'File00 retired-MFA assertion vocabulary missing: '+needle)

file01=read('file01','includes/class-spf-registry.php')
for needle in ["$path = '/' . trim( $raw_path, '/' ) . '/';","$page_id = absint( $route['page_id'] )","0 >= $page_id"]:
    check(needle in file01,'File01 exact route validation invariant missing: '+needle)

file19_funcs=read('file19','19-unified-notifications/includes/functions.php')
file19_registry=read('file19','19-unified-notifications/includes/class-sun-producer-registry.php')
check('sun_ingest_domain_event' in file19_funcs,'File19 domain-event ingestion API missing')
check('sun_registered_producers' in file19_registry,'File19 producer registry filter missing')

file20=read('file20','sabri-unified-application-shell/includes/class-central-plan-contract.php')
for needle in ["'04' => array( 'Legacy Publishing Adapter'","'migration-compatibility'","'system_recovery'","'visual-provider'"]:
    check(needle in file20,'File20 canonical ownership/context invariant missing: '+needle)

file21_identity=read('file21','includes/class-canonical-identity-adapter.php')
file21_migration=read('file21','includes/class-legacy-publication-migration.php')
file21_search=read('file21','includes/class-search-provider-registry.php')
for needle in ["array_key_exists( 'mfa_required', $assertions )","sabri_file21_legacy_media_preflight_v1","sabri_file21_verify_migrated_legacy_media_v1","author_identity_context","media_preflight_context","_sabri_hnf_legacy_author_platform_uuid_v1","_sabri_hnf_legacy_media_reference_manifest_v1"]:
    hay=file21_identity+'\n'+file21_migration
    check(needle in hay,'File21/File04 migration invariant missing: '+needle)
check("FILE26_CONNECTOR_SLUG = 'file21-publication'" in file21_search,'File21 canonical File26 connector slug drifted')

file24=read('file24','plugin/sabri-security-center/src/Registry/ModuleRegistry.php')
for needle in ["'tables'", "'files'", "'secret_classes'", "'exporters'", "'erasers'", "'emergency_callbacks'", "'verification_level'"]:
    check(needle in file24,'File24 complete manifest requirement missing: '+needle)

file25=read('file25','includes/class-file-20-integration.php')
check("'structural_shell_owner' => 'file-20'" in file25,'File25 must preserve File20 structural shell ownership')
check("'visual_token_owner' => 'file-25'" in file25,'File25 visual-token ownership invariant missing')

file26_plugin=read('file26','includes/class-file26-plugin.php')
file26_indexer=read('file26','includes/class-file26-indexer.php')
for needle in ['sabri_file26_accept_file04_contract_v1','snfla_request_search_reindex','snfla_verify_cutover_search_reindex','file21-publication']:
    check(needle in file26_plugin,'File26/File04 cutover contract missing: '+needle)
check('enqueue_reindex' in file26_indexer,'File26 bounded reindex API missing')

# Local File04 consumers must exactly match the frozen companion contracts.
cross=(ROOT/'includes/class-snfla-cross-file-contracts.php').read_text(encoding='utf-8')
adapter=(ROOT/'includes/class-snfla-file21-adapter.php').read_text(encoding='utf-8')
for needle in ["'/wp-json/sabri/file04/v1/status/'","'/wp-json/sabri/file04/v1/plan/system-check/'","'layout_context' => 'system_recovery'"]:
    check(needle in cross,'File04/File01/File20 route parity missing: '+needle)
check("'page_id' => 0" not in cross,'File04 must not register invalid zero File01 page IDs')
for needle in ["'tables'", "'files'", "'secret_classes'", "'exporters'", "'erasers'", "'emergency_callbacks'", "'verification_level'"]:
    check(needle in cross,'File04/File24 complete manifest field missing: '+needle)
for needle in ["'references'", "'source_signature'", "'request_digest'"]:
    check(needle in adapter,'File04 must forward full File21 media evidence: '+needle)

if fail:
    print('Exact companion contract parity FAILED:',file=sys.stderr)
    for item in fail: print('-',item,file=sys.stderr)
    sys.exit(1)
print('Exact companion contract parity PASS: File00/01/19/20/21/24/25/26 frozen heads match File04 source contracts.')
