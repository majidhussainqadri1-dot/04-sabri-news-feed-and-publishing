#!/usr/bin/env python3
from pathlib import Path
import sys
r=int(sys.argv[1])
def patch(path,old,new):
 p=Path(path);s=p.read_text(encoding='utf-8')
 if old not in s: raise SystemExit(f'R{r} target missing in {path}: {old[:100]!r}')
 p.write_text(s.replace(old,new,1),encoding='utf-8')
if r==37:
 patch('includes/class-snfla-mapping.php',"! in_array( $target_type, array( '', 'post', 'sabri_news' ), true )","! in_array( $target_type, array( '', 'post', 'sabri_news', 'source_only' ), true )")
elif r==38:
 path='includes/class-snfla-integrity.php';p=Path(path);s=p.read_text(encoding='utf-8')
 needle="\tpublic static function normalized_ids( array $ids, $limit = 100 ) {"
 helper="""\tpublic static function strict_positive_ids( array $ids, $limit = 100 ) {
\t\tif ( ! is_int( $limit ) || $limit < 1 || count( $ids ) < 1 || count( $ids ) > $limit ) { return new WP_Error( 'snfla_invalid_id_batch' ); }
\t\t$out=array(); $seen=array();
\t\tforeach ( $ids as $raw ) {
\t\t\tif ( is_int( $raw ) ) { $id=$raw; }
\t\t\telseif ( is_string( $raw ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $raw ) ) { $id=(int)$raw; if ( $id<=0 || (string)$id !== $raw ) { return new WP_Error( 'snfla_invalid_id_batch' ); } }
\t\t\telse { return new WP_Error( 'snfla_invalid_id_batch' ); }
\t\t\tif ( $id<=0 || isset($seen[$id]) ) { return new WP_Error( 'snfla_invalid_id_batch' ); }
\t\t\t$seen[$id]=true; $out[]=$id;
\t\t}
\t\treturn $out;
\t}

"""
 if needle not in s: raise SystemExit('R38 helper target missing')
 p.write_text(s.replace(needle,helper+needle,1),encoding='utf-8')
 path='includes/class-snfla-migration.php'
 patch(path,"""\tpublic static function quarantine_disposition( $actor_id, array $legacy_ids, $reason_code, $decision_reference, $expected_state, $expected_version ) {
\t\t$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
\t\t$reason_code = sanitize_key( (string) $reason_code );""",
"""\tpublic static function quarantine_disposition( $actor_id, array $legacy_ids, $reason_code, $decision_reference, $expected_state, $expected_version ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );
\t\tif ( is_wp_error( $legacy_ids ) ) { return new WP_Error( 'snfla_invalid_quarantine_batch', 'Quarantine IDs must be positive, unique and canonical.', array( 'status' => 400 ) ); }
\t\t$reason_code = sanitize_key( (string) $reason_code );""")
elif r==39:
 patch('includes/class-snfla-migration.php',"""\tpublic static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
\t\t$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
\t\tif ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }""",
"""\tpublic static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );
\t\tif ( is_wp_error( $legacy_ids ) ) { return new WP_Error( 'snfla_invalid_migration_batch', 'Migration IDs must be positive, unique and canonical.', array( 'status' => 400 ) ); }
\t\tif ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }""")
elif r==40:
 patch('includes/class-snfla-rollback.php',"""\tpublic static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $restore_handover = false, $handover_confirmation = '' ) {
\t\t$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
\t\tif ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_rollback_batch', 'Select at least one migrated legacy publication.', array( 'status' => 400 ) ); }""",
"""\tpublic static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $restore_handover = false, $handover_confirmation = '' ) {
\t\t$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );
\t\tif ( is_wp_error( $legacy_ids ) ) { return new WP_Error( 'snfla_invalid_rollback_batch', 'Rollback IDs must be positive, unique and canonical.', array( 'status' => 400 ) ); }
\t\tif ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_rollback_batch', 'Select at least one migrated legacy publication.', array( 'status' => 400 ) ); }""")
 Path('tools/r37-r40-series.py').unlink();Path('.github/workflows/r37-r40-series.yml').unlink()
else: raise SystemExit('unsupported')
