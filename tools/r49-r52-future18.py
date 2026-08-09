#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]);p=Path('includes/class-snfla-future18.php');s=p.read_text(encoding='utf-8')
def rep(old,new,count=1):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:120]!r}')
 s=s.replace(old,new,count)
if r==49:
 rep("""\t\t$ids  = array(
\t\t\t'legacy_ids' => array(
\t\t\t\t'required' => true,
\t\t\t\t'type' => 'array',
\t\t\t\t'items' => array( 'type' => 'integer' ),
\t\t\t\t'validate_callback' => static function ( $value ) { return is_array( $value ) && count( $value ) >= 1 && count( $value ) <= self::MAX_IDS; },
\t\t\t),
\t\t);""",
"""\t\t$ids  = array(
\t\t\t'legacy_ids' => array(
\t\t\t\t'required' => true,
\t\t\t\t'type' => 'array',
\t\t\t\t'items' => array( 'type' => 'integer', 'minimum' => 1 ),
\t\t\t\t'validate_callback' => static function ( $value ) {
\t\t\t\t\tif ( ! is_array( $value ) || count( $value ) < 1 || count( $value ) > self::MAX_IDS ) { return false; }
\t\t\t\t\t$seen=array(); foreach ( $value as $id ) { if ( ! is_int( $id ) || $id <= 0 || isset($seen[$id]) ) { return false; } $seen[$id]=true; } return true;
\t\t\t\t},
\t\t\t),
\t\t);""")
 rep("""\tprivate static function id_arg() {
\t\treturn array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' );
\t}""",
"""\tprivate static function id_arg() {
\t\treturn array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => static function( $value ){ return is_int($value) && $value>0 ? $value : 0; }, 'validate_callback' => static function( $value ){ return is_int($value) && $value>0; } );
\t}""")
elif r==50:
 rep("""\tpublic static function digital_twin( array $legacy_ids, $actor_id = 0 ) {
\t\t$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_IDS );
\t\tif ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_twin_empty', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }""",
"""\tpublic static function digital_twin( array $legacy_ids, $actor_id = 0 ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_IDS );
\t\tif ( is_wp_error( $legacy_ids ) || empty( $legacy_ids ) ) { return new WP_Error( 'snfla_twin_empty', 'Select positive, unique canonical legacy IDs.', array( 'status' => 400 ) ); }""")
elif r==51:
 rep("""\tpublic static function redirect_observatory( array $legacy_ids ) {
\t\t$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_IDS ); if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_redirect_observatory_empty', 'Select one or more legacy IDs.', array( 'status' => 400 ) ); }""",
"""\tpublic static function redirect_observatory( array $legacy_ids ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_IDS ); if ( is_wp_error( $legacy_ids ) || empty( $legacy_ids ) ) { return new WP_Error( 'snfla_redirect_observatory_empty', 'Select positive, unique canonical legacy IDs.', array( 'status' => 400 ) ); }""")
elif r==52:
 # Add one strict helper and harden all direct Future18 scalar ID coercions without changing non-ID absint uses.
 needle="\tprivate static function pair_args() {"
 helper="""\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed=(int)$value; return $parsed>0 && (string)$parsed===$value ? $parsed : 0;
\t}

"""
 rep(needle,helper+needle)
 replacements={
  '$legacy_id = absint( $legacy_id ); $target_id = absint( $target_id );':'$legacy_id = self::strict_positive_id( $legacy_id ); $target_id = self::strict_positive_id( $target_id );',
  '$legacy_id = absint( $legacy_id ); $post = get_post( $legacy_id );':'$legacy_id = self::strict_positive_id( $legacy_id ); $post = get_post( $legacy_id );',
  '$legacy_id = absint( $legacy_id );':'$legacy_id = self::strict_positive_id( $legacy_id );',
 }
 for old,new in replacements.items(): s=s.replace(old,new)
 # Mutation actor identity must not alias malformed internal callers.
 s=s.replace('$actor_id = absint( $actor_id );','$actor_id = self::strict_positive_id( $actor_id );')
 Path('tools/r49-r52-future18.py').unlink();Path('.github/workflows/r49-r52-future18.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
