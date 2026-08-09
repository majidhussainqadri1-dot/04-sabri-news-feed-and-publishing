#!/usr/bin/env python3
from pathlib import Path
import sys

round_no=int(sys.argv[1])

def patch(path, old, new):
    p=Path(path); s=p.read_text(encoding='utf-8')
    if old not in s:
        raise SystemExit(f'R{round_no:02d} target missing in {path}: {old[:120]!r}')
    p.write_text(s.replace(old,new,1),encoding='utf-8')

if round_no==10:
    path='includes/class-snfla-mapping.php'
    patch(path,
"""\t\t$status = sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) );
\t\t$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
\t\tif ( ! in_array( $status, $allowed_statuses, true ) ) { return false; }
\t\t$row = array(
\t\t\t'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
\t\t\t'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),""",
"""\t\t$status = sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) );
\t\t$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
\t\t$target_id_raw = array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 );
\t\t$target_id = self::strict_nonnegative_id( $target_id_raw );
\t\t$target_type = sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) );
\t\t$run_uuid = sanitize_text_field( (string) ( array_key_exists( 'run_uuid', $data ) ? $data['run_uuid'] : ( $current['run_uuid'] ?? '' ) ) );
\t\tif ( ! in_array( $status, $allowed_statuses, true ) || null === $target_id || ! in_array( $target_type, array( '', 'post', 'sabri_news' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) { return false; }
\t\t$row = array(
\t\t\t'target_id'               => $target_id,
\t\t\t'target_type'             => $target_type,""")
    patch(path,
"""\t\t\t'run_uuid'                => sanitize_text_field( array_key_exists( 'run_uuid', $data ) ? $data['run_uuid'] : ( $current['run_uuid'] ?? '' ) ),""",
"""\t\t\t'run_uuid'                => $run_uuid,""")
    patch(path,
"""\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed = (int) $value;
\t\treturn $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
\t}
""",
"""\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed = (int) $value;
\t\treturn $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
\t}

\tprivate static function strict_nonnegative_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value >= 0 ? $value : null; }
\t\tif ( '0' === $value ) { return 0; }
\t\t$positive = self::strict_positive_id( $value );
\t\treturn $positive > 0 ? $positive : null;
\t}

\tprivate static function uuid4_valid( $value ) {
\t\treturn 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', (string) $value );
\t}
""")
elif round_no==11:
    path='includes/class-snfla-mapping.php'
    patch(path,
"""\tpublic static function dry_run_replace( $run_uuid, $source_signature, $legacy_id, $source_checksum, array $conflicts, $target_type = 'auto' ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', $conflicts ) ) ) );""",
"""\tpublic static function dry_run_replace( $run_uuid, $source_signature, $legacy_id, $source_checksum, array $conflicts, $target_type = 'auto' ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$run_uuid = sanitize_text_field( (string) $run_uuid );
\t\t$source_signature = strtolower( (string) $source_signature );
\t\t$source_checksum = strtolower( (string) $source_checksum );
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$target_type = sanitize_key( $target_type );
\t\tif ( ! self::uuid4_valid( $run_uuid ) || $legacy_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_signature ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $target_type, array( 'auto', 'post', 'sabri_news' ), true ) ) { return false; }
\t\t$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', $conflicts ) ) ) );""")
    patch(path,"""\t\t\tsanitize_text_field( $run_uuid ),
\t\t\tsanitize_text_field( $source_signature ),
\t\t\tabsint( $legacy_id ),
\t\t\tsanitize_text_field( $source_checksum ),
\t\t\tsanitize_key( $target_type ),""",
"""\t\t\t$run_uuid,
\t\t\t$source_signature,
\t\t\t$legacy_id,
\t\t\t$source_checksum,
\t\t\t$target_type,""")
elif round_no==12:
    path='includes/class-snfla-mapping.php'
    patch(path,
"""\tpublic static function dry_run_candidate_checked( $legacy_id, $source_signature, $run_uuid ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT source_checksum,eligible,conflict_codes_json FROM {$t['dry_run']} WHERE legacy_id=%d AND source_signature=%s AND run_uuid=%s\", absint( $legacy_id ), sanitize_text_field( $source_signature ), sanitize_text_field( $run_uuid ) ), ARRAY_A );""",
"""\tpublic static function dry_run_candidate_checked( $legacy_id, $source_signature, $run_uuid ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\t$source_signature = strtolower( (string) $source_signature );
\t\t$run_uuid = sanitize_text_field( (string) $run_uuid );
\t\tif ( $legacy_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_signature ) || ! self::uuid4_valid( $run_uuid ) ) { return new WP_Error( 'snfla_dry_run_candidate_identity_invalid' ); }
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT source_checksum,eligible,conflict_codes_json FROM {$t['dry_run']} WHERE legacy_id=%d AND source_signature=%s AND run_uuid=%s\", $legacy_id, $source_signature, $run_uuid ), ARRAY_A );""")
    patch(path,
"""\t\t$codes = json_decode( (string) $row['conflict_codes_json'], true );
\t\tif ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
\t\treturn array( 'source_checksum' => (string) $row['source_checksum'], 'eligible' => ! empty( $row['eligible'] ), 'conflict_codes' => array_values( array_filter( array_map( 'sanitize_key', $codes ) ) ) );""",
"""\t\t$codes = json_decode( (string) $row['conflict_codes_json'], true );
\t\t$source_checksum = strtolower( (string) ( $row['source_checksum'] ?? '' ) );
\t\t$eligible = (string) ( $row['eligible'] ?? '' );
\t\tif ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $eligible, array( '0', '1' ), true ) ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
\t\treturn array( 'source_checksum' => $source_checksum, 'eligible' => '1' === $eligible, 'conflict_codes' => array_values( array_filter( array_map( 'sanitize_key', $codes ) ) ) );""")
elif round_no==13:
    path='includes/class-snfla-interaction-provider.php'
    patch(path,
"""\t\t$state        = isset( $progress['interaction_progress'][ $kind ] ) && is_array( $progress['interaction_progress'][ $kind ] ) ? $progress['interaction_progress'][ $kind ] : array();
\t\t$cursor       = absint( $state['cursor'] ?? 0 );
\t\t$meta_complete = 'views' !== $kind || ! empty( $state['meta_complete'] );
\t\t$report       = array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'remaining' => false, 'errors' => array() );""",
"""\t\t$state        = isset( $progress['interaction_progress'][ $kind ] ) && is_array( $progress['interaction_progress'][ $kind ] ) ? $progress['interaction_progress'][ $kind ] : array();
\t\t$report       = array( 'processed' => 0, 'migrated' => 0, 'skipped' => 0, 'remaining' => false, 'errors' => array() );
\t\tif ( ! self::progress_state_valid( $state ) ) { $report['errors'][] = 'interaction_progress_state_corrupt'; return $report; }
\t\t$cursor       = isset( $state['cursor'] ) ? (int) $state['cursor'] : 0;
\t\t$meta_complete = 'views' !== $kind || ( isset( $state['meta_complete'] ) && true === $state['meta_complete'] );""")
    needle="\tprivate static function migrate_kind( $kind, $legacy_id, $target_id, $budget, array &$progress ) {"
    helper="""\tprivate static function progress_state_valid( array $state ) {
\t\tif ( isset( $state['cursor'] ) && ( ! is_int( $state['cursor'] ) || $state['cursor'] < 0 ) ) { return false; }
\t\tforeach ( array( 'complete', 'meta_complete' ) as $flag ) { if ( isset( $state[ $flag ] ) && ! is_bool( $state[ $flag ] ) ) { return false; } }
\t\treturn true;
\t}

"""
    patch(path,needle,helper+needle)
