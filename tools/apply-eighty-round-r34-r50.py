from pathlib import Path
import re
ROOT=Path(__file__).resolve().parents[1]

def t(path): return (ROOT/path).read_text(encoding='utf-8')
def w(path,s): (ROOT/path).write_text(s,encoding='utf-8')
def rep(path,old,new,count=1):
    s=t(path)
    n=s.count(old)
    if n!=count: raise SystemExit(f'{path}: expected {count}, got {n}: {old[:80]!r}')
    w(path,s.replace(old,new,count))

def sub(path,pattern,repl,count=1):
    s=t(path)
    ns,n=re.subn(pattern,repl,s,count=count,flags=re.S)
    if n!=count: raise SystemExit(f'{path}: regex expected {count}, got {n}: {pattern[:80]!r}')
    w(path,ns)

# R34: destructive rollback preflight must reject malformed interaction-progress evidence.
rep('includes/class-snfla-rollback.php', """\t\t\t$status    = is_array( $map ) ? sanitize_key( $map['status'] ?? '' ) : '';
\t\t\t$allowed   = array( 'migrated', 'interaction_pending', 'conflict', 'publication_rolled_back_interactions_pending', 'rollback_conflict' );
\t\t\tif ( ! is_array( $map ) || $target_id <= 0 || ! in_array( $status, $allowed, true ) ) { $blocked[ $legacy_id ] = 'mapping_unavailable'; continue; }
""", """\t\t\t$status    = is_array( $map ) ? sanitize_key( $map['status'] ?? '' ) : '';
\t\t\t$allowed   = array( 'migrated', 'interaction_pending', 'conflict', 'publication_rolled_back_interactions_pending', 'rollback_conflict' );
\t\t\tif ( ! is_array( $map ) || $target_id <= 0 || ! in_array( $status, $allowed, true ) ) { $blocked[ $legacy_id ] = 'mapping_unavailable'; continue; }
\t\t\t$progress = SNFLA_Mapping::progress_checked( $legacy_id );
\t\t\tif ( is_wp_error( $progress ) ) { $blocked[ $legacy_id ] = 'interaction_progress_corrupt'; continue; }
""")

# R35: migration warning/resume path must not suppress mapping/progress ledger corruption.
rep('includes/class-snfla-migration.php', """\t\t\tforeach ( $resumable as $legacy_id => $code ) {
\t\t\t\t$current = SNFLA_Mapping::get( $legacy_id );
\t\t\t\tSNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'target_type' => $current['target_type'] ?? '', 'status' => 'interaction_pending', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $current['target_checksum'] ?? '', 'run_uuid' => $run_uuid, 'last_error_code' => $code, 'interaction_ledger' => SNFLA_Mapping::progress( $legacy_id ) ) );
\t\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, 'interaction_migration_incomplete', 'high', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
\t\t\t}
""", """\t\t\tforeach ( $resumable as $legacy_id => $code ) {
\t\t\t\t$current = SNFLA_Mapping::get_checked( $legacy_id );
\t\t\t\t$progress = SNFLA_Mapping::progress_checked( $legacy_id );
\t\t\t\tif ( is_wp_error( $current ) || is_wp_error( $progress ) ) {
\t\t\t\t\t$ledger_code = is_wp_error( $current ) ? $current->get_error_code() : $progress->get_error_code();
\t\t\t\t\t$mapping_failures[ $legacy_id ] = $ledger_code;
\t\t\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, $ledger_code, 'blocker', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t\tif ( ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'target_type' => $current['target_type'] ?? '', 'status' => 'interaction_pending', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $current['target_checksum'] ?? '', 'run_uuid' => $run_uuid, 'last_error_code' => $code, 'interaction_ledger' => $progress ) ) {
\t\t\t\t\t$mapping_failures[ $legacy_id ] = 'interaction_progress_persist_failed';
\t\t\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, 'interaction_progress_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
\t\t\t\t\tcontinue;
\t\t\t\t}
\t\t\t\tSNFLA_Mapping::open_conflict( $legacy_id, 'interaction_migration_incomplete', 'high', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
\t\t\t}
""")

