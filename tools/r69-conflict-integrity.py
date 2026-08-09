from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\t$rows = $wpdb->get_results( \"SELECT id,legacy_id,conflict_code,severity,fingerprint,status,run_uuid FROM {$t['conflicts']} ORDER BY id ASC\", ARRAY_A );"""
new="""\t\t$rows = $wpdb->get_results( \"SELECT id,legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid FROM {$t['conflicts']} ORDER BY id ASC\", ARRAY_A );"""
if old not in s: raise SystemExit('R69 select target missing')
s=s.replace(old,new,1)
old2="""\t\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) {
\t\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
\t\t\t}"""
new2="""\t\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t\t$context = json_decode( (string) ( $row['redacted_context_json'] ?? '' ), true );
\t\t\t$expected_fingerprint = hash( 'sha256', SNFLA_Checksum::encode( array( 'run_uuid' => $run_uuid, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ) );
\t\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! hash_equals( $expected_fingerprint, $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) || ! is_array( $context ) || JSON_ERROR_NONE !== json_last_error() ) {
\t\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
\t\t\t}"""
if old2 not in s: raise SystemExit('R69 integrity target missing')
p.write_text(s.replace(old2,new2,1),encoding='utf-8')
Path('tools/r69-conflict-integrity.py').unlink();Path('.github/workflows/r69-conflict-integrity.yml').unlink()
