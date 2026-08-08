#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
def t(path): return (ROOT/path).read_text(encoding='utf-8')
def need(condition,message,failures):
    if not condition: failures.append(message)

fail=[]
main=t('sabri-news-feed-legacy-adapter.php')
caps=t('includes/class-snfla-capabilities.php')
schema=t('includes/class-snfla-schema.php')
ret=t('includes/class-snfla-retirement.php')
future=t('includes/class-snfla-future18.php')
hard=t('includes/class-snfla-post-audit-hardening.php')
plan=t('includes/class-snfla-plan-completion.php')
workflow=t('.github/workflows/file04-legacy-adapter-ci.yml')
build=t('tools/build-release.py')
audit=t('SECOND-TEN-ROUND-HARDENING-AUDIT.md')
all_php='\n'.join(p.read_text(encoding='utf-8') for p in ROOT.rglob('*.php') if '.git' not in p.parts and 'tests' not in p.parts)

need('Version: 2.0.2' in main and "SNFLA_VERSION', '2.0.2'" in main,'Current runtime must be v2.0.2.',fail)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'Storage schema must remain 1.3.0.',fail)
need("apply_filters( 'snfla_actor_capability_allowed', true" in caps and "apply_filters( 'snfla_read_capability_allowed', true" in caps,'Capability extension filters must be deny-only.',fail)
need("const INVALID_STATE = 'invalid'" in schema and 'snfla_lifecycle_state_invalid' in schema and 'in_array( $state, SNFLA_Schema::states(), true )' in ret,'Lifecycle invalid-state fail-closed control is missing.',fail)
need('provider_request_bound' in hard and 'request_digest' in hard and 'source_signature' in hard and 'request_digest' in future,'Future provider anti-replay binding is missing.',fail)
need("'/future/contract-baseline'" in future and 'record_contract_baseline' in future and 'baseline_missing_or_invalid' in future,'Explicit signed contract baseline workflow is missing.',fail)
need('snfla_receipt_persist_failed' in future and 'snfla_receipt_audit_failed' in future,'Receipt durability must fail closed.',fail)
need('manifest_digest' in plan and "'production_ready'] = false" in plan and 'file04_system_' in plan,'System/File26/production-readiness truth controls are missing.',fail)
need('observability_samples_missing' in future and 'sample_count' in future,'Canary must reject zero observability samples.',fail)
need('snfla_media_source_signature_invalid' in plan and 'provider_bound' in plan and 'verify_bound' in plan,'File21 media evidence must be source/request-bound before and after migration.',fail)
need('snfla_gameday_persist_failed' in future and 'snfla_gameday_audit_failed' in future,'GameDay durability must fail closed.',fail)
need('persistence_failed' in hard and 'audit_persistence_failed' in hard,'Redirect/citation proof durability must fail closed.',fail)
need(all(token in future for token in ['snfla_twin_persist_failed','snfla_twin_audit_failed','snfla_checkpoint_persist_failed','snfla_shadow_persist_failed','snfla_shadow_audit_failed','snfla_canary_persist_failed','snfla_canary_audit_failed']),'Round10: remaining Future18 twin/checkpoint/shadow/canary evidence must fail closed on persistence/audit failure.',fail)
need('run-second-ten-round-hardening.py' in workflow and 'run-second-ten-round-hardening.py' in build,'Second ten-round gate must run in CI and deterministic builder.',fail)
need("VERSION='2.0.2'" in build and 'SECOND_TEN_ROUND_REVIEW_ROUNDS=10' in build,'Builder must bind v2.0.2 and second-ten-round evidence.',fail)
need('Round 10' in audit and ('PENDING' in audit or 'No new defect' in audit),'Audit must explicitly record the final Round 10 state.',fail)
need('wp_insert_post(' not in hard and '$wpdb->posts' not in hard and 'SNFLA_Migration::migrate(' not in hard,'Hardening must not create a second publication/migration backend.',fail)
need('LegacyPublicationMigration::migrate_selected' in all_php and 'LegacyPublicationRollback::rollback_selected' in all_php,'File21 canonical migration/rollback command boundary must remain.',fail)

if fail:
    print('Second ten-round hardening gate failed:',file=sys.stderr)
    for item in fail: print('-',item,file=sys.stderr)
    sys.exit(1)
print('Second ten-round hardening gate passed for implemented controls and audit trace.')