# R36: partial rollback retries validate historical target provenance even after File21 active mapping is intentionally removed.
rep('includes/class-snfla-file21-adapter.php', """\tpublic static function migration_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );
\t\t$post = $target_id > 0 ? get_post( $target_id ) : null;
\t\treturn $legacy_id > 0
\t\t\t&& $post instanceof WP_Post
\t\t\t&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
\t\t\t&& self::target_for( $legacy_id ) === $target_id
\t\t\t&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
\t\t\t&& SNFLA_Inventory::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
\t}
""", """\tpublic static function migration_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );
\t\t$post = $target_id > 0 ? get_post( $target_id ) : null;
\t\treturn $legacy_id > 0
\t\t\t&& $post instanceof WP_Post
\t\t\t&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
\t\t\t&& self::target_for( $legacy_id ) === $target_id
\t\t\t&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
\t\t\t&& SNFLA_Inventory::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
\t}

\tpublic static function rolled_back_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );
\t\t$post = $target_id > 0 ? get_post( $target_id ) : null;
\t\treturn $legacy_id > 0
\t\t\t&& $post instanceof WP_Post
\t\t\t&& in_array( $post->post_type, array( 'post', 'sabri_news' ), true )
\t\t\t&& 'private' === (string) $post->post_status
\t\t\t&& 0 === self::target_for( $legacy_id )
\t\t\t&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
\t\t\t&& SNFLA_Inventory::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
\t}
""")
rep('includes/class-snfla-rollback.php', """\t\t\t$post = get_post( $target_id );
\t\t\tif ( ! $post instanceof WP_Post || ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) { $blocked[ $legacy_id ] = 'target_provenance_failed'; continue; }
""", """\t\t\t$post = get_post( $target_id );
\t\t\t$publication_already_rolled = in_array( $status, array( 'publication_rolled_back_interactions_pending', 'rollback_conflict' ), true ) && 0 === SNFLA_File21_Adapter::target_for( $legacy_id );
\t\t\t$target_valid = $publication_already_rolled ? SNFLA_File21_Adapter::rolled_back_target_valid( $legacy_id, $target_id ) : SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id );
\t\t\tif ( ! $post instanceof WP_Post || ! $target_valid ) { $blocked[ $legacy_id ] = 'target_provenance_failed'; continue; }
""")

# R37: full activation-handover restore cannot ignore unresolved/migrating local mappings.
rep('includes/class-snfla-rollback.php', """\t\t\t\t$remaining_raw = $wpdb->get_var( \"SELECT COUNT(*) FROM {$tables['map']} WHERE status IN ('migrated','interaction_pending','conflict','rollback_conflict','publication_rolled_back_interactions_pending')\" );
""", """\t\t\t\t$remaining_raw = $wpdb->get_var( \"SELECT COUNT(*) FROM {$tables['map']} WHERE status NOT IN ('rolled_back','quarantined')\" );
""")

# R38: reconciliation report compensation after audit failure is verified.
rep('includes/class-snfla-reconciliation.php', """\t\t\tif ( ! SNFLA_Audit::record( 'reconciliation_completed', $actor_id, array( 'source_total' => $source_total, 'verified_mappings' => $verified, 'issue_count' => $issue_count, 'open_conflicts' => $open_conflicts, 'green' => $report['green'], 'complete_scan' => true, 'final_delta_matches_lock' => $report['final_delta_matches_lock'], 'report_checksum' => $report['report_checksum'] ), $object_ref ) ) {
\t\t\t\tupdate_option( 'snfla_reconciliation_report', $previous_report, false );
\t\t\t\treturn new WP_Error( 'snfla_reconciliation_audit_failed', 'The reconciliation report was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
\t\t\t}
""", """\t\t\tif ( ! SNFLA_Audit::record( 'reconciliation_completed', $actor_id, array( 'source_total' => $source_total, 'verified_mappings' => $verified, 'issue_count' => $issue_count, 'open_conflicts' => $open_conflicts, 'green' => $report['green'], 'complete_scan' => true, 'final_delta_matches_lock' => $report['final_delta_matches_lock'], 'report_checksum' => $report['report_checksum'] ), $object_ref ) ) {
\t\t\t\t$restored_previous = update_option( 'snfla_reconciliation_report', $previous_report, false ) || get_option( 'snfla_reconciliation_report', array() ) === $previous_report;
\t\t\t\treturn new WP_Error( $restored_previous ? 'snfla_reconciliation_audit_failed' : 'snfla_reconciliation_compensation_failed', $restored_previous ? 'The reconciliation report was reverted because audit evidence could not be written.' : 'Reconciliation audit failed and the previous report could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored_previous ) );
\t\t\t}
""")

