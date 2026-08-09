#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def patch(path,old,new):
 p=Path(path);s=p.read_text(encoding='utf-8')
 if old not in s: raise SystemExit(f'R{r} target missing in {path}: {old[:120]!r}')
 p.write_text(s.replace(old,new,1),encoding='utf-8')
if r==56:
 path='includes/class-snfla-rest.php'
 patch(path,"""\t\tregister_rest_route( self::NS, '/backup-proof', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'backup_proof' ), 'args' => array_merge( self::state_args(), array( 'backup_id' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'restore_id' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'snapshot_checksum' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ), 'environment' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ) ) ) ) );""",
"""\t\tregister_rest_route( self::NS, '/backup-proof', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'backup_proof' ), 'args' => array(
\t\t\t'reference' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
\t\t\t'checksum' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
\t\t\t'created_at_utc' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
\t\t\t'restore_reference' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
\t\t\t'restore_checksum' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
\t\t\t'restored_at_utc' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
\t\t\t'restored_source_signature' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
\t\t\t'restored_post_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ),
\t\t\t'restored_comment_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ),
\t\t\t'restored_table_counts' => array( 'required' => true, 'type' => 'object' ),
\t\t) ) );""")
 patch(path,"""\tpublic static function backup_proof( WP_REST_Request $request ) {
\t\t$actor = self::can_run( $request );
\t\tif ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
\t\treturn self::result( 'snfla_backup_proof_recorded', SNFLA_Migration::record_backup_proof( $actor, $request->get_param( 'backup_id' ), $request->get_param( 'restore_id' ), $request->get_param( 'snapshot_checksum' ), $request->get_param( 'environment' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
\t}""",
"""\tpublic static function backup_proof( WP_REST_Request $request ) {
\t\t$actor = self::can_run( $request );
\t\tif ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
\t\t$evidence=array(); foreach(array('reference','checksum','created_at_utc','restore_reference','restore_checksum','restored_at_utc','restored_source_signature','restored_post_count','restored_comment_count','restored_table_counts') as $key){$evidence[$key]=$request->get_param($key);} 
\t\treturn self::result( 'snfla_backup_proof_recorded', SNFLA_Migration::record_backup_proof( $actor, $evidence ) );
\t}""")
elif r==57:
 path='includes/class-snfla-cli.php'
 patch(path,"""\tpublic function status() {
\t\t$request = new WP_REST_Request( 'GET', '/' . SNFLA_REST::NAMESPACE . '/status' );
\t\t$response = SNFLA_REST::status( $request );""",
"""\tpublic function status() {
\t\t$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
\t\tif ( is_wp_error( $actor ) ) { WP_CLI::error( $actor->get_error_code() . ': ' . $actor->get_error_message() ); }
\t\t$request = new WP_REST_Request( 'GET', '/' . SNFLA_REST::NAMESPACE . '/status' );
\t\t$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
\t\t$response = SNFLA_REST::status( $request );""")
elif r==58:
 path='includes/class-snfla-cli.php';p=Path(path);s=p.read_text(encoding='utf-8')
 # Introduce strict helper and replace all assoc expected-version fallbacks.
 needle="\tprivate function actor( $capability ) {"
 helper="""\tprivate function expected_version( array $assoc ) {
\t\tif ( ! isset( $assoc['expected-version'] ) ) { return SNFLA_Schema::version(); }
\t\t$raw=(string)$assoc['expected-version']; if ( 1!==preg_match('/^[1-9][0-9]*$/D',$raw) ) { WP_CLI::error('snfla_invalid_expected_version: expected-version must be a positive decimal integer.'); }
\t\t$value=(int)$raw; if($value<=0 || (string)$value!==$raw){ WP_CLI::error('snfla_invalid_expected_version'); } return $value;
\t}

"""
 if needle not in s: raise SystemExit('R58 helper target missing')
 s=s.replace(needle,helper+needle,1)
 s=s.replace("$assoc['expected-version'] ?? SNFLA_Schema::version()","$this->expected_version( $assoc )")
 p.write_text(s,encoding='utf-8')
elif r==59:
 path='includes/class-snfla-redirects.php'
 patch(path,"""\tpublic static function open_fallback( $actor_id, $hours, $expected_state, $expected_version ) {
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""",
"""\tpublic static function open_fallback( $actor_id, $hours, $expected_state, $expected_version ) {
\t\tif ( ! is_int( $hours ) || $hours < 1 || $hours > 168 || ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_fallback_parameters_invalid', 'Fallback hours and lifecycle version must be canonical bounded integers.', array( 'status' => 400 ) ); }
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""")
 patch(path,"SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) )","SNFLA_Schema::assert_current( sanitize_key( $expected_state ), $expected_version )")
 patch(path,"$hours = min( 168, max( 1, absint( $hours ) ) );","$hours = $hours;")
 patch(path,"SNFLA_Schema::transition( 'read_only_fallback', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id","SNFLA_Schema::transition( 'read_only_fallback', sanitize_key( $expected_state ), $expected_version, $actor_id")
elif r==60:
 path='includes/class-snfla-migration.php'
 patch(path,"""\tpublic static function dry_run( $actor_id, $limit = 500, $expected_state = 'inventory_locked', $expected_version = 1 ) {
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""",
"""\tpublic static function dry_run( $actor_id, $limit = 500, $expected_state = 'inventory_locked', $expected_version = 1 ) {
\t\tif ( ! is_int( $limit ) || $limit < 50 || $limit > 1000 || ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_dry_run_parameters_invalid', 'Dry-run limit and lifecycle version must be canonical bounded integers.', array( 'status' => 400 ) ); }
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""")
 patch(path,"SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) )","SNFLA_Schema::assert_current( sanitize_key( $expected_state ), $expected_version )")
 patch(path,"$batch_size = min( 1000, max( 50, absint( $limit ) ) );","$batch_size = $limit;")
 patch(path,"SNFLA_Schema::transition( 'dry_run_ready', $expected_state, absint( $expected_version ), $actor_id","SNFLA_Schema::transition( 'dry_run_ready', $expected_state, $expected_version, $actor_id")
 Path('tools/r56-r60-boundaries.py').unlink();Path('.github/workflows/r56-r60-boundaries.yml').unlink()
else: raise SystemExit('unsupported')
