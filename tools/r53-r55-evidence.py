#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def patch(path,old,new):
 p=Path(path);s=p.read_text(encoding='utf-8')
 if old not in s: raise SystemExit(f'R{r} target missing in {path}: {old[:120]!r}')
 p.write_text(s.replace(old,new,1),encoding='utf-8')
if r==53:
 path='includes/class-snfla-rollback.php';p=Path(path);s=p.read_text(encoding='utf-8')
 needle="\tpublic static function proof() {"
 helper="""\tpublic static function proof_current() {
\t\t$proof = self::proof();
\t\tif ( ! SNFLA_Integrity::evidence_valid( $proof ) ) { return false; }
\t\t$locked = SNFLA_Inventory::locked();
\t\t$source_signature = strtolower( (string) ( $locked['source_signature'] ?? '' ) );
\t\t$proof_signature = strtolower( (string) ( $proof['source_signature'] ?? '' ) );
\t\t$performed = ! empty( $proof['performed_at_utc'] ) ? strtotime( (string) $proof['performed_at_utc'] . ' UTC' ) : false;
\t\t$run_uuid = (string) ( $proof['run_uuid'] ?? '' );
\t\treturn 1 === preg_match( '/^[a-f0-9]{64}$/D', $source_signature )
\t\t\t&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $proof_signature )
\t\t\t&& hash_equals( $source_signature, $proof_signature )
\t\t\t&& SNFLA_Inventory::unchanged()
\t\t\t&& false !== $performed && $performed >= time() - 7 * DAY_IN_SECONDS && $performed <= time() + 300
\t\t\t&& 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $run_uuid )
\t\t\t&& SNFLA_Audit::has_event( 'rollback_completed', '', 'run_uuid', $run_uuid );
\t}

"""
 if needle not in s: raise SystemExit('R53 insertion target missing')
 p.write_text(s.replace(needle,helper+needle,1),encoding='utf-8')
 patch('includes/class-snfla-future18.php',"'rollback_proof_valid' => SNFLA_Integrity::evidence_valid( $rollback ),","'rollback_proof_valid' => SNFLA_Rollback::proof_current(),")
elif r==54:
 path='includes/class-snfla-future18.php'
 old="""\t\t$gates = array(
\t\t\t'legacy_writes_disabled'"""
 new="""\t\t$gameday_time = is_array( $gameday ) && ! empty( $gameday['performed_at_utc'] ) ? strtotime( (string) $gameday['performed_at_utc'] . ' UTC' ) : false;
\t\t$gameday_signature = (string) ( $gameday['source_signature'] ?? '' );
\t\t$gameday_request = (string) ( $gameday['request_digest'] ?? '' );
\t\t$current_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
\t\t$gameday_current = is_array( $gameday ) && SNFLA_Integrity::evidence_valid( $gameday ) && ! empty( $gameday['verified'] )
\t\t\t&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $current_signature ) && hash_equals( $current_signature, $gameday_signature )
\t\t\t&& 1 === preg_match( '/^[a-f0-9]{64}$/D', $gameday_request )
\t\t\t&& false !== $gameday_time && $gameday_time >= time() - 7 * DAY_IN_SECONDS && $gameday_time <= time() + 300
\t\t\t&& SNFLA_Audit::has_event( 'future18_gameday_verified', 'future18-gameday:' . $gameday_request, 'source_signature', $current_signature );
\t\t$gates = array(
\t\t\t'legacy_writes_disabled'"""
 patch(path,old,new)
 patch(path,"""'disaster_recovery_gameday_verified' => is_array( $gameday ) && SNFLA_Integrity::evidence_valid( $gameday ) && ! empty( $gameday['verified'] ) && ! empty( $gameday['source_signature'] ) && hash_equals( (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ), (string) $gameday['source_signature'] ),""","""'disaster_recovery_gameday_verified' => $gameday_current,""")
elif r==55:
 path='includes/class-snfla-future18.php';p=Path(path);s=p.read_text(encoding='utf-8')
 old="""\tprivate static function contract_snapshot() {
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
\t\t}"""
 new="""\tprivate static function contract_snapshot() {
\t\t$manifest = SNFLA_Central_Plan::module_manifest();
\t\t$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
\t\t$manifest_digest = SNFLA_Checksum::hash( $manifest );
\t\t$requests = array(
\t\t\t'File 00' => array( 'consumer' => 'File 04', 'purpose' => 'identity_authority' ),
\t\t\t'File 21' => array( 'consumer' => 'File 04', 'purpose' => 'canonical_publication_migration' ),
\t\t\t'File 26' => array( 'consumer' => 'File 04', 'purpose' => 'legacy_resolution_search_handoff' ),
\t\t);
\t\t$filters = array( 'File 00' => 'sabri_file00_contract_descriptor_v1', 'File 21' => 'sabri_file21_contract_descriptor_v1', 'File 26' => 'sabri_file26_contract_descriptor_v1' );
\t\t$descriptors=array(); $unverified=array();
\t\tforeach ( $requests as $owner => $request ) {
\t\t\t$request['source_signature']=$source_signature; $request['manifest_digest']=$manifest_digest; $request['request_digest']=SNFLA_Checksum::hash($request);
\t\t\t$descriptor=apply_filters( $filters[$owner], array('verified'=>false,'status'=>'unavailable'), $request );
\t\t\t$verified_at=is_array($descriptor)&&!empty($descriptor['verified_at_utc'])?strtotime((string)$descriptor['verified_at_utc'].' UTC'):false;
\t\t\t$bound=is_array($descriptor)&&!empty($descriptor['verified'])&&!empty($descriptor['provider_id'])
\t\t\t\t&&hash_equals($source_signature,(string)($descriptor['source_signature']??''))&&hash_equals($manifest_digest,(string)($descriptor['manifest_digest']??''))&&hash_equals((string)$request['request_digest'],(string)($descriptor['request_digest']??''))
\t\t\t\t&&false!==$verified_at&&$verified_at>=time()-15*MINUTE_IN_SECONDS&&$verified_at<=time()+300;
\t\t\tif(!$bound){$unverified[]=$owner;} $descriptors[$owner]=$descriptor;
\t\t}
\t\t$normalized = SNFLA_Checksum::canonicalize( SNFLA_Audit::redact( $descriptors ) );"""
 if old not in s: raise SystemExit('R55 contract block target missing')
 s=s.replace(old,new,1)
 s=s.replace("'source_signature' => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ),","'source_signature' => $source_signature,",1)
 p.write_text(s,encoding='utf-8')
 Path('tools/r53-r55-evidence.py').unlink();Path('.github/workflows/r53-r55-evidence.yml').unlink()
else: raise SystemExit('unsupported')
