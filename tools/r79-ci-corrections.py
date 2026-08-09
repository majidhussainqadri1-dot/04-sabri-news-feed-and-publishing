from pathlib import Path

mapping=Path('includes/class-snfla-mapping.php')
s=mapping.read_text(encoding='utf-8')
# Repair any line-leading escaped indentation left by an earlier generated conflict block.
for _ in range(8):
    s=s.replace('\t\\t','\t')
    s=s.replace('\n\\t','\n\t')

# R69 intended hardening: stored conflict fingerprint and redacted JSON must be authentic.
if 'expected_fingerprint' not in s:
    old='''\t\t$rows = $wpdb->get_results( "SELECT id,legacy_id,conflict_code,severity,fingerprint,status,run_uuid FROM {$t['conflicts']} ORDER BY id ASC", ARRAY_A );'''
    new='''\t\t$rows = $wpdb->get_results( "SELECT id,legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid FROM {$t['conflicts']} ORDER BY id ASC", ARRAY_A );'''
    if old not in s: raise SystemExit('R79 conflict select target missing')
    s=s.replace(old,new,1)
    old2='''\t\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) {
\t\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
\t\t\t}'''
    new2='''\t\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t\t$context = json_decode( (string) ( $row['redacted_context_json'] ?? '' ), true );
\t\t\t$expected_fingerprint = hash( 'sha256', SNFLA_Checksum::encode( array( 'run_uuid' => $run_uuid, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ) );
\t\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! hash_equals( $expected_fingerprint, $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) || ! is_array( $context ) || JSON_ERROR_NONE !== json_last_error() ) {
\t\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
\t\t\t}'''
    if old2 not in s: raise SystemExit('R79 conflict integrity target missing')
    s=s.replace(old2,new2,1)

# R76 intended hardening: compensation failures must be operationally visible.
if 'conflict_resolution_compensation_failed' not in s:
    old='''\t\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
\t\t\treturn false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted ? false : false;'''
    new='''\t\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
\t\t\t$restored = false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted;
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'conflict_resolution_compensation_failed', 'critical', array( 'conflict_id' => $conflict_id ) ); }
\t\t\treturn false;'''
    if old not in s: raise SystemExit('R79 conflict resolve compensation target missing')
    s=s.replace(old,new,1)
    old2='''\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})", $args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;'''
    new2='''\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})", $args ) );
\t\t\t$restored = false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids );
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'conflict_supersession_compensation_failed', 'critical', array( 'run_uuid_digest' => hash( 'sha256', $run_uuid ), 'affected_count' => count( $ids ) ) ); }
\t\t\treturn false;'''
    if old2 not in s: raise SystemExit('R79 conflict supersession compensation target missing')
    s=s.replace(old2,new2,1)
    old3='''\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})", $revert_args ) );
\t\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;'''
    new3='''\t\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})", $revert_args ) );
\t\t\t$restored = false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids );
\t\t\tif ( ! $restored ) { do_action( 'snfla_operational_alert_v1', 'system_conflict_compensation_failed', 'critical', array( 'legacy_id' => $legacy_id, 'affected_count' => count( $ids ) ) ); }
\t\t\treturn false;'''
    if old3 not in s: raise SystemExit('R79 system conflict compensation target missing')
    s=s.replace(old3,new3,1)

mapping.write_text(s,encoding='utf-8')

# Make R78 proof semantic rather than formatting-sensitive.
test=Path('tests/run-second-eighty-round-hardening.py')
ts=test.read_text(encoding='utf-8')
old="need('snfla_interaction_query_identity_invalid' in mapping and \"array( 'active', 'rolled_back' )\" in mapping,'R78 strict interaction-ledger read/query semantics missing',f)"
new="need('snfla_interaction_query_identity_invalid' in mapping and \"'rolled_back'\" in mapping and \"'active'\" in mapping,'R78 strict interaction-ledger read/query semantics missing',f)"
if old not in ts: raise SystemExit('R79 R78-gate assertion target missing')
test.write_text(ts.replace(old,new,1),encoding='utf-8')

Path('tools/r79-ci-corrections.py').unlink()
Path('.github/workflows/r79-ci-corrections.yml').unlink()