# R39/R40: malformed original interaction evidence fails reconciliation and rollback closed.
old="""\t\t\t\t\t$original       = json_decode( (string) ( $ledger['original_json'] ?? '{}' ), true );
\t\t\t\t\t$original       = is_array( $original ) ? $original : array();
\t\t\t\t\t$baseline       = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
"""
new="""\t\t\t\t\t$original       = json_decode( (string) ( $ledger['original_json'] ?? '{}' ), true );
\t\t\t\t\tif ( ! is_array( $original ) || JSON_ERROR_NONE !== json_last_error() ) { $issues[] = 'interaction_ledger_original_corrupt'; continue; }
\t\t\t\t\t$baseline       = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
"""
rep('includes/class-snfla-interaction-provider.php',old,new)
rep('includes/class-snfla-interaction-provider.php', """\t\t\t\t$original = json_decode( (string) ( $row['original_json'] ?? '{}' ), true );
\t\t\t\t$original = is_array( $original ) ? $original : array();
\t\t\t\t$baseline = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
""", """\t\t\t\t$original = json_decode( (string) ( $row['original_json'] ?? '{}' ), true );
\t\t\t\tif ( ! is_array( $original ) || JSON_ERROR_NONE !== json_last_error() ) { $errors[] = 'interaction_ledger_original_corrupt'; continue; }
\t\t\t\t$baseline = isset( $original['baseline'] ) && is_array( $original['baseline'] ) ? $original['baseline'] : array();
""")

# R41: system conflict resolution is compensating if its audit event cannot be persisted.
sub('includes/class-snfla-mapping.php', r"\tpublic static function resolve_system_conflicts\(.*?\n\t}\n\n\t/\*\* Stream", """\tpublic static function resolve_system_conflicts( $legacy_id, array $codes, $actor_id, $resolution = 'system_verified' ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
\t\tif ( empty( $codes ) ) { return true; }
\t\t$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
\t\t$select_args = array_merge( array( absint( $legacy_id ) ), $codes );
\t\t$wpdb->last_error = '';
\t\t$ids = $wpdb->get_col( $wpdb->prepare( \"SELECT id FROM {$t['conflicts']} WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders}) ORDER BY id ASC\", $select_args ) );
\t\tif ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) { return false; }
\t\t$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
\t\tif ( empty( $ids ) ) { return true; }
\t\t$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
\t\t$update_args = array_merge( array( gmdate( 'Y-m-d H:i:s' ) ), $ids );
\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE status='open' AND id IN ({$id_placeholders})\", $update_args ) );
\t\tif ( false === $updated || (int) $updated !== count( $ids ) ) { return false; }
\t\tif ( SNFLA_Audit::record( 'system_conflicts_resolved', $actor_id, array( 'legacy_id' => absint( $legacy_id ), 'codes' => $codes, 'resolution' => sanitize_key( $resolution ), 'count' => count( $ids ) ), 'legacy:' . absint( $legacy_id ) ) ) { return true; }
\t\t$reverted = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE status='resolved' AND id IN ({$id_placeholders})\", $ids ) );
\t\treturn false;
\t}

\t/** Stream""")

# R42: superseding prior dry-run conflicts is also audit-compensating.
sub('includes/class-snfla-mapping.php', r"\tpublic static function supersede_run_conflicts\(.*?\n\t}\n\n\tpublic static function open_conflict", """\tpublic static function supersede_run_conflicts( $run_uuid, $actor_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$run_uuid = sanitize_text_field( (string) $run_uuid );
\t\tif ( '' === $run_uuid ) { return true; }
\t\t$wpdb->last_error = '';
\t\t$ids = $wpdb->get_col( $wpdb->prepare( \"SELECT id FROM {$t['conflicts']} WHERE run_uuid=%s AND status='open' ORDER BY id ASC\", $run_uuid ) );
\t\tif ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) { return false; }
\t\t$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
\t\tif ( empty( $ids ) ) { return true; }
\t\t$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
\t\t$args = array_merge( array( gmdate( 'Y-m-d H:i:s' ) ), $ids );
\t\t$updated = $wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='superseded',resolved_at=%s WHERE status='open' AND id IN ({$placeholders})\", $args ) );
\t\tif ( false === $updated || (int) $updated !== count( $ids ) ) { return false; }
\t\tif ( SNFLA_Audit::record( 'previous_dry_run_conflicts_superseded', $actor_id, array( 'run_uuid' => $run_uuid, 'count' => count( $ids ) ), 'dry-run:' . $run_uuid ) ) { return true; }
\t\t$wpdb->query( $wpdb->prepare( \"UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE status='superseded' AND id IN ({$placeholders})\", $ids ) );
\t\treturn false;
\t}

\tpublic static function open_conflict""")

