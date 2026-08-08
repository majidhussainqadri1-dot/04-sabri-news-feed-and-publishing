from pathlib import Path
p=Path('includes/class-snfla-reconciliation.php')
s=p.read_text(encoding='utf-8')

s=s.replace("""\t\t\t$context = array( 'canonical_owner' => 'File 21', 'source_signature' => $locked['source_signature'], 'reconciliation_checksum' => (string) ( $report['report_checksum'] ?? '' ) );
\t\t\tdo_action( 'sabri_hnf_invalidate_cache' );""","""\t\t\t$context = array( 'canonical_owner' => 'File 21', 'source_signature' => $locked['source_signature'], 'reconciliation_checksum' => (string) ( $report['report_checksum'] ?? '' ) );
\t\t\t$context['request_digest'] = SNFLA_Checksum::hash( $context );
\t\t\tdo_action( 'sabri_hnf_invalidate_cache' );""",1)

s=s.replace("self::integration_evidence_valid( $cache_evidence, array( 'completed', 'not_required' ) )","self::integration_evidence_valid( $cache_evidence, array( 'completed', 'not_required' ), $context )",1)
s=s.replace("self::integration_evidence_valid( $search_evidence, array( 'completed', 'accepted', 'not_required' ) )","self::integration_evidence_valid( $search_evidence, array( 'completed', 'accepted', 'not_required' ), $context )",1)

old="""\tprivate static function integration_evidence_valid( $evidence, array $allowed_statuses ) {
\t\tif ( ! is_array( $evidence ) || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) ) {
\t\t\treturn false;
\t\t}
\t\t$status = sanitize_key( (string) ( $evidence['status'] ?? '' ) );
\t\tif ( ! in_array( $status, $allowed_statuses, true ) ) {
\t\t\treturn false;
\t\t}
\t\tif ( 'not_required' === $status && empty( $evidence['reason_code'] ) ) {
\t\t\treturn false;
\t\t}
\t\treturn true;
\t}
"""
new="""\tprivate static function integration_evidence_valid( $evidence, array $allowed_statuses, array $request ) {
\t\tif ( ! is_array( $evidence ) || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) ) {
\t\t\treturn false;
\t\t}
\t\t$status = sanitize_key( (string) ( $evidence['status'] ?? '' ) );
\t\tif ( ! in_array( $status, $allowed_statuses, true ) ) {
\t\t\treturn false;
\t\t}
\t\tif ( 'not_required' === $status && empty( $evidence['reason_code'] ) ) {
\t\t\treturn false;
\t\t}
\t\t$source_signature = strtolower( (string) ( $request['source_signature'] ?? '' ) );
\t\t$reconciliation_checksum = strtolower( (string) ( $request['reconciliation_checksum'] ?? '' ) );
\t\t$request_digest = strtolower( (string) ( $request['request_digest'] ?? '' ) );
\t\tif ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $source_signature )
\t\t\t|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $reconciliation_checksum )
\t\t\t|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $request_digest )
\t\t\t|| empty( $evidence['source_signature'] )
\t\t\t|| empty( $evidence['reconciliation_checksum'] )
\t\t\t|| empty( $evidence['request_digest'] )
\t\t\t|| ! hash_equals( $source_signature, strtolower( (string) $evidence['source_signature'] ) )
\t\t\t|| ! hash_equals( $reconciliation_checksum, strtolower( (string) $evidence['reconciliation_checksum'] ) )
\t\t\t|| ! hash_equals( $request_digest, strtolower( (string) $evidence['request_digest'] ) ) ) {
\t\t\treturn false;
\t\t}
\t\t$verified_at = ! empty( $evidence['verified_at_utc'] ) ? strtotime( (string) $evidence['verified_at_utc'] . ' UTC' ) : false;
\t\treturn false !== $verified_at && $verified_at >= time() - 15 * MINUTE_IN_SECONDS && $verified_at <= time() + 300;
\t}
"""
if old not in s:
    raise SystemExit('Round5 integration validator target missing')
s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
Path('tools/round5-third-audit-patch.py').unlink()
Path('.github/workflows/round5-third-audit-patch.yml').unlink()