elif round_no==14:
    path='includes/class-snfla-interaction-provider.php'
    patch(path,
"""\tpublic static function migrate( array $context ) {
\t\treturn self::run(
\t\t\tabsint( $context['legacy_id'] ?? 0 ),
\t\t\tabsint( $context['target_id'] ?? 0 ),
\t\t\tabsint( $context['actor_id'] ?? 0 ),
\t\t\tself::budget( $context['max_records'] ?? self::DEFAULT_RECORD_BUDGET )
\t\t);
\t}""",
"""\tpublic static function migrate( array $context ) {
\t\treturn self::run(
\t\t\tself::strict_positive_id( $context['legacy_id'] ?? 0 ),
\t\t\tself::strict_positive_id( $context['target_id'] ?? 0 ),
\t\t\tself::strict_positive_id( $context['actor_id'] ?? 0 ),
\t\t\tself::budget( $context['max_records'] ?? self::DEFAULT_RECORD_BUDGET )
\t\t);
\t}""")
    needle="\tprivate static function budget( $value ) {"
    helper="""\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed = (int) $value;
\t\treturn $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
\t}

"""
    patch(path,needle,helper+needle)
elif round_no==15:
    path='includes/class-snfla-file21-adapter.php'
    patch(path,
"""\tpublic static function target_for( $legacy_id ) {
\t\tif ( ! SNFLA_Capabilities::file21_ready() ) {
\t\t\treturn 0;
\t\t}
\t\treturn absint( \\Sabri\\HomeNewsFeed\\LegacyPublicationMigration::target_for( absint( $legacy_id ) ) );
\t}""",
"""\tpublic static function target_for( $legacy_id ) {
\t\t$legacy_id = self::strict_positive_id( $legacy_id );
\t\tif ( $legacy_id <= 0 || ! SNFLA_Capabilities::file21_ready() ) { return 0; }
\t\treturn self::strict_positive_id( \\Sabri\\HomeNewsFeed\\LegacyPublicationMigration::target_for( $legacy_id ) );
\t}""")
    needle="\tpublic static function migration_target_valid( $legacy_id, $target_id ) {"
    helper="""\tprivate static function strict_positive_id( $value ) {
\t\tif ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
\t\tif ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
\t\t$parsed = (int) $value;
\t\treturn $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
\t}

"""
    patch(path,needle,helper+needle)