# R43: reject File21 migration responses that contain unrequested legacy IDs.
rep('includes/class-snfla-plan-completion.php', """\t\tif ( is_wp_error( $result ) || ! is_array( $result ) ) { return $result; }
\t\t$migrated = (array) ( $result['migrated'] ?? array() );
\t\tforeach ( $legacy_ids as $legacy_id ) {
""", """\t\tif ( is_wp_error( $result ) || ! is_array( $result ) ) { return $result; }
\t\t$requested = SNFLA_Integrity::normalized_ids( $legacy_ids, SNFLA_Migration::MAX_BATCH );
\t\t$migrated = (array) ( $result['migrated'] ?? array() );
\t\t$returned = SNFLA_Integrity::normalized_ids( array_keys( $migrated ), SNFLA_Migration::MAX_BATCH );
\t\t$unexpected = array_values( array_diff( $returned, $requested ) );
\t\tif ( ! empty( $unexpected ) ) {
\t\t\tdo_action( 'snfla_operational_alert_v1', array( 'code' => 'file21_unexpected_migration_result_ids', 'severity' => 'critical', 'unexpected_count' => count( $unexpected ) ) );
\t\t\treturn new WP_Error( 'snfla_file21_unexpected_result_ids', 'Canonical File 21 returned migration results for legacy IDs that were not requested; File 04 refused to persist them.', array( 'status' => 502, 'unexpected_count' => count( $unexpected ) ) );
\t\t}
\t\tforeach ( $requested as $legacy_id ) {
""")

# R44: plan-preflight REST route uses the same strict positive, unique ID semantics as mutation routes.
rep('includes/class-snfla-plan-completion.php', """\t\t\t\t\t\t'validate_callback' => static function ( $value ) {
\t\t\t\t\t\t\treturn is_array( $value ) && count( $value ) >= 1 && count( $value ) <= SNFLA_Migration::MAX_BATCH;
\t\t\t\t\t\t},
""", """\t\t\t\t\t\t'validate_callback' => static function ( $value ) {
\t\t\t\t\t\t\tif ( ! is_array( $value ) || count( $value ) < 1 || count( $value ) > SNFLA_Migration::MAX_BATCH ) { return false; }
\t\t\t\t\t\t\t$seen = array();
\t\t\t\t\t\t\tforeach ( $value as $id ) {
\t\t\t\t\t\t\t\tif ( ! is_int( $id ) && ! ( is_string( $id ) && preg_match( '/^[1-9][0-9]*$/D', $id ) ) ) { return false; }
\t\t\t\t\t\t\t\t$id = (int) $id;
\t\t\t\t\t\t\t\tif ( $id <= 0 || isset( $seen[ $id ] ) ) { return false; }
\t\t\t\t\t\t\t\t$seen[ $id ] = true;
\t\t\t\t\t\t\t}
\t\t\t\t\t\t\treturn true;
\t\t\t\t\t\t},
""")

