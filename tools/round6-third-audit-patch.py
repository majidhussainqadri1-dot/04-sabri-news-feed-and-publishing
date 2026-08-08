from pathlib import Path
p=Path('includes/class-snfla-retirement.php')
s=p.read_text(encoding='utf-8')
old="""\t\t\t\t$request = array(
\t\t\t\t\t'schema'           => 1,
\t\t\t\t\t'batch_number'     => $batch_no,
\t\t\t\t\t'batch_count'      => count( $entries ),
\t\t\t\t\t'batch_checksum'   => $batch_checksum,
\t\t\t\t\t'previous_checksum'=> $chain,
\t\t\t\t\t'source_signature' => $source_signature,
\t\t\t\t\t'entries'          => $entries,
\t\t\t\t);
\t\t\t\t$response = apply_filters( 'snfla_retirement_redirect_handoff_batch', array( 'verified' => false ), $request );
\t\t\t\tif ( ! is_array( $response ) || empty( $response['verified'] ) || absint( $response['accepted_count'] ?? 0 ) !== count( $entries ) || ! hash_equals( $batch_checksum, (string) ( $response['batch_checksum'] ?? '' ) ) || '' === sanitize_text_field( (string) ( $response['provider_id'] ?? '' ) ) ) {
"""
new="""\t\t\t\t$request = array(
\t\t\t\t\t'schema'           => 2,
\t\t\t\t\t'batch_number'     => $batch_no,
\t\t\t\t\t'batch_count'      => count( $entries ),
\t\t\t\t\t'batch_checksum'   => $batch_checksum,
\t\t\t\t\t'previous_checksum'=> $chain,
\t\t\t\t\t'source_signature' => $source_signature,
\t\t\t\t\t'entries'          => $entries,
\t\t\t\t);
\t\t\t\t$request['request_digest'] = SNFLA_Checksum::hash( $request );
\t\t\t\t$response = apply_filters( 'snfla_retirement_redirect_handoff_batch', array( 'verified' => false ), $request );
\t\t\t\t$verified_at = is_array( $response ) && ! empty( $response['verified_at_utc'] ) ? strtotime( (string) $response['verified_at_utc'] . ' UTC' ) : false;
\t\t\t\tif ( ! is_array( $response ) || empty( $response['verified'] )
\t\t\t\t\t|| absint( $response['accepted_count'] ?? 0 ) !== count( $entries )
\t\t\t\t\t|| ! hash_equals( $batch_checksum, (string) ( $response['batch_checksum'] ?? '' ) )
\t\t\t\t\t|| ! hash_equals( $source_signature, (string) ( $response['source_signature'] ?? '' ) )
\t\t\t\t\t|| ! hash_equals( $chain, (string) ( $response['previous_checksum'] ?? '' ) )
\t\t\t\t\t|| ! hash_equals( (string) $request['request_digest'], (string) ( $response['request_digest'] ?? '' ) )
\t\t\t\t\t|| false === $verified_at || $verified_at < time() - 15 * MINUTE_IN_SECONDS || $verified_at > time() + 300
\t\t\t\t\t|| '' === sanitize_text_field( (string) ( $response['provider_id'] ?? '' ) ) ) {
"""
if old not in s: raise SystemExit('round6 batch target missing')
s=s.replace(old,new,1)
old2="""\t\t$summary = array(
\t\t\t'schema'            => 1,
\t\t\t'provider_id'       => $provider_id,
\t\t\t'source_signature'  => $source_signature,
\t\t\t'manifest_checksum' => $chain,
\t\t\t'count'             => absint( $stream['count'] ?? 0 ),
\t\t\t'redirect_count'    => absint( $stream['redirect_count'] ?? 0 ),
\t\t\t'gone_count'        => absint( $stream['gone_count'] ?? 0 ),
\t\t\t'batch_count'       => $batch_no,
\t\t);
\t\t$response = apply_filters( 'snfla_verify_retirement_redirect_handoff', array( 'verified' => false ), $summary );
"""
new2="""\t\t$summary = array(
\t\t\t'schema'            => 2,
\t\t\t'provider_id'       => $provider_id,
\t\t\t'source_signature'  => $source_signature,
\t\t\t'manifest_checksum' => $chain,
\t\t\t'count'             => absint( $stream['count'] ?? 0 ),
\t\t\t'redirect_count'    => absint( $stream['redirect_count'] ?? 0 ),
\t\t\t'gone_count'        => absint( $stream['gone_count'] ?? 0 ),
\t\t\t'batch_count'       => $batch_no,
\t\t);
\t\t$summary['request_digest'] = SNFLA_Checksum::hash( $summary );
\t\t$response = apply_filters( 'snfla_verify_retirement_redirect_handoff', array( 'verified' => false ), $summary );
"""
if old2 not in s: raise SystemExit('round6 summary target missing')
s=s.replace(old2,new2,1)
old3="""\t\tif ( ! is_array( $response ) || empty( $response['verified'] ) || 'completed' !== sanitize_key( (string) ( $response['status'] ?? '' ) ) || '' === $provider_id || ! hash_equals( $provider_id, $response_provider ) || ! hash_equals( $source_signature, (string) ( $response['source_signature'] ?? '' ) ) || ! hash_equals( $chain, (string) ( $response['manifest_checksum'] ?? '' ) ) || absint( $response['count'] ?? 0 ) !== $summary['count'] ) {
"""
new3="""\t\t$summary_verified_at = is_array( $response ) && ! empty( $response['verified_at_utc'] ) ? strtotime( (string) $response['verified_at_utc'] . ' UTC' ) : false;
\t\tif ( ! is_array( $response ) || empty( $response['verified'] ) || 'completed' !== sanitize_key( (string) ( $response['status'] ?? '' ) ) || '' === $provider_id || ! hash_equals( $provider_id, $response_provider ) || ! hash_equals( $source_signature, (string) ( $response['source_signature'] ?? '' ) ) || ! hash_equals( $chain, (string) ( $response['manifest_checksum'] ?? '' ) ) || ! hash_equals( (string) $summary['request_digest'], (string) ( $response['request_digest'] ?? '' ) ) || absint( $response['count'] ?? 0 ) !== $summary['count'] || false === $summary_verified_at || $summary_verified_at < time() - 15 * MINUTE_IN_SECONDS || $summary_verified_at > time() + 300 ) {
"""
if old3 not in s: raise SystemExit('round6 final verifier target missing')
s=s.replace(old3,new3,1)
p.write_text(s,encoding='utf-8')
Path('tools/round6-third-audit-patch.py').unlink()
Path('.github/workflows/round6-third-audit-patch.yml').unlink()
