from pathlib import Path

def replace(path, old, new):
    p = Path(path)
    s = p.read_text()
    if old not in s:
        raise SystemExit(f'round4 patch target missing in {path}: {old[:80]!r}')
    p.write_text(s.replace(old, new, 1))

future = 'includes/class-snfla-future18.php'
replace(future,
"\t\t$request = array( 'feature_id' => 'F04-FUT-004', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'required' => array( 'desktop', 'mobile', 'rtl', 'keyboard', 'zoom_200', 'reduced_motion', 'dom_semantics', 'accessibility_tree' ) );\n\t\t$evidence = apply_filters( 'snfla_visual_migration_diff_provider_v1', array( 'verified' => false, 'owner' => 'File 20/File 25' ), $request );",
"\t\t$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );\n\t\t$request = array( 'feature_id' => 'F04-FUT-004', 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'required' => array( 'desktop', 'mobile', 'rtl', 'keyboard', 'zoom_200', 'reduced_motion', 'dom_semantics', 'accessibility_tree' ), 'source_signature' => $source_signature );\n\t\t$request['request_digest'] = SNFLA_Checksum::hash( $request );\n\t\t$evidence = apply_filters( 'snfla_visual_migration_diff_provider_v1', array( 'verified' => false, 'owner' => 'File 20/File 25' ), $request );")

replace(future,
"\t\t$provider = apply_filters( 'snfla_redirect_citation_observatory_v1', array( 'verified' => false ), array( 'rows' => $rows, 'checks' => array( '301_or_410', 'redirect_loop', 'redirect_chain', 'query_preservation', 'fragment_preservation', 'external_citation_continuity' ) ) );",
"\t\t$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );\n\t\t$request = array( 'rows' => $rows, 'checks' => array( '301_or_410', 'redirect_loop', 'redirect_chain', 'query_preservation', 'fragment_preservation', 'external_citation_continuity' ), 'source_signature' => $source_signature );\n\t\t$request['request_digest'] = SNFLA_Checksum::hash( $request );\n\t\t$provider = apply_filters( 'snfla_redirect_citation_observatory_v1', array( 'verified' => false ), $request );")

replace(future,
"\t\t$request = array( 'feature_id' => 'F04-FUT-016', 'environment' => $environment, 'required_exercises' => array( 'backup_restore', 'migration', 'provider_outage', 'queue_retry', 'cache_rebuild', 'search_reindex', 'rollback', 'reconciliation' ), 'production_chaos_allowed' => false );\n\t\t$evidence = apply_filters( 'snfla_disaster_recovery_gameday_v1', array( 'verified' => false ), $request );",
"\t\t$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );\n\t\t$request = array( 'feature_id' => 'F04-FUT-016', 'environment' => $environment, 'required_exercises' => array( 'backup_restore', 'migration', 'provider_outage', 'queue_retry', 'cache_rebuild', 'search_reindex', 'rollback', 'reconciliation' ), 'production_chaos_allowed' => false, 'source_signature' => $source_signature );\n\t\t$request['request_digest'] = SNFLA_Checksum::hash( $request );\n\t\t$evidence = apply_filters( 'snfla_disaster_recovery_gameday_v1', array( 'verified' => false ), $request );")

replace(future,
"\t\t$result = array( 'feature_id' => 'F04-FUT-016', 'verified' => $verified, 'environment' => $environment, 'production_chaos_allowed' => false, 'evidence' => SNFLA_Audit::redact( is_array( $evidence ) ? $evidence : array() ), 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ) );",
"\t\t$result = array( 'feature_id' => 'F04-FUT-016', 'verified' => $verified, 'environment' => $environment, 'production_chaos_allowed' => false, 'source_signature' => $source_signature, 'request_digest' => $request['request_digest'], 'evidence' => SNFLA_Audit::redact( is_array( $evidence ) ? $evidence : array() ), 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ) );")

replace(future,
"\t\t\t'disaster_recovery_gameday_verified' => is_array( $gameday ) && SNFLA_Integrity::evidence_valid( $gameday ),",
"\t\t\t'disaster_recovery_gameday_verified' => is_array( $gameday ) && SNFLA_Integrity::evidence_valid( $gameday ) && ! empty( $gameday['verified'] ) && ! empty( $gameday['source_signature'] ) && hash_equals( (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ), (string) $gameday['source_signature'] ),")

