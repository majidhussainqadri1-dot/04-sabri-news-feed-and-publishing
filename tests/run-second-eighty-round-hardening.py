#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT=Path(__file__).resolve().parents[1]
def t(p): return (ROOT/p).read_text(encoding='utf-8')
def need(c,m,f):
    if not c: f.append(m)

f=[]
main=t('sabri-news-feed-legacy-adapter.php')
integrity=t('includes/class-snfla-integrity.php')
checksum=t('includes/class-snfla-checksum.php')
audit=t('includes/class-snfla-audit.php')
rest=t('includes/class-snfla-rest.php')
mapping=t('includes/class-snfla-mapping.php')
interactions=t('includes/class-snfla-interaction-provider.php')
file21=t('includes/class-snfla-file21-adapter.php')
migration=t('includes/class-snfla-migration.php')
rollback=t('includes/class-snfla-rollback.php')
reconciliation=t('includes/class-snfla-reconciliation.php')
redirects=t('includes/class-snfla-redirects.php')
db=t('includes/class-snfla-database.php')
future=t('includes/class-snfla-future18.php')
cli=t('includes/class-snfla-cli.php')
workflow=t('.github/workflows/file04-legacy-adapter-ci.yml')
build=t('tools/build-release.py')
status=t('STATUS.md')
record=t('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md')

need('Version: 2.0.5' in main and "SNFLA_VERSION', '2.0.5'" in main,'R26 v2.0.5 runtime missing',f)
need("SNFLA_SCHEMA_VERSION', '1.3.0'" in main,'storage schema must remain 1.3.0',f)
need('strict_checkpoint_ids' in integrity and 'null === $stored_ids' in integrity,'R1 strict checkpoint IDs missing',f)
need('is_wp_error( $terms )' in checksum and "return '';" in checksum,'R2 taxonomy checksum fail-closed missing',f)
need('event_row_authentic' in audit and "'' === $action" in audit,'R3-R4 authenticated/nonempty audit actions missing',f)
need('last_trusted' in rest and 'reconciliation_trusted' in rest and 'dry_trusted' in rest,'R5-R6 REST status evidence authentication missing',f)
need('allowed_statuses' in mapping and 'strict_positive_id' in mapping and 'strict_nonnegative_id' in mapping and 'uuid4_valid' in mapping,'R7-R12 mapping/dry-run identity controls missing',f)
need('progress_state_valid' in interactions and 'strict_positive_id' in interactions,'R13-R14 interaction progress/provider identity controls missing',f)
need('strict_id_batch' in file21 and 'strict_positive_id' in file21,'R15-R16 File21 strict identity boundary missing',f)
need('snfla_run_ledger_identity_invalid' in migration and 'snfla_run_ledger_corrupt' in migration and "array( 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' )" in migration,'R17-R19 run ledger hardening missing',f)
need("is_int( $value ) && $value >= 1" in rest,'R20 strict lifecycle version REST input missing',f)
need('static $verified_this_request' in db and 'self::verify_schema()' in db and 'snfla_activation_version_evidence_failed' in db and 'snfla_integrity_schedule_failed' in db and 'SNFLA_Integrity::evidence_valid( $handover )' in db,'R21-R25 database physical-health/activation evidence controls missing',f)
need("VERSION='2.0.5'" in build and 'SECOND_EIGHTY_ROUND_REVIEW_ROUNDS=80' in build and 'run-second-eighty-round-hardening.py' in build,'R26-R27 builder integration missing',f)
need('run-second-eighty-round-hardening.py' in workflow,'R27 CI integration missing',f)

# R28-R32: strengthened historical QA, physical schema/index and DB lock semantics.
historical=t('tests/run-eighty-round-hardening.py')
need('Historical first 80-round guardrails remain present' in historical and "SNFLA_VERSION', '2.0.5'" in historical,'R28 historical eighty-round gate is not aligned to the current strengthened release',f)
need('$column_contracts' in db and "SHOW COLUMNS FROM `{$table}`" in db and '_type_mismatch' in db and '_nullability_mismatch' in db,'R29 physical column contract verification missing',f)
need("'Sub_part'" in db and '_prefix_index_not_allowed' in db,'R30 prefix-index rejection missing',f)
need("preg_match( '/^[a-z0-9_]{1,40}$/D', $name )" in db and 'database_lock_release_failed' in db,'R31-R32 exact DB lock identity/release alerting missing',f)

