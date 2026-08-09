#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def patch(path,old,new):
 p=Path(path);s=p.read_text(encoding='utf-8')
 if old not in s: raise SystemExit(f'R{r} target missing in {path}: {old[:120]!r}')
 p.write_text(s.replace(old,new,1),encoding='utf-8')
if r==61:
 patch('includes/class-snfla-rest.php',"""register_rest_route( self::NS, '/fallback/open', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'open_fallback' ), 'args' => array_merge( self::state_args(), array( 'minutes' => array( 'type' => 'integer', 'default' => 30, 'minimum' => 5, 'maximum' => 1440 ) ) ) ) );""",
"""register_rest_route( self::NS, '/fallback/open', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'open_fallback' ), 'args' => array_merge( self::state_args(), array( 'hours' => array( 'type' => 'integer', 'default' => 24, 'minimum' => 1, 'maximum' => 168 ) ) ) ) );""")
 patch('includes/class-snfla-rest.php',"SNFLA_Redirects::open_fallback( $actor, $request->get_param( 'minutes' ),","SNFLA_Redirects::open_fallback( $actor, $request->get_param( 'hours' ),")
elif r==62:
 patch('includes/class-snfla-rest.php',"SNFLA_Reconciliation::cutover( $actor,","SNFLA_Reconciliation::approve_cutover( $actor,")
elif r==63:
 path='includes/class-snfla-reconciliation.php'
 patch(path,"""\tpublic static function run( $actor_id, $expected_state, $expected_version ) {
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""",
"""\tpublic static function run( $actor_id, $expected_state, $expected_version ) {
\t\tif ( ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_reconciliation_version_invalid', 'Lifecycle version must be a canonical positive integer.', array( 'status' => 400 ) ); }
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {""")
 patch(path,"SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) )","SNFLA_Schema::assert_current( sanitize_key( $expected_state ), $expected_version )")
 patch(path,"SNFLA_Schema::transition( 'reconciliation', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id","SNFLA_Schema::transition( 'reconciliation', sanitize_key( $expected_state ), $expected_version, $actor_id")
elif r==64:
 path='includes/class-snfla-reconciliation.php'
 patch(path,"""\tpublic static function approve_cutover( $actor_id, $expected_state, $expected_version ) {
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) )""",
"""\tpublic static function approve_cutover( $actor_id, $expected_state, $expected_version ) {
\t\tif ( ! is_int( $expected_version ) || $expected_version < 1 ) { return new WP_Error( 'snfla_cutover_version_invalid', 'Lifecycle version must be a canonical positive integer.', array( 'status' => 400 ) ); }
\t\tif ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) )""")
 # Harden all cutover-local state version coercions in the remainder by exact text replacements.
 p=Path(path);s=p.read_text(encoding='utf-8')
 s=s.replace("SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) )","SNFLA_Schema::assert_current( sanitize_key( $expected_state ), $expected_version )")
 s=s.replace("SNFLA_Schema::transition( 'redirect_cutover', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id","SNFLA_Schema::transition( 'redirect_cutover', sanitize_key( $expected_state ), $expected_version, $actor_id")
 p.write_text(s,encoding='utf-8')
 Path('tools/r61-r64-runtime.py').unlink();Path('.github/workflows/r61-r64-runtime.yml').unlink()
else: raise SystemExit('unsupported')