# R45: dry-run analysis is not accepted as current unless its audit event was persisted.
rep('includes/class-snfla-plan-completion.php', """\t\t$analysis['analysis_checksum'] = SNFLA_Checksum::hash( $analysis );
\t\tif ( ! update_option( self::ANALYSIS_OPTION, $analysis, false ) && get_option( self::ANALYSIS_OPTION, array() ) !== $analysis ) {
\t\t\treturn new WP_Error( 'snfla_dry_run_analysis_persist_failed', 'The dry-run planning analysis could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\tSNFLA_Audit::record( 'dry_run_analysis_completed', $actor_id, array( 'run_uuid' => $analysis['run_uuid'], 'analysis_checksum' => $analysis['analysis_checksum'], 'source_signature' => $analysis['source_signature'] ), 'dry-run-analysis:' . $analysis['run_uuid'] );
\t\treturn $analysis;
""", """\t\t$analysis['analysis_checksum'] = SNFLA_Checksum::hash( $analysis );
\t\t$previous_analysis = get_option( self::ANALYSIS_OPTION, array() );
\t\tif ( ! update_option( self::ANALYSIS_OPTION, $analysis, false ) && get_option( self::ANALYSIS_OPTION, array() ) !== $analysis ) {
\t\t\treturn new WP_Error( 'snfla_dry_run_analysis_persist_failed', 'The dry-run planning analysis could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\tif ( ! SNFLA_Audit::record( 'dry_run_analysis_completed', $actor_id, array( 'run_uuid' => $analysis['run_uuid'], 'analysis_checksum' => $analysis['analysis_checksum'], 'source_signature' => $analysis['source_signature'] ), 'dry-run-analysis:' . $analysis['run_uuid'] ) ) {
\t\t\t$restored_previous = update_option( self::ANALYSIS_OPTION, $previous_analysis, false ) || get_option( self::ANALYSIS_OPTION, array() ) === $previous_analysis;
\t\t\treturn new WP_Error( $restored_previous ? 'snfla_dry_run_analysis_audit_failed' : 'snfla_dry_run_analysis_compensation_failed', $restored_previous ? 'The dry-run analysis was reverted because its audit event could not be written.' : 'Dry-run analysis audit failed and previous evidence could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored_previous ) );
\t\t}
\t\treturn $analysis;
""")
rep('includes/class-snfla-plan-completion.php', """\t\tif ( empty( $dry['run_uuid'] ) || empty( $analysis['run_uuid'] ) || ! hash_equals( (string) $dry['run_uuid'], (string) $analysis['run_uuid'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) $dry['source_signature'], (string) ( $analysis['source_signature'] ?? '' ) ) ) {
\t\t\treturn array();
\t\t}
\t\treturn $analysis;
""", """\t\tif ( empty( $dry['run_uuid'] ) || empty( $analysis['run_uuid'] ) || ! hash_equals( (string) $dry['run_uuid'], (string) $analysis['run_uuid'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) $dry['source_signature'], (string) ( $analysis['source_signature'] ?? '' ) ) ) {
\t\t\treturn array();
\t\t}
\t\tif ( ! SNFLA_Audit::has_event( 'dry_run_analysis_completed', 'dry-run-analysis:' . (string) $analysis['run_uuid'], 'analysis_checksum', $expected_checksum ) ) { return array(); }
\t\treturn $analysis;
""")

# R46: meta-reference digests use the canonical checksum encoder; JSON failures do not collapse to the meta key.
rep('includes/class-snfla-plan-completion.php', """\t\t\t\t$refs[] = array( 'reference_id' => 'meta:' . $key, 'type' => 'legacy_reference', 'meta_key' => $key, 'value_digest' => hash( 'sha256', wp_json_encode( SNFLA_Audit::redact( $value ) ) ?: $key ) );
""", """\t\t\t\t$refs[] = array( 'reference_id' => 'meta:' . $key, 'type' => 'legacy_reference', 'meta_key' => $key, 'value_digest' => SNFLA_Checksum::hash( SNFLA_Audit::redact( $value ) ) );
""")

# R47/R49: File21 media preflight attestation is fresh and broken_links is a strict integer zero.
rep('includes/class-snfla-plan-completion.php', """\t\t$provider_bound = is_array( $evidence ) && ! empty( $evidence['source_signature'] ) && ! empty( $evidence['request_digest'] ) && hash_equals( $source_signature, (string) $evidence['source_signature'] ) && hash_equals( (string) $request['request_digest'], (string) $evidence['request_digest'] );
\t\tif ( ! is_array( $evidence ) || ! $provider_bound || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) || empty( $evidence['ownership_verified'] ) || empty( $evidence['rights_or_license_verified'] ) || empty( $evidence['alt_policy_verified'] ) || empty( $evidence['duplicate_hash_checked'] ) || ! array_key_exists( 'broken_links', $evidence ) || 0 !== absint( $evidence['broken_links'] ) ) {
""", """\t\t$provider_bound = is_array( $evidence ) && ! empty( $evidence['source_signature'] ) && ! empty( $evidence['request_digest'] ) && hash_equals( $source_signature, (string) $evidence['source_signature'] ) && hash_equals( (string) $request['request_digest'], (string) $evidence['request_digest'] );
\t\t$verified_at = is_array( $evidence ) && ! empty( $evidence['verified_at_utc'] ) ? strtotime( (string) $evidence['verified_at_utc'] . ' UTC' ) : false;
\t\t$broken_links = is_array( $evidence ) && array_key_exists( 'broken_links', $evidence ) ? filter_var( $evidence['broken_links'], FILTER_VALIDATE_INT ) : false;
\t\tif ( ! is_array( $evidence ) || ! $provider_bound || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) || empty( $evidence['ownership_verified'] ) || empty( $evidence['rights_or_license_verified'] ) || empty( $evidence['alt_policy_verified'] ) || empty( $evidence['duplicate_hash_checked'] ) || 0 !== $broken_links || false === $verified_at || $verified_at < time() - 15 * MINUTE_IN_SECONDS || $verified_at > time() + 300 ) {
""")

