from pathlib import Path

def patch(path, old, new):
    p=Path(path); s=p.read_text()
    if old not in s:
        raise SystemExit(f'round8 target missing in {path}: {old[:100]!r}')
    p.write_text(s.replace(old,new,1))

# Media/reference provider evidence must be bound to the exact current source/request.
plan='includes/class-snfla-plan-completion.php'
patch(plan,
"""\t\t$request = array(
\t\t\t'file_number' => '04',
\t\t\t'legacy_id' => $legacy_id,
\t\t\t'references' => $refs,
\t\t\t'required_assertions' => array( 'ownership_verified', 'rights_or_license_verified', 'alt_policy_verified', 'duplicate_hash_checked', 'broken_links_zero', 'canonical_target_contract' ),
\t\t);
\t\t$evidence = apply_filters( 'sabri_file21_legacy_media_preflight_v1', array( 'verified' => false ), $request );
\t\tif ( ! is_array( $evidence ) || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) || empty( $evidence['ownership_verified'] ) || empty( $evidence['rights_or_license_verified'] ) || empty( $evidence['alt_policy_verified'] ) || empty( $evidence['duplicate_hash_checked'] ) || ! array_key_exists( 'broken_links', $evidence ) || 0 !== absint( $evidence['broken_links'] ) ) {""",
"""\t\t$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
\t\tif ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $source_signature ) || ! SNFLA_Inventory::unchanged() ) {
\t\t\treturn new WP_Error( 'snfla_media_source_signature_invalid', 'A current locked legacy source signature is required for media/reference attestation.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
\t\t}
\t\t$request = array(
\t\t\t'file_number' => '04',
\t\t\t'legacy_id' => $legacy_id,
\t\t\t'references' => $refs,
\t\t\t'required_assertions' => array( 'ownership_verified', 'rights_or_license_verified', 'alt_policy_verified', 'duplicate_hash_checked', 'broken_links_zero', 'canonical_target_contract' ),
\t\t\t'source_signature' => $source_signature,
\t\t);
\t\t$request['request_digest'] = SNFLA_Checksum::hash( $request );
\t\t$evidence = apply_filters( 'sabri_file21_legacy_media_preflight_v1', array( 'verified' => false ), $request );
\t\t$provider_bound = is_array( $evidence ) && ! empty( $evidence['source_signature'] ) && ! empty( $evidence['request_digest'] ) && hash_equals( $source_signature, (string) $evidence['source_signature'] ) && hash_equals( (string) $request['request_digest'], (string) $evidence['request_digest'] );
\t\tif ( ! is_array( $evidence ) || ! $provider_bound || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) || empty( $evidence['ownership_verified'] ) || empty( $evidence['rights_or_license_verified'] ) || empty( $evidence['alt_policy_verified'] ) || empty( $evidence['duplicate_hash_checked'] ) || ! array_key_exists( 'broken_links', $evidence ) || 0 !== absint( $evidence['broken_links'] ) ) {""")

patch(plan,
"""\t\t\t'reference_count' => count( $refs ),
\t\t\t'evidence' => SNFLA_Audit::redact( $evidence ),""",
"""\t\t\t'reference_count' => count( $refs ),
\t\t\t'source_signature' => $source_signature,
\t\t\t'request_digest' => $request['request_digest'],
\t\t\t'evidence' => SNFLA_Audit::redact( $evidence ),""")

patch(plan,
"""\t\t\t$verify = apply_filters(
\t\t\t\t'sabri_file21_verify_migrated_legacy_media_v1',
\t\t\t\tarray( 'verified' => false ),
\t\t\t\tarray( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'references' => $preflight['references'], 'provider_id' => $preflight['provider_id'] )
\t\t\t);
\t\t\tif ( ! is_array( $verify ) || empty( $verify['verified'] ) || empty( $verify['provider_id'] ) || absint( $verify['target_id'] ?? 0 ) !== $target_id || absint( $verify['verified_reference_count'] ?? -1 ) !== absint( $preflight['reference_count'] ) ) {""",
"""\t\t\t$verify_request = array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'references' => $preflight['references'], 'provider_id' => $preflight['provider_id'], 'source_signature' => (string) ( $preflight['source_signature'] ?? '' ), 'preflight_request_digest' => (string) ( $preflight['request_digest'] ?? '' ) );
\t\t\t$verify_request['request_digest'] = SNFLA_Checksum::hash( $verify_request );
\t\t\t$verify = apply_filters(
\t\t\t\t'sabri_file21_verify_migrated_legacy_media_v1',
\t\t\t\tarray( 'verified' => false ),
\t\t\t\t$verify_request
\t\t\t);
\t\t\t$verify_bound = is_array( $verify ) && ! empty( $verify['source_signature'] ) && ! empty( $verify['request_digest'] ) && hash_equals( (string) $verify_request['source_signature'], (string) $verify['source_signature'] ) && hash_equals( (string) $verify_request['request_digest'], (string) $verify['request_digest'] );
\t\t\tif ( ! is_array( $verify ) || ! $verify_bound || empty( $verify['verified'] ) || empty( $verify['provider_id'] ) || absint( $verify['target_id'] ?? 0 ) !== $target_id || absint( $verify['verified_reference_count'] ?? -1 ) !== absint( $preflight['reference_count'] ) ) {""")

