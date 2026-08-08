from pathlib import Path

p = Path('includes/class-snfla-plan-completion.php')
s = p.read_text()

def rep(old,new):
    global s
    if old not in s:
        raise SystemExit('round7 target missing: '+old[:100])
    s=s.replace(old,new,1)

rep("\t\t$file26 = apply_filters( 'sabri_file26_accept_file04_contract_v1', array( 'accepted' => false, 'status' => 'unknown' ), SNFLA_Central_Plan::module_manifest() );\n\t\t$checks['file26'] = array( 'status' => is_array( $file26 ) && ! empty( $file26['accepted'] ) ? 'pass' : 'unknown', 'evidence' => SNFLA_Audit::redact( is_array( $file26 ) ? $file26 : array() ) );",
"\t\t$file26_manifest = SNFLA_Central_Plan::module_manifest();\n\t\t$file26_request = array( 'consumer' => 'File 04', 'purpose' => 'legacy_resolution_search_handoff', 'manifest_digest' => SNFLA_Checksum::hash( $file26_manifest ) );\n\t\t$file26 = apply_filters( 'sabri_file26_accept_file04_contract_v1', array( 'accepted' => false, 'status' => 'unknown' ), $file26_request );\n\t\t$file26_bound = is_array( $file26 ) && ! empty( $file26['accepted'] ) && ! empty( $file26['provider_id'] ) && ! empty( $file26['manifest_digest'] ) && hash_equals( (string) $file26_request['manifest_digest'], (string) $file26['manifest_digest'] );\n\t\t$checks['file26'] = array( 'status' => $file26_bound ? 'pass' : 'unknown', 'request_digest' => $file26_request['manifest_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $file26 ) ? $file26 : array() ) );")

rep("\t\t$checks['audit'] = SNFLA_Audit::verify_chain();",
"\t\t$audit = SNFLA_Audit::verify_chain();\n\t\t$checks['audit'] = array_merge( array( 'status' => ! empty( $audit['valid'] ) ? 'pass' : 'blocker' ), is_array( $audit ) ? $audit : array( 'valid' => false ) );")

old="\t\t$blockers = array();\n\t\tforeach ( $checks as $key => $row ) { if ( is_array( $row ) && 'blocker' === ( $row['status'] ?? '' ) ) { $blockers[] = $key; } }\n\t\treturn array( 'schema' => 1, 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'checks' => $checks, 'blockers' => $blockers, 'healthy_for_current_lifecycle' => empty( $blockers ), 'production_acceptance_separate' => true );"
new="\t\t$blockers = array(); $unknown = array();\n\t\tforeach ( $checks as $key => $row ) {\n\t\t\tif ( ! is_array( $row ) ) { continue; }\n\t\t\tif ( 'blocker' === ( $row['status'] ?? '' ) ) { $blockers[] = $key; }\n\t\t\telseif ( in_array( (string) ( $row['status'] ?? '' ), array( 'unknown', 'pending_cutover' ), true ) ) { $unknown[] = $key; }\n\t\t}\n\t\treturn array( 'schema' => 2, 'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'checks' => $checks, 'blockers' => $blockers, 'unknown_or_pending' => $unknown, 'healthy_for_current_lifecycle' => empty( $blockers ), 'production_acceptance_separate' => true, 'production_accepted' => false );"
rep(old,new)

old="""\t\t$readiness['file04_system_check'] = self::system_check();
\t\t$readiness['performance_metrics'] = self::metrics_summary();
\t\t$readiness['blockers'] = array_values( array_unique( $blockers ) );
\t\t$readiness['production_ready'] = empty( $readiness['blockers'] );
\t\treturn $readiness;"""
new="""\t\t$system = self::system_check();
\t\tforeach ( (array) ( $system['blockers'] ?? array() ) as $system_blocker ) { $blockers[] = 'file04_system_' . sanitize_key( $system_blocker ); }
\t\t$readiness['file04_system_check'] = $system;
\t\t$readiness['performance_metrics'] = self::metrics_summary();
\t\t$readiness['blockers'] = array_values( array_unique( $blockers ) );
\t\t$readiness['source_candidate_ready'] = empty( $readiness['blockers'] );
\t\t// Source/CI evidence can never self-promote this adapter to production acceptance.
\t\t$readiness['production_ready'] = false;
\t\t$readiness['external_acceptance_required'] = array( 'hostinger_staging', 'real_file00_file21_file26_contracts', 'backup_restore', 'real_role_journeys', 'founder_approval', 'production_monitoring_rollback_window' );
\t\treturn $readiness;"""
rep(old,new)

p.write_text(s)
Path('tools/round7-selfpatch.py').unlink()
Path('.github/workflows/round7-selfpatch.yml').unlink()