# R33-R36: fresh clean review plus status/conflict ledger integrity.
need('snfla_mapping_status_corrupt' in rest and 'snfla_conflict_status_query_failed' in rest,'R34 mapping status/count corruption gate missing',f)
need('conflict_ledger_integrity' in mapping and 'redacted_context_json' in mapping and 'fingerprint' in mapping and "acquire_lock( 'conflicts'" in mapping,'R36 conflict-ledger physical-schema alignment/integrity missing',f)

# R37-R44: source-only disposition and strict migration/File21 identities.
need("array( '', 'post', 'sabri_news', 'source_only' )" in mapping,'R37 legitimate source_only mapping disposition missing',f)
need('strict_positive_ids' in integrity and 'snfla_invalid_quarantine_batch' in migration,'R38 strict quarantine batch identity missing',f)
need('snfla_invalid_migration_batch' in migration,'R39 strict core migration batch identity missing',f)
need('snfla_invalid_rollback_batch' in rollback,'R40 strict core rollback batch identity missing',f)
need('snfla_file21_rollback_result_unverified' in file21 and 'snfla_file21_rollback_unexpected_result_ids' in file21,'R41 File21 rollback result verification missing',f)
need('migration_target_valid' in file21 and 'rolled_back_target_valid' in file21 and 'contain_orphan' in file21 and file21.count('strict_positive_id') >= 4,'R42-R44 File21 provenance/orphan strict identities missing',f)

# R45-R48: interaction bridge identity, budget, source-row and source-schema errors.
need('snfla_interaction_resume_identity_invalid' in interactions,'R45 strict interaction resume identity missing',f)
need('snfla_interaction_budget_invalid' in interactions and 'interaction_budget_invalid' in interactions,'R46 bounded exact interaction budget missing',f)
need('$source_row_id = self::strict_positive_id' in interactions,'R47 strict source interaction row identity missing',f)
need('interaction_source_schema_query_failed_' in interactions,'R48 interaction source schema probe DB-error gate missing',f)

# R49-R55: Future18 strict identities plus current evidence freshness/binding.
need("'items' => array( 'type' => 'integer', 'minimum' => 1 )" in future and 'strict_positive_ids' in future,'R49-R51 Future18 strict REST/batch IDs missing',f)
need('private static function strict_positive_id' in future and 'self::strict_positive_id( $actor_id )' in future,'R52 Future18 scalar/actor strict identity missing',f)
need('proof_current' in rollback and 'rollback_proof_valid' in future,'R53 current rollback proof gate missing',f)
need('$gameday_current' in future and "future18_gameday_verified" in future,'R54 fresh current GameDay evidence gate missing',f)
need("'manifest_digest'" in future and "'request_digest'" in future and '15*MINUTE_IN_SECONDS' in future.replace(' ',''),'R55 source/manifest/request/freshness-bound canonical contract descriptors missing',f)

