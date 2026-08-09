#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1]);p=Path('includes/class-snfla-file21-adapter.php');s=p.read_text(encoding='utf-8')
def rep(old,new):
 global s
 if old not in s: raise SystemExit(f'R{r} target missing: {old[:120]!r}')
 s=s.replace(old,new,1)
if r==41:
 rep("""\t\treturn \\Sabri\\HomeNewsFeed\\LegacyPublicationRollback::rollback_selected( $legacy_ids, absint( $actor_id ) );
\t}""",
"""\t\t$result = \\Sabri\\HomeNewsFeed\\LegacyPublicationRollback::rollback_selected( $legacy_ids, $actor_id );
\t\tif ( is_wp_error( $result ) ) { return $result; }
\t\tif ( ! is_array( $result ) ) { return new WP_Error( 'snfla_file21_rollback_result_invalid', 'File 21 rollback returned an invalid result envelope.' ); }
\t\t$allowed = array_fill_keys( $legacy_ids, true );
\t\t$rolled = isset( $result['rolled_back'] ) && is_array( $result['rolled_back'] ) ? $result['rolled_back'] : array();
\t\tforeach ( $rolled as $raw_legacy_id => $row ) {
\t\t\t$legacy_id = self::strict_positive_id( $raw_legacy_id );
\t\t\t$target_id = is_array( $row ) ? self::strict_positive_id( $row['target_id'] ?? 0 ) : 0;
\t\t\tif ( $legacy_id <= 0 || ! isset( $allowed[ $legacy_id ] ) || $target_id <= 0 || ! self::rolled_back_target_valid( $legacy_id, $target_id ) ) {
\t\t\t\treturn new WP_Error( 'snfla_file21_rollback_result_unverified', 'File 21 rollback returned an unexpected or unverifiable rolled-back target.' );
\t\t\t}
\t\t}
\t\tforeach ( array( 'skipped', 'already_rolled_back' ) as $collection ) {
\t\t\tif ( ! isset( $result[ $collection ] ) ) { continue; }
\t\t\tif ( ! is_array( $result[ $collection ] ) ) { return new WP_Error( 'snfla_file21_rollback_result_invalid' ); }
\t\t\tforeach ( $result[ $collection ] as $key => $value ) {
\t\t\t\t$candidate = is_int( $key ) || ( is_string( $key ) && ctype_digit( $key ) ) ? $key : ( is_array( $value ) ? ( $value['legacy_id'] ?? 0 ) : $value );
\t\t\t\t$id = self::strict_positive_id( $candidate );
\t\t\t\tif ( $id <= 0 || ! isset( $allowed[ $id ] ) ) { return new WP_Error( 'snfla_file21_rollback_unexpected_result_ids' ); }
\t\t\t}
\t\t}
\t\treturn $result;
\t}""")
elif r==42:
 rep("""\tpublic static function migration_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );""",
"""\tpublic static function migration_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$target_id = self::strict_positive_id( $target_id );""")
elif r==43:
 rep("""\tpublic static function rolled_back_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = absint( $legacy_id );
\t\t$target_id = absint( $target_id );""",
"""\tpublic static function rolled_back_target_valid( $legacy_id, $target_id ) {
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$target_id = self::strict_positive_id( $target_id );""")
elif r==44:
 rep("""\t\t$legacy_id  = absint( $legacy_id );
\t\t$target_id  = absint( $target_id );
\t\t$actor_id   = absint( $actor_id );""",
"""\t\t$legacy_id  = self::strict_positive_id( $legacy_id );
\t\t$target_id  = self::strict_positive_id( $target_id );
\t\t$actor_id   = self::strict_positive_id( $actor_id );""")
 Path('tools/r41-r44-file21.py').unlink();Path('.github/workflows/r41-r44-file21.yml').unlink()
else: raise SystemExit('unsupported')
p.write_text(s,encoding='utf-8')
