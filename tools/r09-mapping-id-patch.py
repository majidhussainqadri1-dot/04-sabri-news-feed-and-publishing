from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\tpublic static function get_checked( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$t['map']} WHERE legacy_id=%d\", absint( $legacy_id ) ), ARRAY_A );"""
new="""\tpublic static function get_checked( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\tif ( $legacy_id <= 0 ) { return new WP_Error( 'snfla_invalid_legacy_id', 'A canonical positive legacy ID is required.', array( 'status' => 400 ) ); }
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT * FROM {$t['map']} WHERE legacy_id=%d\", $legacy_id ), ARRAY_A );"""
if old not in s: raise SystemExit('R09 get target missing')
s=s.replace(old,new,1)
old2="""\tpublic static function delete( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\treturn false !== $wpdb->delete( $t['map'], array( 'legacy_id' => absint( $legacy_id ) ), array( '%d' ) );
\t}"""
new2="""\tpublic static function delete( $legacy_id ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\treturn $legacy_id > 0 && false !== $wpdb->delete( $t['map'], array( 'legacy_id' => $legacy_id ), array( '%d' ) );
\t}"""
if old2 not in s: raise SystemExit('R09 delete target missing')
s=s.replace(old2,new2,1)
insert="""
\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed = (int) $value;
\t\treturn $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
\t}

"""
needle="\tpublic static function get( $legacy_id ) {"
if needle not in s: raise SystemExit('R09 helper insertion target missing')
s=s.replace(needle,insert+needle,1)
p.write_text(s,encoding='utf-8')
Path('tools/r09-mapping-id-patch.py').unlink();Path('.github/workflows/r09-mapping-id-patch.yml').unlink()
