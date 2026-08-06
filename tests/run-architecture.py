#!/usr/bin/env python3
from pathlib import Path
import re, sys, json

ROOT = Path(__file__).resolve().parents[1]
PHP = {p.relative_to(ROOT).as_posix(): p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts}
ALL_PHP = '\n'.join(PHP.values())
errors=[]

def require(cond, message):
    if not cond: errors.append(message)

def text(path): return (ROOT/path).read_text(encoding='utf-8')

main=text('sabri-news-feed-legacy-adapter.php')
plugin=PHP['includes/class-snfla-plugin.php']
file21=PHP['includes/class-snfla-file21-adapter.php']
redirects=PHP['includes/class-snfla-redirects.php']
retirement=PHP['includes/class-snfla-retirement.php']
rest=PHP['includes/class-snfla-rest.php']
mapping=PHP['includes/class-snfla-mapping.php']

require('Version: 1.2.0' in main and "SNFLA_VERSION', '1.2.0'" in main and "SNFLA_SCHEMA_VERSION', '1.2.0'" in main, 'Runtime/header/schema versions must be 1.2.0.')
require("SNFLA_FILE21_MIN_PACKAGE', '1.0.3.2'" in main and "SNFLA_FILE21_MIN_RUNTIME', '1.0.3'" in main, 'File 21 package/runtime compatibility gates must match current canonical contract.')
for pat in [r'\bOFFSET\b', r'\bTRUNCATE\b', r'maybe_unserialize', r'(?<![A-Za-z_])unserialize\s*\(', r'wp_cache_flush\s*\(']:
    require(not re.search(pat, ALL_PHP, re.I), f'Forbidden pattern found: {pat}')
require('register_post_type' in plugin and 'SNFLA_Inventory::LEGACY_POST_TYPE' in plugin, 'Only the legacy source schema may be registered.')
require('create_posts' in plugin and "'do_not_allow'" in plugin, 'Legacy post creation must be denied.')
require('remove_all_actions( \'wp_ajax_snp_interact\'' in plugin, 'Obsolete interaction endpoints must be neutralized.')
require('wp_insert_post' not in file21 and 'wp_update_post' not in file21 and '$wpdb->posts' not in file21, 'File 04 must not mutate File 21 posts/tables directly.')
require('LegacyPublicationRollback::rollback_selected' in file21, 'Orphan containment/rollback must use File 21 canonical command.')
require('migration_target_valid' in file21 and '_sabri_hnf_legacy_source_id' in file21 and '_sabri_hnf_legacy_source_type' in file21, 'Canonical target provenance must be fully validated.')
require('wp_safe_redirect( $url, 301' in redirects, 'Canonical legacy redirects must be permanent 301 redirects.')
require("'quarantined' ===" in redirects and 'private_legacy_response( 410 )' in redirects, 'Quarantined source-only records must resolve to governed 410 responses.')
require('404 === absint( $status )' in redirects, '410/503 responses must not be falsely marked as WordPress 404 state.')
require('revalidate_actor' in PHP['includes/class-snfla-capabilities.php'], 'A lock-time actor revalidation helper must exist.')
for path in ['includes/class-snfla-inventory.php','includes/class-snfla-migration.php','includes/class-snfla-reconciliation.php','includes/class-snfla-redirects.php','includes/class-snfla-retirement.php','includes/class-snfla-rollback.php']:
    require('revalidate_actor' in PHP[path] or path.endswith('class-snfla-rollback.php') and 'current_actor' in PHP[path], f'Protected mutator lacks actor revalidation: {path}')
require('get_checked' in mapping and 'snfla_mapping_query_failed' in mapping, 'Mapping ledger reads must expose fail-closed query errors.')
require('no-store, no-cache, must-revalidate, private' in rest and 'X-Robots-Tag' in rest, 'REST evidence responses must be private/no-store/noindex.')
require('Idempotency-Key' in rest and 'snfla_idempotency_key_mismatch' in rest and '16–190' in rest, 'REST idempotency key contract must be strict and collision-resistant.')
require("'expected_state'" in rest and "'expected_version'" in rest and "'legacy_ids'" in rest, 'REST mutation schemas must require lifecycle and bounded object inputs.')
require('deactivation_api_available' in retirement and 'snfla_retirement_deactivation_failed' in retirement, 'Retirement must fail closed if self-deactivation cannot be verified.')
require('each_route_disposition_batch' in retirement and 'manifest_checksum' in retirement, 'Retirement must hand off every redirect/gone disposition with checksum evidence.')
require('$network_wide ||' in PHP['includes/class-snfla-database.php'] and 'snfla_network_activation_unsupported' in PHP['includes/class-snfla-database.php'], 'Network-wide activation must be explicitly blocked.')
require('capture_table_baseline' in PHP['includes/class-snfla-database.php'] and 'remove_new_activation_tables' in PHP['includes/class-snfla-database.php'], 'Activation compensation must remove only adapter tables created by the failed activation.')
require('safe_count_query' in PHP['includes/class-snfla-migration.php'], 'Legacy count queries must fail closed rather than convert database errors to zero.')
require('snfla_reconciliation_mapping_count_failed' in PHP['includes/class-snfla-reconciliation.php'], 'Reconciliation disposition count reads must fail closed.')
require('rollback_checkpoint_persist_failed' in PHP['includes/class-snfla-rollback.php'] and 'interaction rollback was not started' in PHP['includes/class-snfla-rollback.php'], 'Rollback must checkpoint local evidence before interaction reversal.')
require('PACKAGE-MANIFEST.json' not in PHP, 'Runtime PHP must not depend on source/package evidence files.')

if errors:
    print('Architecture checks failed:')
    for e in errors: print('-',e)
    sys.exit(1)
print(f'Architecture and ownership checks passed ({30} assertions across {len(PHP)} PHP files).')