elif round_no==16:
    path='includes/class-snfla-file21-adapter.php'
    patch(path,
"""\tpublic static function migrate( array $legacy_ids, $actor_id, $with_interactions = true ) {
\t\tif ( ! SNFLA_Capabilities::file21_ready() ) {""",
"""\tpublic static function migrate( array $legacy_ids, $actor_id, $with_interactions = true ) {
\t\t$legacy_ids = self::strict_id_batch( $legacy_ids );
\t\t$actor_id = self::strict_positive_id( $actor_id );
\t\tif ( empty( $legacy_ids ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_file21_contract_ids_invalid', 'Canonical File 21 migration requires positive unique IDs and actor identity.' ); }
\t\tif ( ! SNFLA_Capabilities::file21_ready() ) {""")
    patch(path,
"""\tpublic static function rollback( array $legacy_ids, $actor_id ) {
\t\tif ( ! SNFLA_Capabilities::file21_ready() ) {""",
"""\tpublic static function rollback( array $legacy_ids, $actor_id ) {
\t\t$legacy_ids = self::strict_id_batch( $legacy_ids );
\t\t$actor_id = self::strict_positive_id( $actor_id );
\t\tif ( empty( $legacy_ids ) || $actor_id <= 0 ) { return new WP_Error( 'snfla_file21_contract_ids_invalid', 'Canonical File 21 rollback requires positive unique IDs and actor identity.' ); }
\t\tif ( ! SNFLA_Capabilities::file21_ready() ) {""")
    needle="\tprivate static function strict_positive_id( $value ) {"
    helper="""\tprivate static function strict_id_batch( array $ids ) {
\t\tif ( empty( $ids ) || count( $ids ) > SNFLA_Migration::MAX_BATCH ) { return array(); }
\t\t$out=array(); $seen=array();
\t\tforeach ( $ids as $raw ) { $id=self::strict_positive_id( $raw ); if ( $id<=0 || isset( $seen[$id] ) ) { return array(); } $seen[$id]=true; $out[]=$id; }
\t\treturn $out;
\t}

"""
    patch(path,needle,helper+needle)