hard = 'includes/class-snfla-post-audit-hardening.php'
replace(hard,
"\t\t$counts_valid = isset( $evidence['diff_count'], $evidence['critical_diff_count'] )",
"\t\t$source_bound = self::provider_request_bound( $evidence, $request );\n\t\t$counts_valid = isset( $evidence['diff_count'], $evidence['critical_diff_count'] )")
replace(hard,
"\t\t\t&& 0 === absint( $evidence['critical_diff_count'] )\n\t\t\t&& empty( $missing );",
"\t\t\t&& 0 === absint( $evidence['critical_diff_count'] )\n\t\t\t&& $source_bound\n\t\t\t&& empty( $missing );")
replace(hard,
"\t\t\t'critical_diff_count_zero'     => $counts_valid && 0 === absint( $evidence['critical_diff_count'] ),",
"\t\t\t'critical_diff_count_zero'     => $counts_valid && 0 === absint( $evidence['critical_diff_count'] ),\n\t\t\t'provider_request_source_bound' => $source_bound,")

replace(hard,
"\t\t$missing  = self::missing_or_failed( $required, $matrix );\n\t\t$valid    = ! empty( $evidence['verified'] ) && ! empty( $evidence['provider_id'] ) && ! empty( $required ) && empty( $missing );",
"\t\t$missing  = self::missing_or_failed( $required, $matrix );\n\t\t$source_bound = self::provider_request_bound( $evidence, $request );\n\t\t$valid    = ! empty( $evidence['verified'] ) && ! empty( $evidence['provider_id'] ) && ! empty( $required ) && $source_bound && empty( $missing );")
replace(hard,
"\t\t\t'missing_or_failed_checks'    => $missing,\n\t\t);\n\t\t$locked = SNFLA_Inventory::locked();",
"\t\t\t'missing_or_failed_checks'    => $missing,\n\t\t\t'provider_request_source_bound'=> $source_bound,\n\t\t);\n\t\t$locked = SNFLA_Inventory::locked();")
replace(hard,
"\t\t\t'source_signature'  => sanitize_text_field( (string) ( $locked['source_signature'] ?? '' ) ),",
"\t\t\t'source_signature'  => sanitize_text_field( (string) ( $request['source_signature'] ?? '' ) ),\n\t\t\t'request_digest'    => sanitize_text_field( (string) ( $request['request_digest'] ?? '' ) ),")

# GameDay: bind provider response to exact request/source.
replace(hard,
"\t\t$production_safe = empty( $evidence['production_environment'] )",
"\t\t$source_bound = self::provider_request_bound( $evidence, $request );\n\t\t$production_safe = empty( $evidence['production_environment'] )")
replace(hard,
"\t\t\t&& ! empty( $required )\n\t\t\t&& empty( $missing )\n\t\t\t&& $production_safe;",
"\t\t\t&& ! empty( $required )\n\t\t\t&& empty( $missing )\n\t\t\t&& $source_bound\n\t\t\t&& $production_safe;")
replace(hard,
"\t\t\t'production_environment_refused'=> $production_safe,",
"\t\t\t'production_environment_refused'=> $production_safe,\n\t\t\t'provider_request_source_bound' => $source_bound,")

insert = """
\tprivate static function provider_request_bound( array $evidence, array $request ) {
\t\t$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
\t\t$request_digest   = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
\t\t$echo_signature   = strtolower( trim( (string) ( $evidence['source_signature'] ?? '' ) ) );
\t\t$echo_digest      = strtolower( trim( (string) ( $evidence['request_digest'] ?? '' ) ) );
\t\t$payload = $request;
\t\tunset( $payload['request_digest'] );
\t\treturn 1 === preg_match( '/^[a-f0-9]{64}$/', $source_signature )
\t\t\t&& 1 === preg_match( '/^[a-f0-9]{64}$/', $request_digest )
\t\t\t&& hash_equals( $request_digest, SNFLA_Checksum::hash( $payload ) )
\t\t\t&& hash_equals( $source_signature, $echo_signature )
\t\t\t&& hash_equals( $request_digest, $echo_digest );
\t}

"""
replace(hard, "\tprivate static function evidence_matrix( array $evidence ) {", insert + "\tprivate static function evidence_matrix( array $evidence ) {")

Path('tools/round4-selfpatch.py').unlink()
Path('.github/workflows/round4-selfpatch.yml').unlink()
