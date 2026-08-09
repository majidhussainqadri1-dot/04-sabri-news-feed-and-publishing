from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
start=s.index('\tpublic static function open_conflict(')
end=s.index('\n\tpublic static function resolve_system_conflicts(', start)
end2=s.index('\n\t}', end)+3
old=s[start:end2]
new=r'''\tpublic static function open_conflict( $legacy_id, $code, $severity, array $context = array(), $run_uuid = '' ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$code = sanitize_key( $code );
\t\t$severity = in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) ? $severity : 'blocker';
\t\t$run_uuid = sanitize_text_field( (string) $run_uuid );
\t\tif ( $legacy_id <= 0 || '' === $code || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) { return false; }
\t\t$redacted_json = wp_json_encode( SNFLA_Audit::redact( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
\t\tif ( ! is_string( $redacted_json ) ) { return false; }
\t\t$fingerprint = hash( 'sha256', SNFLA_Checksum::encode( array( 'run_uuid' => $run_uuid, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ) );
\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t$sql = $wpdb->prepare(
\t\t\t"INSERT INTO {$t['conflicts']} (legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid,created_at,resolved_at) VALUES (%d,%s,%s,%s,'open',%s,%s,%s,NULL) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status='open',redacted_context_json=VALUES(redacted_context_json),run_uuid=VALUES(run_uuid),resolved_at=NULL",
\t\t\t$legacy_id, $code, $severity, $fingerprint, $redacted_json, $run_uuid, $now
\t\t);
\t\t$wpdb->last_error = '';
\t\t$result = $wpdb->query( $sql );
\t\treturn false !== $result && empty( $wpdb->last_error );
\t}

\tpublic static function conflict_ledger_integrity() {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$rows = $wpdb->get_results( "SELECT id,legacy_id,conflict_code,severity,fingerprint,status,run_uuid FROM {$t['conflicts']} ORDER BY id ASC", ARRAY_A );
\t\tif ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_conflict_ledger_query_failed' ); }
\t\tforeach ( $rows as $row ) {
\t\t\t$id = self::strict_positive_id( $row['id'] ?? 0 );
\t\t\t$legacy_id = self::strict_positive_id( $row['legacy_id'] ?? 0 );
\t\t\t$code = (string) ( $row['conflict_code'] ?? '' );
\t\t\t$severity = (string) ( $row['severity'] ?? '' );
\t\t\t$fingerprint = strtolower( (string) ( $row['fingerprint'] ?? '' ) );
\t\t\t$status = (string) ( $row['status'] ?? '' );
\t\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) {
\t\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
\t\t\t}
\t\t}
\t\treturn true;
\t}

\tpublic static function open_conflict_count() {
\t\tglobal $wpdb;
\t\t$integrity = self::conflict_ledger_integrity();
\t\tif ( is_wp_error( $integrity ) ) { return PHP_INT_MAX; }
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$value = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
\t\treturn ! empty( $wpdb->last_error ) || null === $value || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', (string) $value ) ? PHP_INT_MAX : (int) $value;
\t}

\tpublic static function resolve_conflict( $conflict_id, $actor_id, $resolution_code ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$conflict_id = self::strict_positive_id( $conflict_id );
\t\t$actor_id = self::strict_positive_id( $actor_id );
\t\t$resolution_code = sanitize_key( $resolution_code );
\t\tif ( $conflict_id <= 0 || $actor_id <= 0 || '' === $resolution_code || is_wp_error( self::conflict_ledger_integrity() ) ) { return false; }
\t\tif ( ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
\t\ttry {
\t\t\t$wpdb->last_error = '';
\t\t\t$changed = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $conflict_id, 'status' => 'open' ), array( '%s', '%s' ), array( '%d', '%s' ) );
\t\t\tif ( false === $changed || ! empty( $wpdb->last_error ) || 1 !== (int) $changed ) { return false; }
\t\t\tif ( SNFLA_Audit::record( 'conflict_resolved', $actor_id, array( 'conflict_id' => $conflict_id, 'resolution_code' => $resolution_code ), 'conflict:' . $conflict_id ) ) { return true; }
\t\t\t$wpdb->last_error = '';
\t\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
\t\t\treturn false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted ? false : false;
\t\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
\t}

\tpublic static function supersede_run_conflicts( $run_uuid, $actor_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$run_uuid = sanitize_text_field( (string) $run_uuid );
\t\t$actor_id = self::strict_positive_id( $actor_id );
\t\tif ( '' === $run_uuid ) { return true; }
\t\tif ( ! self::uuid4_valid( $run_uuid ) || $actor_id <= 0 || is_wp_error( self::conflict_ledger_integrity() ) ) { return false; }
\t\tif ( ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
\t\ttry {
\t\t\t$wpdb->last_error = '';
\t\t\t$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE run_uuid=%s AND status='open' ORDER BY id ASC", $run_uuid ) );
\t\t\tif ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
\t\t\t$ids = array_values( array_filter( array_map( array( __CLASS__, 'strict_positive_id_for_callback' ), $changed_ids ) ) );
\t\t\tif ( count( $ids ) !== count( $changed_ids ) ) { return false; }
\t\t\tif ( empty( $ids ) ) { return true; }
\t\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t\t$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE run_uuid=%s AND status='open'", $now, $run_uuid ) );
\t\t\tif ( false === $result || ! empty( $wpdb->last_error ) || (int) $result !== count( $ids ) ) { return false; }
\t\t\tif ( SNFLA_Audit::record( 'dry_run_conflicts_superseded', $actor_id, array( 'run_uuid' => $run_uuid, 'resolved_count' => count( $ids ) ), 'dry-run:' . $run_uuid ) ) { return true; }
\t\t\t$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
\t\t\t$args = array_merge( array( $now ), $ids );
\t\t\t$wpdb->last_error = '';
\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})", $args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;
\t\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
\t}

\tpublic static function resolve_system_conflicts( $legacy_id, array $codes, $actor_id, $resolution_code = 'automatic_reconciliation_verified' ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$actor_id = self::strict_positive_id( $actor_id );
\t\t$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
\t\t$resolution_code = sanitize_key( $resolution_code );
\t\tif ( $legacy_id <= 0 || $actor_id <= 0 || empty( $codes ) || '' === $resolution_code ) { return true; }
\t\tif ( is_wp_error( self::conflict_ledger_integrity() ) || ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
\t\ttry {
\t\t\t$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
\t\t\t$args = array_merge( array( $legacy_id ), $codes );
\t\t\t$wpdb->last_error = '';
\t\t\t$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders}) ORDER BY id ASC", $args ) );
\t\t\tif ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
\t\t\t$ids = array_values( array_filter( array_map( array( __CLASS__, 'strict_positive_id_for_callback' ), $changed_ids ) ) );
\t\t\tif ( count( $ids ) !== count( $changed_ids ) || empty( $ids ) ) { return empty( $changed_ids ); }
\t\t\t$now = gmdate( 'Y-m-d H:i:s' );
\t\t\t$update_args = array_merge( array( $now, $legacy_id ), $codes );
\t\t\t$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders})", $update_args ) );
\t\t\tif ( false === $result || ! empty( $wpdb->last_error ) || (int) $result !== count( $ids ) ) { return false; }
\t\t\tif ( SNFLA_Audit::record( 'system_conflicts_resolved', $actor_id, array( 'legacy_id' => $legacy_id, 'codes' => $codes, 'resolution_code' => $resolution_code, 'resolved_count' => count( $ids ) ), 'legacy:' . $legacy_id ) ) { return true; }
\t\t\t$revert_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
\t\t\t$revert_args = array_merge( array( $now ), $ids );
\t\t\t$wpdb->last_error = '';
\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})", $revert_args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;
\t\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
\t}

\tpublic static function strict_positive_id_for_callback( $value ) { return self::strict_positive_id( $value ); }
'''
p.write_text(s[:start]+new+s[end2:],encoding='utf-8')
Path('tools/r36-conflict-schema-patch.py').unlink()
Path('.github/workflows/r36-conflict-schema-patch.yml').unlink()
