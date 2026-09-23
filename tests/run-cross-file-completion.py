#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
def t(path): return (ROOT / path).read_text(encoding='utf-8')
def need(cond, msg, failures):
    if not cond: failures.append(msg)

main = t('sabri-news-feed-legacy-adapter.php')
cross = t('includes/class-snfla-cross-file-contracts.php')
caps = t('includes/class-snfla-capabilities.php')
rest = t('includes/class-snfla-rest.php')
completion = t('includes/class-snfla-plan-completion.php')
central = t('includes/class-snfla-central-plan.php')
admin = t('includes/class-snfla-admin.php')
migration = t('includes/class-snfla-migration.php')
reconciliation = t('includes/class-snfla-reconciliation.php')
retirement = t('includes/class-snfla-retirement.php')
template = t('templates/legacy-migration-report.php')
workflow = t('.github/workflows/file04-legacy-adapter-ci.yml')
build = t('tools/build-release.py')
audit = t('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md')
status = t('STATUS.md')

f = []

# 1-2 loader and boot.
need("'class-snfla-cross-file-contracts.php'" in main, 'Cross-file contract class is not loaded.', f)
need('SNFLA_Cross_File_Contracts::boot();' in main, 'Cross-file contract class is not booted.', f)

# 3 strict File 26 identity.
file26 = central[central.find('public static function file26_resolution'):central.find('public static function release_readiness')]
need('self::strict_positive_id( $legacy_id )' in file26 and "status' => 'invalid'" in file26 and 'absint( $legacy_id )' not in file26, 'File 26 legacy resolution still allows lossy identity normalization.', f)

# 4-6 degraded diagnostic path must not depend on File 21.
diag = caps[caps.find('public static function current_diagnostic_actor'):caps.find('public static function verify_rest_nonce')]
need('file21_ready()' not in diag and "'manage_options'" in diag and 'subject_is_active' in diag, 'Diagnostic actor path is not dependency-outage safe.', f)
need('current_diagnostic_actor()' in rest[rest.find('public static function can_read'):rest.find('public static function can_run')], 'REST read-only diagnostics still require File 21.', f)
need('current_diagnostic_actor()' in completion[completion.find('public static function permission_review'):completion.find('private static function rest_payload')], 'Plan diagnostics still require File 21.', f)

# 7 namespace constant correctness.
need('SNFLA_REST::NAMESPACE' not in completion + admin, 'Undefined SNFLA_REST::NAMESPACE remains in runtime source.', f)

# 8-9 canonical report/admin routes.
need("const REPORT_ROUTE              = '/legacy-migration/report/'" in cross and "add_rewrite_rule( '^legacy-migration/report/?$'" in cross and 'legacy-migration-report.php' in cross, 'Canonical restricted migration report route is incomplete.', f)
need("'sabri-legacy-feed'" in admin and "admin_url( 'admin.php' )" in admin and "'snfla-legacy-adapter'" in admin, 'Canonical admin route or compatibility alias is incomplete.', f)

# 10 File 01 registry contract.
for needle in ['SPF_Registry::register_manifest', 'SPF_Registry::register_contract', 'SPF_Registry::map_route', "FILE01_MODULE_KEY         = 'file-04'", "FILE01_CONTRACT_KEY       = 'file04.legacy-migration'"]:
    need(needle in cross, 'File 01 registry integration missing: ' + needle, f)

# 11 File 20 context/layout truth.
need("'layout_context'  => 'system_recovery'" in cross and "'sabri_shell_contract_registry'" in cross and "'sabri_shell_layout_contexts'" in cross and "'minimal' ===" in cross, 'File 20 canonical context/layout binding is incomplete.', f)

# 12-13 File 19 producer, exact plan events and bounded durable retry.
for event in ['LegacyMigrationBatchCompleted.v1','LegacyRecordQuarantined.v1','LegacyCutoverCompleted.v1','LegacyAdapterRetired.v1']:
    need(event in cross, 'Required File 04 plan event missing from producer contract: ' + event, f)
for transport in ['LegacyMigrationBatchCompleted.V1','LegacyRecordQuarantined.V1','LegacyCutoverCompleted.V1','LegacyAdapterRetired.V1']:
    need(transport in cross, 'File 19-compatible transport event missing: ' + transport, f)
need('FILE19_OUTBOX_MAX          = 100' in cross and 'retry_file19_outbox' in cross and 'sun_ingest_domain_event' in cross, 'Bounded File 19 retry/outbox integration is incomplete.', f)

# 14-17 lifecycle event emission points.
need("'LegacyMigrationBatchCompleted.v1'" in migration, 'Migration-completed event is not emitted.', f)
need("'LegacyRecordQuarantined.v1'" in migration, 'Quarantine event is not emitted.', f)
need("'LegacyCutoverCompleted.v1'" in reconciliation, 'Cutover-completed event is not emitted.', f)
need("'LegacyAdapterRetired.v1'" in retirement, 'Adapter-retired event is not emitted.', f)

# 18 File 24 assurance contract.
need("'spcrc/module_manifests'" in cross and "'spcrc/file04_contract_state'" in cross and "'module_key'             => 'file-04'" in cross and "'canonical_data_owner'" in cross and "'release_gate'" in cross, 'File 24 manifest/assurance integration is incomplete.', f)

# 19 System Check cross-file visibility.
for needle in ["$checks['file01_registry']", "$checks['file20_shell']", "$checks['file19_events']", "$checks['file24_assurance']"]:
    need(needle in completion, 'System Check omits cross-file state: ' + needle, f)
need("'/plan/contracts/sync'" in completion and 'sync_foundation_registry' in completion, 'Authorized File 01 contract sync endpoint is missing.', f)

# 20 release/audit evidence must close R80 and run this gate.
need('| 80 | **Defect.**' in audit and 'PENDING — not yet claimed' not in audit, 'R80 audit evidence has not been closed truthfully.', f)
need('run-cross-file-completion.py' in workflow and 'run-cross-file-completion.py' in build, 'Exact-head workflow/package verification does not execute the cross-file completion gate.', f)
need('second-eighty-round-source-corrected' in status, 'STATUS.md does not identify the corrected second-eighty source state.', f)

# Restricted report contains only privacy-minimized diagnostics and uses theme shell.
need('SNFLA_Cross_File_Contracts::report_data()' in template and 'get_header' in template and 'get_footer' in template, 'Restricted report template is not shell-compatible.', f)

if f:
    print('File 04 cross-file completion gate failed:', file=sys.stderr)
    for item in f:
        print('-', item, file=sys.stderr)
    sys.exit(1)

print('File 04 cross-file completion gate passed: 20 checks cover File 01/19/20/24/26 contracts, degraded diagnostics, routes, lifecycle events and R80 evidence.')
