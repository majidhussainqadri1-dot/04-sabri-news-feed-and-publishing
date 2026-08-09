from pathlib import Path
p=Path('includes/class-snfla-mapping.php')
s=p.read_text(encoding='utf-8')
old="""\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'snfla_mapping_query_failed', 'The legacy mapping ledger could not be read safely.', array( 'status' => 500 ) );
\t\t}
\t\treturn is_array( $row ) ? $row : array();
\t}"""
new="""\t\tif ( ! empty( $wpdb->last_error ) ) {
\t\t\treturn new WP_Error( 'snfla_mapping_query_failed', 'The legacy mapping ledger could not be read safely.', array( 'status' => 500 ) );
\t\t}
\t\tif ( ! is_array( $row ) ) { return array(); }
\t\tif ( ! self::mapping_row_valid( $row, $legacy_id ) ) {
\t\t\treturn new WP_Error( 'snfla_mapping_row_corrupt', 'The legacy mapping ledger row contains invalid identity, state or integrity fields.', array( 'status' => 500 ) );
\t\t}
\t\treturn $row;
\t}"""
if old not in s: raise SystemExit('R68 get_checked target missing')
s=s.replace(old,new,1)
needle="\tprivate static function strict_positive_id( $value ) {"
helper="""\tprivate static function mapping_row_valid( array $row, $expected_legacy_id ) {
\t\t$legacy_id = self::strict_positive_id( $row['legacy_id'] ?? 0 );
\t\t$target_id = self::strict_nonnegative_id( $row['target_id'] ?? 0 );
\t\t$target_type = (string) ( $row['target_type'] ?? '' );
\t\t$status = (string) ( $row['status'] ?? '' );
\t\t$source_checksum = strtolower( (string) ( $row['source_checksum'] ?? '' ) );
\t\t$target_checksum = strtolower( (string) ( $row['target_checksum'] ?? '' ) );
\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
\t\t$progress = json_decode( (string) ( $row['interaction_ledger_json'] ?? '{}' ), true );
\t\t$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
\t\treturn $legacy_id === $expected_legacy_id
\t\t\t&& null !== $target_id
\t\t\t&& in_array( $target_type, array( '', 'post', 'sabri_news', 'source_only' ), true )
\t\t\t&& in_array( $status, $allowed_statuses, true )
\t\t\t&& ( '' === $source_checksum || 1 === preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) )
\t\t\t&& ( '' === $target_checksum || 1 === preg_match( '/^[a-f0-9]{64}$/D', $target_checksum ) )
\t\t\t&& ( '' === $run_uuid || self::uuid4_valid( $run_uuid ) )
\t\t\t&& is_array( $progress ) && JSON_ERROR_NONE === json_last_error();
\t}

"""
if needle not in s: raise SystemExit('R68 helper target missing')
s=s.replace(needle,helper+needle,1)
p.write_text(s,encoding='utf-8')
Path('tools/r68-mapping-read-integrity.py').unlink();Path('.github/workflows/r68-mapping-read-integrity.yml').unlink()
