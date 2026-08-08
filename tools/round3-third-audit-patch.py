from pathlib import Path

p=Path('includes/class-snfla-rest.php')
s=p.read_text(encoding='utf-8')
old="""\tprivate static function idempotency_key( WP_REST_Request $request ) {
\t\t$header = trim( sanitize_text_field( (string) $request->get_header( 'Idempotency-Key' ) ) );
\t\t$body   = trim( sanitize_text_field( (string) $request->get_param( 'idempotency_key' ) ) );
\t\tif ( '' !== $header && '' !== $body && ! hash_equals( $header, $body ) ) {
\t\t\treturn new WP_Error( 'snfla_idempotency_key_mismatch', 'The Idempotency-Key header and request field do not match.', array( 'status' => 400 ) );
\t\t}
\t\t$key = '' !== $header ? $header : $body;
\t\t$length = strlen( $key );
\t\tif ( $length < 16 || $length > 190 || preg_match( '/[\\x00-\\x1F\\x7F]/', $key ) ) {
\t\t\treturn new WP_Error( 'snfla_invalid_idempotency_key', 'A stable printable idempotency key of 16–190 characters is required.', array( 'status' => 400 ) );
\t\t}
\t\treturn $key;
\t}
"""
new="""\tprivate static function idempotency_key( WP_REST_Request $request ) {
\t\t// Idempotency keys are opaque security tokens, not human text. Sanitizing
\t\t// them can normalize distinct raw keys into one value and make unrelated
\t\t// requests share an operation ledger entry. Accept exact printable ASCII
\t\t// bytes only, compare them byte-for-byte, and hash that exact accepted key.
\t\t$header = trim( (string) $request->get_header( 'Idempotency-Key' ) );
\t\t$body   = trim( (string) $request->get_param( 'idempotency_key' ) );
\t\tif ( '' !== $header && '' !== $body && ! hash_equals( $header, $body ) ) {
\t\t\treturn new WP_Error( 'snfla_idempotency_key_mismatch', 'The Idempotency-Key header and request field do not match.', array( 'status' => 400 ) );
\t\t}
\t\t$key = '' !== $header ? $header : $body;
\t\t$length = strlen( $key );
\t\tif ( $length < 16 || $length > 190 || 1 !== preg_match( '/^[!-~]+$/D', $key ) ) {
\t\t\treturn new WP_Error( 'snfla_invalid_idempotency_key', 'A stable ASCII idempotency key of 16–190 printable non-space characters is required.', array( 'status' => 400 ) );
\t\t}
\t\treturn $key;
\t}
"""
if old not in s:
    raise SystemExit('Round3 idempotency function target not found')
p.write_text(s.replace(old,new,1),encoding='utf-8')
Path('tools/round3-third-audit-patch.py').unlink()
Path('.github/workflows/round3-third-audit-patch.yml').unlink()