elif round_no==17:
    path='includes/class-snfla-migration.php'
    patch(path,
"""\t\t$uuid = sanitize_text_field( (string) $uuid );
\t\t$operation = sanitize_key( $operation );
\t\t$status = sanitize_key( $status );""",
"""\t\t$uuid = sanitize_text_field( (string) $uuid );
\t\t$operation = sanitize_key( $operation );
\t\t$status = sanitize_key( $status );
\t\tif ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid ) || ! in_array( $operation, array( 'migrate', 'rollback' ), true ) || 'running' !== $status ) { return false; }""")
elif round_no==18:
    path='includes/class-snfla-migration.php'
    patch(path,
"""\tpublic static function finish_run( $uuid, $status, array $summary ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$summary_json = wp_json_encode( SNFLA_Audit::redact( $summary ) );""",
"""\tpublic static function finish_run( $uuid, $status, array $summary ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$uuid = sanitize_text_field( (string) $uuid );
\t\t$status = sanitize_key( $status );
\t\tif ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid ) || ! in_array( $status, array( 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' ), true ) ) { return false; }
\t\t$summary_json = wp_json_encode( SNFLA_Audit::redact( $summary ) );""")
    patch(path,"""array( 'status' => sanitize_key( $status ), 'summary_json' => $summary_json, 'finished_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'run_uuid' => sanitize_text_field( $uuid ) )""",
"""array( 'status' => $status, 'summary_json' => $summary_json, 'finished_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'run_uuid' => $uuid )""")
elif round_no==19:
    path='includes/class-snfla-migration.php'
    patch(path,
"""\tpublic static function existing_run( $operation, $idempotency_hash ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT run_uuid,operation,status,source_signature,checkpoint_json,summary_json,started_at,finished_at FROM {$t['runs']} WHERE operation=%s AND idempotency_hash=%s LIMIT 1\", sanitize_key( $operation ), strtolower( sanitize_text_field( (string) $idempotency_hash ) ) ), ARRAY_A );""",
"""\tpublic static function existing_run( $operation, $idempotency_hash ) {
\t\tglobal $wpdb;
\t\t$t = SNFLA_Database::tables();
\t\t$operation = sanitize_key( $operation );
\t\t$idempotency_hash = strtolower( (string) $idempotency_hash );
\t\tif ( ! in_array( $operation, array( 'migrate', 'rollback' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $idempotency_hash ) ) { return new WP_Error( 'snfla_run_ledger_identity_invalid', 'Run-ledger lookup identity is invalid.', array( 'status' => 400 ) ); }
\t\t$wpdb->last_error = '';
\t\t$row = $wpdb->get_row( $wpdb->prepare( \"SELECT run_uuid,operation,status,source_signature,checkpoint_json,summary_json,started_at,finished_at FROM {$t['runs']} WHERE operation=%s AND idempotency_hash=%s LIMIT 1\", $operation, $idempotency_hash ), ARRAY_A );""")
    patch(path,
"""\t\tif ( ! is_array( $row ) ) { return array(); }
\t\t$row['checkpoint'] = json_decode( (string) $row['checkpoint_json'], true );""",
"""\t\tif ( ! is_array( $row ) ) { return array(); }
\t\tif ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', (string) ( $row['run_uuid'] ?? '' ) ) || (string) ( $row['operation'] ?? '' ) !== $operation || ! in_array( (string) ( $row['status'] ?? '' ), array( 'running', 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', (string) ( $row['source_signature'] ?? '' ) ) ) { return new WP_Error( 'snfla_run_ledger_corrupt', 'The operation run ledger contains invalid identity or state evidence.', array( 'status' => 500 ) ); }
\t\t$row['checkpoint'] = json_decode( (string) $row['checkpoint_json'], true );""")
elif round_no==20:
    path='includes/class-snfla-rest.php'
    patch(path,
"""\t\t\t'expected_version' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint', 'validate_callback' => static function ( $value ) { return absint( $value ) >= 1; } ),""",
"""\t\t\t'expected_version' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => static function ( $value ) { return is_int( $value ) && $value >= 1 ? $value : 0; }, 'validate_callback' => static function ( $value ) { return is_int( $value ) && $value >= 1; } ),""")
    Path('tools/r10-r20-series.py').unlink()
    Path('.github/workflows/r10-r20-series.yml').unlink()
else:
    raise SystemExit('unsupported round')
