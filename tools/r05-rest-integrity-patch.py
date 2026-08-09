from pathlib import Path
p=Path('includes/class-snfla-rest.php')
s=p.read_text(encoding='utf-8')
old="""\tprivate static function integrity_status() {
\t\t$chain = SNFLA_Audit::verify_chain();
\t\t$last  = get_option( 'snfla_last_integrity_check', array() );
\t\treturn array( 'audit_chain' => array( 'valid' => ! empty( $chain['valid'] ), 'checked' => absint( $chain['checked'] ?? 0 ), 'error' => sanitize_key( (string) ( $chain['error'] ?? '' ) ) ), 'last_check' => is_array( $last ) ? array( 'checked_at_utc' => sanitize_text_field( (string) ( $last['checked_at_utc'] ?? '' ) ), 'ok' => ! empty( $last['ok'] ) ) : array() );
\t}"""
new="""\tprivate static function integrity_status() {
\t\t$chain = SNFLA_Audit::verify_chain();
\t\t$last  = get_option( 'snfla_last_integrity_check', array() );
\t\t$last_trusted = SNFLA_Integrity::evidence_valid( $last );
\t\treturn array(
\t\t\t'audit_chain' => array( 'valid' => ! empty( $chain['valid'] ), 'checked' => absint( $chain['checked'] ?? 0 ), 'error' => sanitize_key( (string) ( $chain['error'] ?? '' ) ) ),
\t\t\t'last_check'  => $last_trusted ? array( 'evidence_valid' => true, 'checked_at_utc' => sanitize_text_field( (string) ( $last['checked_at_utc'] ?? '' ) ), 'ok' => ! empty( $last['ok'] ) ) : array( 'evidence_valid' => false ),
\t\t);
\t}"""
if old not in s: raise SystemExit('R05 target missing')
p.write_text(s.replace(old,new,1),encoding='utf-8')
Path('tools/r05-rest-integrity-patch.py').unlink()
Path('.github/workflows/r05-rest-integrity-patch.yml').unlink()
