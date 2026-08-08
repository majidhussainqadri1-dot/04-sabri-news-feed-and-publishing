from pathlib import Path

p=Path('includes/class-snfla-migration.php')
s=p.read_text(encoding='utf-8')
old="""\t\t$verification = apply_filters( 'snfla_verify_restore_evidence', array( 'verified' => false ), $verification_request, $locked );
\t\tif ( ! is_array( $verification ) || empty( $verification['verified'] ) || empty( $verification['verifier_id'] ) ) {
\t\t\treturn new WP_Error( 'snfla_restore_verifier_required', 'An approved staging restore-verifier adapter must independently verify the restore rehearsal.', array( 'status' => 412 ) );
\t\t}
\t\t$proof = SNFLA_Integrity::sign_evidence(
\t\t\tarray_merge(
\t\t\t\t$verification_request,
\t\t\t\tarray(
\t\t\t\t\t'verifier_id'       => sanitize_key( $verification['verifier_id'] ),
\t\t\t\t\t'verified_at_utc'   => sanitize_text_field( (string) ( $verification['verified_at_utc'] ?? gmdate( 'Y-m-d H:i:s' ) ) ),
\t\t\t\t\t'recorded_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
\t\t\t\t\t'actor_digest'      => SNFLA_Audit::actor_digest( $actor_id ),
\t\t\t\t\t'restore_verified'  => true,
\t\t\t\t)
\t\t\t)
\t\t);
"""
new="""\t\t$verification_request['request_digest'] = SNFLA_Checksum::hash( $verification_request );
\t\t$verification = apply_filters( 'snfla_verify_restore_evidence', array( 'verified' => false ), $verification_request, $locked );
\t\t$verified_at = is_array( $verification ) ? self::parse_utc_timestamp( $verification['verified_at_utc'] ?? '' ) : false;
\t\t$provider_bound = is_array( $verification )
\t\t\t&& ! empty( $verification['verified'] )
\t\t\t&& ! empty( $verification['verifier_id'] )
\t\t\t&& ! empty( $verification['source_signature'] )
\t\t\t&& ! empty( $verification['request_digest'] )
\t\t\t&& hash_equals( $source_signature, strtolower( (string) $verification['source_signature'] ) )
\t\t\t&& hash_equals( (string) $verification_request['request_digest'], strtolower( (string) $verification['request_digest'] ) )
\t\t\t&& false !== $verified_at
\t\t\t&& $verified_at >= time() - 15 * MINUTE_IN_SECONDS
\t\t\t&& $verified_at <= time() + 300;
\t\tif ( ! $provider_bound ) {
\t\t\treturn new WP_Error( 'snfla_restore_verifier_required', 'An approved staging restore-verifier must attest the exact current source-bound restore request and a fresh verification timestamp.', array( 'status' => 412 ) );
\t\t}
\t\t$proof = SNFLA_Integrity::sign_evidence(
\t\t\tarray_merge(
\t\t\t\t$verification_request,
\t\t\t\tarray(
\t\t\t\t\t'verifier_id'       => sanitize_key( $verification['verifier_id'] ),
\t\t\t\t\t'verified_at_utc'   => gmdate( 'Y-m-d H:i:s', $verified_at ),
\t\t\t\t\t'recorded_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
\t\t\t\t\t'actor_digest'      => SNFLA_Audit::actor_digest( $actor_id ),
\t\t\t\t\t'restore_verified'  => true,
\t\t\t\t)
\t\t\t)
\t\t);
"""
if old not in s:
    raise SystemExit('Round4 restore-verifier block not found')
p.write_text(s.replace(old,new,1),encoding='utf-8')
Path('tools/round4-third-audit-patch.py').unlink()
Path('.github/workflows/round4-third-audit-patch.yml').unlink()