# R48: post-migration media verification is also fresh, not replayable forever.
rep('includes/class-snfla-plan-completion.php', """\t\t\t$verify_bound = is_array( $verify ) && ! empty( $verify['source_signature'] ) && ! empty( $verify['request_digest'] ) && hash_equals( (string) $verify_request['source_signature'], (string) $verify['source_signature'] ) && hash_equals( (string) $verify_request['request_digest'], (string) $verify['request_digest'] );
\t\t\tif ( ! is_array( $verify ) || ! $verify_bound || empty( $verify['verified'] ) || empty( $verify['provider_id'] ) || absint( $verify['target_id'] ?? 0 ) !== $target_id || absint( $verify['verified_reference_count'] ?? -1 ) !== absint( $preflight['reference_count'] ) ) {
""", """\t\t\t$verify_bound = is_array( $verify ) && ! empty( $verify['source_signature'] ) && ! empty( $verify['request_digest'] ) && hash_equals( (string) $verify_request['source_signature'], (string) $verify['source_signature'] ) && hash_equals( (string) $verify_request['request_digest'], (string) $verify['request_digest'] );
\t\t\t$verify_time = is_array( $verify ) && ! empty( $verify['verified_at_utc'] ) ? strtotime( (string) $verify['verified_at_utc'] . ' UTC' ) : false;
\t\t\t$verified_count = is_array( $verify ) && array_key_exists( 'verified_reference_count', $verify ) ? filter_var( $verify['verified_reference_count'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
\t\t\tif ( ! is_array( $verify ) || ! $verify_bound || empty( $verify['verified'] ) || empty( $verify['provider_id'] ) || absint( $verify['target_id'] ?? 0 ) !== $target_id || false === $verified_count || $verified_count !== absint( $preflight['reference_count'] ) || false === $verify_time || $verify_time < time() - 15 * MINUTE_IN_SECONDS || $verify_time > time() + 300 ) {
""")

# R50: deleted/unknown author placeholder evidence is bound to the exact requested legacy identity.
rep('includes/class-snfla-plan-completion.php', """\t\tif ( ! $user ) {
\t\t\t$replacement = apply_filters(
\t\t\t\t'sabri_file00_legacy_author_placeholder_v1',
\t\t\t\tarray( 'verified' => false ),
\t\t\t\tarray( 'file_number' => '04', 'legacy_id' => $legacy_id, 'legacy_author_id' => $author_id )
\t\t\t);
\t\t\tif ( ! is_array( $replacement ) || empty( $replacement['verified'] ) || empty( $replacement['user_id'] ) || empty( $replacement['platform_uuid'] ) ) {
""", """\t\tif ( ! $user ) {
\t\t\t$placeholder_request = array( 'file_number' => '04', 'legacy_id' => $legacy_id, 'legacy_author_id' => $author_id );
\t\t\t$placeholder_request['request_digest'] = SNFLA_Checksum::hash( $placeholder_request );
\t\t\t$replacement = apply_filters(
\t\t\t\t'sabri_file00_legacy_author_placeholder_v1',
\t\t\t\tarray( 'verified' => false ),
\t\t\t\t$placeholder_request
\t\t\t);
\t\t\t$placeholder_bound = is_array( $replacement ) && ! empty( $replacement['provider_id'] ) && absint( $replacement['legacy_id'] ?? 0 ) === $legacy_id && absint( $replacement['legacy_author_id'] ?? -1 ) === $author_id && ! empty( $replacement['request_digest'] ) && hash_equals( (string) $placeholder_request['request_digest'], (string) $replacement['request_digest'] );
\t\t\tif ( ! is_array( $replacement ) || ! $placeholder_bound || empty( $replacement['verified'] ) || empty( $replacement['user_id'] ) || empty( $replacement['platform_uuid'] ) ) {
""")

print('Applied sequential File 04 corrections for audit rounds 34-50.')