# R56-R64: REST/CLI/core contract compatibility and exact runtime units/versions.
need("'restore_reference'" in rest and "'restored_source_signature'" in rest and 'SNFLA_Migration::record_backup_proof( $actor, $evidence )' in rest,'R56 REST backup-proof contract does not match core evidence API',f)
need('wp_create_nonce( \'wp_rest\' )' in cli and 'current_read_actor' in cli,'R57 CLI status nonce/read-authority bridge missing',f)
need('private function expected_version' in cli and 'snfla_invalid_expected_version' in cli,'R58 CLI exact lifecycle version parser missing',f)
need('snfla_fallback_parameters_invalid' in redirects and '! is_int( $hours )' in redirects,'R59 exact fallback hours/version validation missing',f)
need('snfla_dry_run_parameters_invalid' in migration and '! is_int( $limit )' in migration,'R60 exact dry-run limit/version validation missing',f)
need("'hours' => array( 'type' => 'integer', 'default' => 24" in rest and "get_param( 'hours' )" in rest,'R61 REST fallback hours unit contract missing',f)
need('SNFLA_Reconciliation::approve_cutover' in rest and 'SNFLA_Reconciliation::cutover' not in rest,'R62 REST cutover canonical method mismatch remains',f)
need('snfla_reconciliation_version_invalid' in reconciliation and 'snfla_cutover_version_invalid' in reconciliation,'R63-R64 reconciliation/cutover exact lifecycle versions missing',f)

# R66-R79: final correction series and current proof trace.
need('SNFLA_REST::NS' in cli and 'SNFLA_REST::NAMESPACE' not in cli,'R66 WP-CLI REST namespace correction missing',f)
need('private function integer_arg' in cli and 'snfla_invalid_integer_arg' in cli,'R67 strict WP-CLI numeric parser missing',f)
need('mapping_row_valid' in mapping and 'snfla_mapping_row_corrupt' in mapping,'R68 mapping-row read integrity missing',f)
need('expected_fingerprint' in mapping and 'redacted_context_json' in mapping and 'hash_equals( $expected_fingerprint, $fingerprint )' in mapping,'R69 conflict fingerprint/JSON integrity missing',f)
need('$validated_codes' in mapping and "sanitize_key( $code ) !== $code" in mapping,'R70 exact dry-run conflict-code integrity missing',f)
need('snfla_migration_identity_or_version_invalid' in migration and '$authorized_actor !== $actor_id' in migration,'R71 core migration exact actor/version semantics missing',f)
need('snfla_rollback_identity_or_version_invalid' in rollback and '$authorized_actor !== $actor_id' in rollback,'R72 core rollback exact actor/version semantics missing',f)
caps=t('includes/class-snfla-capabilities.php'); schema=t('includes/class-snfla-schema.php')
need('snfla_actor_identity_invalid' in caps and '! is_int( $expected_actor_id )' in caps,'R73 strict revalidation actor identity missing',f)
need('snfla_lifecycle_identity_or_version_invalid' in schema and 'snfla_lifecycle_version_invalid' in schema,'R74 central lifecycle actor/version semantics missing',f)
need('lifecycle_audit_compensation_failed' in schema and 'lifecycle_recovery_audit_compensation_failed' in schema,'R75 lifecycle audit compensation verification missing',f)
need('conflict_resolution_compensation_failed' in mapping and 'conflict_supersession_compensation_failed' in mapping and 'system_conflict_compensation_failed' in mapping,'R76 conflict compensation escalation missing',f)
need('created_by_migration' in mapping and 'strict_nonnegative_id' in mapping and 'synthetic_view' in mapping,'R77 strict interaction-ledger write semantics missing',f)
need('snfla_interaction_query_identity_invalid' in mapping and "array( 'active', 'rolled_back' )" in mapping,'R78 strict interaction-ledger read/query semantics missing',f)
need('| 79 | **Defect.**' in record and '| 80 | **PENDING' in record,'R79 current audit trace or R80 pending boundary missing',f)

# R65 trace must be current before final fresh regression rounds.
need('| 65 | **Defect.**' in record and '| 79 | **Defect.**' in record and '| 80 | **PENDING' in record,'R79 audit trace is not current or R80 was pre-certified',f)
need('corrected through Round 79' in status or 'through Round 79' in status,'R79 truthful status does not state current review boundary',f)
for i in range(1,81): need(f'| {i} |' in record,f'audit record missing round {i}',f)

if f:
    print('Second 80-round hardening gate failed:',file=sys.stderr)
    for item in f: print('-',item,file=sys.stderr)
    sys.exit(1)
print('Second 80-round gate passed for R01-R79 corrected controls; R80 remains deliberately pending final fresh review.')
