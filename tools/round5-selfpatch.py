from pathlib import Path
import re

p = Path('includes/class-snfla-future18.php')
s = p.read_text()

old = "\t\tself::route( '/future/contract-drift', WP_REST_Server::READABLE, 'rest_contract_drift', $read );"
new = old + "\n\t\tself::route( '/future/contract-baseline', WP_REST_Server::CREATABLE, 'rest_contract_baseline', $act );"
if old not in s:
    raise SystemExit('round5 route target missing')
s = s.replace(old, new, 1)

old = "\tpublic static function rest_contract_drift( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_contract_drift', self::contract_drift() ); }"
new = old + "\n\tpublic static function rest_contract_baseline( WP_REST_Request $request ) { unset( $request ); return self::response( 'future18_contract_baseline', self::record_contract_baseline( get_current_user_id() ) ); }"
if old not in s:
    raise SystemExit('round5 rest method target missing')
s = s.replace(old, new, 1)

pattern = re.compile(r"\t/\*\* F04-FUT-002 — version/fingerprint drift detection across canonical contracts\. \*/\n\tpublic static function contract_drift\(\) \{.*?\n\t\}\n\n\t/\*\* F04-FUT-003", re.S)
replacement = '''\t/** F04-FUT-002 — version/fingerprint drift detection across canonical contracts. */
\tprivate static function contract_snapshot() {
\t\t$manifest = SNFLA_Central_Plan::module_manifest();
\t\t$descriptors = array(
\t\t\t'File 00' => apply_filters( 'sabri_file00_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'identity_authority' ) ),
\t\t\t'File 21' => apply_filters( 'sabri_file21_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'canonical_publication_migration' ) ),
\t\t\t'File 26' => apply_filters( 'sabri_file26_contract_descriptor_v1', array( 'verified' => false, 'status' => 'unavailable' ), array( 'consumer' => 'File 04', 'purpose' => 'legacy_resolution_search_handoff' ) ),
\t\t);
\t\t$normalized = SNFLA_Checksum::canonicalize( SNFLA_Audit::redact( $descriptors ) );
\t\t$unverified = array();
\t\tforeach ( $descriptors as $owner => $descriptor ) {
\t\t\tif ( ! is_array( $descriptor ) || empty( $descriptor['verified'] ) ) { $unverified[] = $owner; }
\t\t}
\t\treturn array(
\t\t\t'fingerprint' => SNFLA_Checksum::hash( array( 'manifest' => $manifest, 'contracts' => $normalized ) ),
\t\t\t'descriptors' => $normalized,
\t\t\t'unverified_contracts' => $unverified,
\t\t\t'source_signature' => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ),
\t\t);
\t}

\tpublic static function contract_drift() {
\t\t$snapshot = self::contract_snapshot();
\t\t$baseline = get_option( self::DRIFT_OPTION, array() );
\t\t$signature = (string) $snapshot['source_signature'];
\t\t$baseline_valid = is_array( $baseline )
\t\t\t&& SNFLA_Integrity::evidence_valid( $baseline )
\t\t\t&& ! empty( $baseline['fingerprint'] )
\t\t\t&& 1 === preg_match( '/^[a-f0-9]{64}$/', $signature )
\t\t\t&& hash_equals( $signature, (string) ( $baseline['source_signature'] ?? '' ) );
\t\t$changed = $baseline_valid && ! hash_equals( (string) $baseline['fingerprint'], (string) $snapshot['fingerprint'] );
\t\t$result = array(
\t\t\t'feature_id' => 'F04-FUT-002',
\t\t\t'checked_at_utc' => gmdate( 'Y-m-d H:i:s' ),
\t\t\t'fingerprint' => $snapshot['fingerprint'],
\t\t\t'baseline_fingerprint' => $baseline_valid ? (string) $baseline['fingerprint'] : '',
\t\t\t'baseline_valid' => $baseline_valid,
\t\t\t'baseline_missing_or_invalid' => ! $baseline_valid,
\t\t\t'changed_since_verified_baseline' => $changed,
\t\t\t'unverified_contracts' => $snapshot['unverified_contracts'],
\t\t\t'block_mutation' => ! $baseline_valid || $changed || ! empty( $snapshot['unverified_contracts'] ),
\t\t\t'descriptors' => $snapshot['descriptors'],
\t\t);
\t\treturn $result;
\t}

\tpublic static function record_contract_baseline( $actor_id ) {
\t\t$actor_id = absint( $actor_id );
\t\t$authorized = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
\t\tif ( is_wp_error( $authorized ) ) { return $authorized; }
\t\tif ( ! SNFLA_Inventory::unchanged() ) {
\t\t\treturn new WP_Error( 'snfla_contract_baseline_source_changed', 'The locked source must be current before recording a contract baseline.', array( 'status' => 409 ) );
\t\t}
\t\t$snapshot = self::contract_snapshot();
\t\tif ( ! empty( $snapshot['unverified_contracts'] ) ) {
\t\t\treturn new WP_Error( 'snfla_contract_baseline_unverified', 'Every canonical dependency contract must be verified before a baseline can be recorded.', array( 'status' => 412, 'unverified_contracts' => $snapshot['unverified_contracts'] ) );
\t\t}
\t\tif ( 1 !== preg_match( '/^[a-f0-9]{64}$/', (string) $snapshot['source_signature'] ) ) {
\t\t\treturn new WP_Error( 'snfla_contract_baseline_source_invalid', 'A cryptographically valid locked source signature is required.', array( 'status' => 412 ) );
\t\t}
\t\t$baseline = SNFLA_Integrity::sign_evidence( array(
\t\t\t'schema' => 2,
\t\t\t'feature_id' => 'F04-FUT-002',
\t\t\t'fingerprint' => $snapshot['fingerprint'],
\t\t\t'source_signature' => $snapshot['source_signature'],
\t\t\t'descriptors' => $snapshot['descriptors'],
\t\t\t'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ),
\t\t\t'recorded_at_utc' => gmdate( 'Y-m-d H:i:s' ),
\t\t) );
\t\t$previous = get_option( self::DRIFT_OPTION, array() );
\t\tif ( ! update_option( self::DRIFT_OPTION, $baseline, false ) && get_option( self::DRIFT_OPTION, array() ) !== $baseline ) {
\t\t\treturn new WP_Error( 'snfla_contract_baseline_persist_failed', 'The verified contract baseline could not be persisted.', array( 'status' => 500 ) );
\t\t}
\t\tif ( ! SNFLA_Audit::record( 'future18_contract_baseline_recorded', $actor_id, array( 'fingerprint' => $snapshot['fingerprint'], 'source_signature' => $snapshot['source_signature'] ), 'future18-contract-baseline:' . $snapshot['fingerprint'] ) ) {
\t\t\tupdate_option( self::DRIFT_OPTION, $previous, false );
\t\t\treturn new WP_Error( 'snfla_contract_baseline_audit_failed', 'The contract baseline was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
\t\t}
\t\treturn array( 'recorded' => true, 'baseline' => SNFLA_Audit::redact( $baseline ), 'drift' => self::contract_drift() );
\t}

\t/** F04-FUT-003'''

s2, n = pattern.subn(replacement, s, count=1)
if n != 1:
    raise SystemExit(f'round5 contract block replacement count={n}')
p.write_text(s2)
Path('tools/round5-selfpatch.py').unlink()
Path('.github/workflows/round5-selfpatch.yml').unlink()
