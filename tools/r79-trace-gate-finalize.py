from pathlib import Path

audit=Path('SECOND-EIGHTY-ROUND-HARDENING-AUDIT.md')
s=audit.read_text(encoding='utf-8')
findings={
66:'**Defect.** Fresh exact-head QA exposed two source defects: the R36 conflict hardening had introduced literal escaped indentation that broke PHP syntax, and WP-CLI status referenced nonexistent `SNFLA_REST::NAMESPACE` instead of `SNFLA_REST::NS`. Both were corrected before continuing.',
67:'**Defect.** WP-CLI numeric options arrive as strings while the strengthened core expects canonical integers; explicit numeric overrides could therefore become unusable. A strict bounded decimal `integer_arg()` parser now normalizes only valid CLI numeric syntax into exact integers.',
68:'**Defect.** Mapping writes were strict but existing mapping rows were trusted on read. `mapping_row_valid()` now verifies legacy/target identity, target type, state, checksums, run UUID and progress JSON before any row is accepted.',
69:'**Defect.** Conflict-ledger integrity checked fingerprint shape but not fingerprint correctness and did not validate redacted JSON. The expected fingerprint is now recomputed and compared, and stored redacted JSON must decode cleanly.',
70:'**Defect.** Dry-run conflict codes were decoded and sanitized, allowing malformed stored values to alias canonical codes. Stored codes must now already be unique, nonempty canonical keys.',
71:'**Defect.** Core migration still accepted actor/version values through lossy equality/coercion. Migration now requires exact positive actor/version integers and uses exact lifecycle version/equality semantics.',
72:'**Defect.** Core rollback likewise allowed lossy actor/version semantics. Rollback now requires exact positive actor/version integers and exact actor identity.',
73:'**Defect.** `revalidate_actor()` itself normalized the expected actor with `absint`, so malformed direct callers could alias the current actor. Expected/current actor identity is now exact positive-integer equality.',
74:'**Defect.** Shared lifecycle primitives still normalized expected versions and did not strictly validate actor IDs. `transition()`, `assert_current()` and `recover_to_batch()` now enforce canonical positive identity/version semantics centrally.',
75:'**Defect.** Lifecycle audit-failure compensation restored old state/version without verifying restoration. Restoration is now verified; failed compensation raises a critical alert and manual-recovery error.',
76:'**Defect.** Conflict-resolution/supersession/system-resolution audit compensation did not explicitly escalate restoration failure. Exact restoration is now checked and critical operational alerts are emitted on compensation failure.',
77:'**Defect.** Interaction-ledger write paths still used lossy IDs/contribution coercion. Ledger writes now require strict legacy/target/canonical identity, source-row rules, strict contribution counts and boolean migration flags.',
78:'**Defect.** Interaction-ledger read/query/rollback paths still normalized identities, kinds, statuses and limits. Those paths now use strict positive/nonnegative IDs, kind/status allowlists, exact bounded limits and DB-error checks.',
79:'**Defect.** After R66-R78 the permanent second-eighty audit/status/gate no longer described or asserted the corrected source, so exact-head QA could not prove those rounds. The audit record and permanent deterministic gate were advanced through R79; R80 remains deliberately unclaimed until final fresh review.'}
for i,text in findings.items():
    old=f'| {i} | **PENDING — not yet claimed.** |'
    new=f'| {i} | {text} |'
    if old not in s:
        raise SystemExit(f'R79 audit row {i} target missing')
    s=s.replace(old,new,1)
s=s.replace('Defect rounds so far: **1–32, 34, 36–65**. Clean rounds so far: **33, 35**. R66–R80 are deliberately unclaimed until they are freshly reviewed on the corrected R65 source.', 'Defect rounds so far: **1–32, 34, 36–79**. Clean rounds so far: **33, 35**. **R80 alone remains deliberately unclaimed** until one final fresh adversarial review and exact-head regression are completed on the corrected R79 source.')
audit.write_text(s,encoding='utf-8')

test=Path('tests/run-second-eighty-round-hardening.py')
ts=test.read_text(encoding='utf-8')
marker="# R65 trace must be current before final fresh regression rounds.\n"
if marker not in ts: raise SystemExit('R79 second-gate insertion marker missing')
block=r'''# R66-R79: final correction series and current proof trace.
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

'''
ts=ts.replace(marker,block+marker,1)
# Replace old R65-final assertions that now conflict with R79 state.
ts=ts.replace("need('| 65 | **Defect.**' in record and '| 66 | **PENDING' in record,'R65 audit trace is not current or final regressions were pre-certified',f)","need('| 65 | **Defect.**' in record and '| 79 | **Defect.**' in record and '| 80 | **PENDING' in record,'R79 audit trace is not current or R80 was pre-certified',f)")
ts=ts.replace("need('corrected through Round 65' in status or 'through Round 65' in status,'R65 truthful status does not state current review boundary',f)","need('corrected through Round 79' in status or 'through Round 79' in status,'R79 truthful status does not state current review boundary',f)")
ts=ts.replace("print('Second 80-round gate passed for R01-R65 corrected controls; R66-R80 remain deliberately pending fresh regression.')","print('Second 80-round gate passed for R01-R79 corrected controls; R80 remains deliberately pending final fresh review.')")
test.write_text(ts,encoding='utf-8')

Path('tools/r79-trace-gate-finalize.py').unlink()
Path('.github/workflows/r79-trace-gate-finalize.yml').unlink()