# Canary may not approve a rollout phase when there are zero real observability samples.
future='includes/class-snfla-future18.php'
patch(future,
"""\t\t$error_rate = isset( $metrics['error_rate'] ) && is_numeric( $metrics['error_rate'] ) ? (float) $metrics['error_rate'] : null;
\t\t$blockers = array();""",
"""\t\t$sample_count = absint( $metrics['sample_count'] ?? 0 );
\t\t$error_rate = $sample_count > 0 && isset( $metrics['error_rate'] ) && is_numeric( $metrics['error_rate'] ) ? (float) $metrics['error_rate'] : null;
\t\t$blockers = array();
\t\tif ( 0 === $sample_count ) { $blockers[] = 'observability_samples_missing'; }""")

# Verified GameDay cannot report success if evidence persistence or audit fails.
patch(future,
"""\t\tif ( $verified ) { update_option( self::GAMEDAY_OPTION, SNFLA_Integrity::sign_evidence( $result ), false ); SNFLA_Audit::record( 'future18_gameday_verified', $actor_id, array( 'provider_id_digest' => hash( 'sha256', (string) $evidence['provider_id'] ) ), 'future18-gameday:' . gmdate( 'Ymd' ) ); }
\t\treturn $result;""",
"""\t\tif ( $verified ) {
\t\t\t$previous = get_option( self::GAMEDAY_OPTION, array() );
\t\t\t$signed = SNFLA_Integrity::sign_evidence( $result );
\t\t\tif ( ! update_option( self::GAMEDAY_OPTION, $signed, false ) && get_option( self::GAMEDAY_OPTION, array() ) !== $signed ) {
\t\t\t\treturn new WP_Error( 'snfla_gameday_persist_failed', 'GameDay evidence could not be persisted; verification remains blocked.', array( 'status' => 500 ) );
\t\t\t}
\t\t\tif ( ! SNFLA_Audit::record( 'future18_gameday_verified', $actor_id, array( 'provider_id_digest' => hash( 'sha256', (string) $evidence['provider_id'] ), 'source_signature' => $source_signature, 'request_digest' => $request['request_digest'] ), 'future18-gameday:' . $request['request_digest'] ) ) {
\t\t\t\tupdate_option( self::GAMEDAY_OPTION, $previous, false );
\t\t\t\treturn new WP_Error( 'snfla_gameday_audit_failed', 'GameDay evidence was reverted because audit evidence could not be persisted.', array( 'status' => 500 ) );
\t\t\t}
\t\t}
\t\treturn $result;""")

# Redirect/citation evidence cannot remain verified if its signed summary/audit is not durable.
hard='includes/class-snfla-post-audit-hardening.php'
patch(hard,
"""\t\tupdate_option( self::REDIRECT_EVIDENCE_OPTION, SNFLA_Integrity::sign_evidence( $summary ), false );
\t\treturn $evidence;""",
"""\t\t$previous = get_option( self::REDIRECT_EVIDENCE_OPTION, array() );
\t\t$signed = SNFLA_Integrity::sign_evidence( $summary );
\t\t$persisted = update_option( self::REDIRECT_EVIDENCE_OPTION, $signed, false ) || get_option( self::REDIRECT_EVIDENCE_OPTION, array() ) === $signed;
\t\tif ( ! $persisted ) {
\t\t\t$evidence['verified'] = false;
\t\t\t$evidence['hardening_validation']['persistence_failed'] = true;
\t\t\treturn $evidence;
\t\t}
\t\tif ( ! SNFLA_Audit::record( 'future18_redirect_observatory_verified', get_current_user_id(), array( 'source_signature' => (string) ( $summary['source_signature'] ?? '' ), 'request_digest' => (string) ( $summary['request_digest'] ?? '' ), 'verified' => (bool) $valid ), 'future18-redirect:' . (string) ( $summary['request_digest'] ?? '' ) ) ) {
\t\t\tupdate_option( self::REDIRECT_EVIDENCE_OPTION, $previous, false );
\t\t\t$evidence['verified'] = false;
\t\t\t$evidence['hardening_validation']['audit_persistence_failed'] = true;
\t\t}
\t\treturn $evidence;""")

Path('tools/round8-selfpatch.py').unlink()
Path('.github/workflows/round8-selfpatch.yml').unlink()
