<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Mapping {
	public static function get_checked( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = self::strict_positive_id( $legacy_id );
		if ( $legacy_id <= 0 ) { return new WP_Error( 'snfla_invalid_legacy_id', 'A canonical positive legacy ID is required.', array( 'status' => 400 ) ); }
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['map']} WHERE legacy_id=%d", $legacy_id ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'snfla_mapping_query_failed', 'The legacy mapping ledger could not be read safely.', array( 'status' => 500 ) );
		}
		return is_array( $row ) ? $row : array();
	}


	private static function strict_positive_id( $value ) {
		if ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
		$parsed = (int) $value;
		return $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
	}

	private static function strict_nonnegative_id( $value ) {
		if ( is_int( $value ) ) { return $value >= 0 ? $value : null; }
		if ( '0' === $value ) { return 0; }
		$positive = self::strict_positive_id( $value );
		return $positive > 0 ? $positive : null;
	}

	private static function uuid4_valid( $value ) {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', (string) $value );
	}

	public static function get( $legacy_id ) {
		$row = self::get_checked( $legacy_id );
		return is_wp_error( $row ) ? array() : $row;
	}

	public static function upsert( $legacy_id, array $data ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		if ( $legacy_id <= 0 ) { return false; }
		$current = self::get_checked( $legacy_id );
		if ( is_wp_error( $current ) ) { return false; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$source_checksum = array_key_exists( 'source_checksum', $data ) ? strtolower( (string) $data['source_checksum'] ) : strtolower( (string) ( $current['source_checksum'] ?? '' ) );
		$target_checksum = array_key_exists( 'target_checksum', $data ) ? strtolower( (string) $data['target_checksum'] ) : strtolower( (string) ( $current['target_checksum'] ?? '' ) );
		if ( ( '' !== $source_checksum && 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) ) || ( '' !== $target_checksum && 1 !== preg_match( '/^[a-f0-9]{64}$/D', $target_checksum ) ) ) {
			return false;
		}
		$interaction_json = $current['interaction_ledger_json'] ?? '{}';
		if ( isset( $data['interaction_ledger'] ) ) {
			$interaction_json = wp_json_encode( SNFLA_Audit::redact( (array) $data['interaction_ledger'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( ! is_string( $interaction_json ) ) { return false; }
		}
		$status = sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) );
		$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
		$target_id_raw = array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 );
		$target_id = self::strict_nonnegative_id( $target_id_raw );
		$target_type = sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) );
		$run_uuid = sanitize_text_field( (string) ( array_key_exists( 'run_uuid', $data ) ? $data['run_uuid'] : ( $current['run_uuid'] ?? '' ) ) );
		if ( ! in_array( $status, $allowed_statuses, true ) || null === $target_id || ! in_array( $target_type, array( '', 'post', 'sabri_news', 'source_only' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) { return false; }
		$row = array(
			'target_id'               => $target_id,
			'target_type'             => $target_type,
			'status'                  => $status,
			'source_checksum'         => $source_checksum,
			'target_checksum'         => $target_checksum,
			'run_uuid'                => $run_uuid,
			'interaction_ledger_json' => $interaction_json,
			'last_error_code'         => sanitize_key( array_key_exists( 'last_error_code', $data ) ? $data['last_error_code'] : ( $current['last_error_code'] ?? '' ) ),
			'updated_at'              => $now,
		);
		if ( $current ) {
			return false !== $wpdb->update( $t['map'], $row, array( 'legacy_id' => $legacy_id ), array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
		}
		$row['legacy_id'] = $legacy_id;
		$row['created_at'] = $now;
		return false !== $wpdb->insert( $t['map'], $row, array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ) );
	}

	public static function delete( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = self::strict_positive_id( $legacy_id );
		return $legacy_id > 0 && false !== $wpdb->delete( $t['map'], array( 'legacy_id' => $legacy_id ), array( '%d' ) );
	}

	public static function open_conflict_codes( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT conflict_code FROM {$t['conflicts']} WHERE legacy_id=%d AND status='open' ORDER BY id ASC", absint( $legacy_id ) ) );
		if ( ! is_array( $codes ) || ! empty( $wpdb->last_error ) ) { return array( 'conflict_ledger_read_failed' ); }
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
	}

	public static function progress_checked( $legacy_id ) {
		$current = self::get_checked( $legacy_id );
		if ( is_wp_error( $current ) ) { return $current; }
		if ( empty( $current['interaction_ledger_json'] ) ) { return array(); }
		$data = json_decode( (string) $current['interaction_ledger_json'], true );
		return is_array( $data ) && JSON_ERROR_NONE === json_last_error()
			? $data
			: new WP_Error( 'snfla_mapping_progress_corrupt', 'The mapping interaction-progress evidence is malformed.', array( 'status' => 500 ) );
	}

	public static function progress( $legacy_id ) {
		$data = self::progress_checked( $legacy_id );
		return is_wp_error( $data ) ? array() : $data;
	}

	public static function update_progress( $legacy_id, $target_id, array $progress ) {
		$current = self::get_checked( $legacy_id );
		if ( is_wp_error( $current ) ) { return false; }
		return self::upsert(
			$legacy_id,
			array(
				'target_id'          => $target_id,
				'target_type'        => $current['target_type'] ?? 'post',
				'status'             => $current['status'] ?? 'interaction_pending',
				'source_checksum'    => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ),
				'target_checksum'    => $current['target_checksum'] ?? '',
				'run_uuid'           => $current['run_uuid'] ?? '',
				'interaction_ledger' => $progress,
			)
		);
	}

	public static function interaction_source_recorded( $legacy_id, $kind, $source_row_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND source_row_id=%d AND status='active' LIMIT 1", absint( $legacy_id ), sanitize_key( $kind ), absint( $source_row_id ) ) );
		return ! empty( $wpdb->last_error ) ? new WP_Error( 'snfla_interaction_ledger_query_failed' ) : 0 < absint( $value );
	}

	public static function record_interaction_row( $legacy_id, $target_id, $kind, $source_row_id, $canonical_row_id, array $original ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		$target_id = absint( $target_id );
		$source_row_id = absint( $source_row_id );
		$canonical_row_id = absint( $canonical_row_id );
		$kind = sanitize_key( $kind );
		$synthetic_view = 'views' === $kind && 0 === $source_row_id;
		if ( $legacy_id <= 0 || $target_id <= 0 || ( $source_row_id <= 0 && ! $synthetic_view ) || $canonical_row_id <= 0 || ! in_array( $kind, array( 'reactions', 'saves', 'views', 'reports' ), true ) ) {
			return false;
		}
		$created_by_migration = ! empty( $original['created_by_migration'] );
		$contribution_count = max( 0, absint( $original['source_contribution'] ?? 0 ) );
		$original = SNFLA_Audit::redact( $original );
		$original_json = wp_json_encode( $original, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $original_json ) ) { return false; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['interaction_ledger']} (legacy_id,target_id,kind,source_row_id,canonical_row_id,contribution_count,original_json,created_by_migration,status,created_at,updated_at) VALUES (%d,%d,%s,%d,%d,%d,%s,%d,'active',%s,%s) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),canonical_row_id=VALUES(canonical_row_id),contribution_count=VALUES(contribution_count),original_json=VALUES(original_json),created_by_migration=VALUES(created_by_migration),status='active',updated_at=VALUES(updated_at)",
			$legacy_id,
			$target_id,
			$kind,
			$source_row_id,
			$canonical_row_id,
			$contribution_count,
			$original_json,
			$created_by_migration ? 1 : 0,
			$now,
			$now
		);
		return false !== $wpdb->query( $sql );
	}

	public static function interaction_original_by_canonical( $legacy_id, $kind, $canonical_row_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT original_json,created_by_migration FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active' ORDER BY id ASC LIMIT 1", absint( $legacy_id ), sanitize_key( $kind ), absint( $canonical_row_id ) ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_interaction_ledger_query_failed' ); }
		if ( ! is_array( $row ) ) { return array(); }
		$original = json_decode( (string) $row['original_json'], true );
		if ( ! is_array( $original ) || JSON_ERROR_NONE !== json_last_error() ) { return new WP_Error( 'snfla_interaction_original_corrupt' ); }
		if ( ! empty( $row['created_by_migration'] ) ) { $original['created_by_migration'] = true; }
		return $original;
	}

	public static function interaction_rows( $legacy_id, $after_id = 0, $limit = 500, $kind = '', $status = 'active' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		$after_id = absint( $after_id );
		$limit = min( 2000, max( 1, absint( $limit ) ) );
		$kind = sanitize_key( $kind );
		$status = sanitize_key( $status );
		$where = "legacy_id=%d AND id>%d";
		$args = array( $legacy_id, $after_id );
		if ( '' !== $kind ) {
			$where .= ' AND kind=%s';
			$args[] = $kind;
		}
		if ( '' !== $status ) {
			$where .= ' AND status=%s';
			$args[] = $status;
		}
		$args[] = $limit;
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['interaction_ledger']} WHERE {$where} ORDER BY id ASC LIMIT %d", $args ), ARRAY_A );
		return ! is_array( $rows ) || ! empty( $wpdb->last_error ) ? new WP_Error( 'snfla_interaction_ledger_query_failed' ) : $rows;
	}

	public static function interaction_contribution_total( $legacy_id, $kind, $canonical_row_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(contribution_count),0) FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND kind=%s AND canonical_row_id=%d AND status='active'",
				absint( $legacy_id ),
				sanitize_key( $kind ),
				absint( $canonical_row_id )
			)
		);
		return ! empty( $wpdb->last_error ) || null === $value ? new WP_Error( 'snfla_interaction_ledger_query_failed' ) : absint( $value );
	}

	public static function interaction_canonical_groups( $legacy_id, $after_first_id = 0, $limit = 500 ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$limit = min( 2000, max( 1, absint( $limit ) ) );
		$sql = $wpdb->prepare(
			"SELECT l.* FROM {$t['interaction_ledger']} l INNER JOIN (SELECT MIN(id) first_id FROM {$t['interaction_ledger']} WHERE legacy_id=%d AND status='active' GROUP BY kind,canonical_row_id) g ON g.first_id=l.id WHERE l.id>%d ORDER BY l.id ASC LIMIT %d",
			absint( $legacy_id ),
			absint( $after_first_id ),
			$limit
		);
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return ! is_array( $rows ) || ! empty( $wpdb->last_error ) ? new WP_Error( 'snfla_interaction_ledger_query_failed' ) : $rows;
	}

	public static function mark_interaction_ledger_rolled_back( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		return false !== $wpdb->query( $wpdb->prepare( "UPDATE {$t['interaction_ledger']} SET status='rolled_back',updated_at=%s WHERE legacy_id=%d AND status='active'", gmdate( 'Y-m-d H:i:s' ), absint( $legacy_id ) ) );
	}

	public static function dry_run_replace( $run_uuid, $source_signature, $legacy_id, $source_checksum, array $conflicts, $target_type = 'auto' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		$source_signature = strtolower( (string) $source_signature );
		$source_checksum = strtolower( (string) $source_checksum );
		$legacy_id = self::strict_positive_id( $legacy_id );
		$target_type = sanitize_key( $target_type );
		if ( ! self::uuid4_valid( $run_uuid ) || $legacy_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_signature ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $target_type, array( 'auto', 'post', 'sabri_news' ), true ) ) { return false; }
		$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', $conflicts ) ) ) );
		$conflicts_json = wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $conflicts_json ) ) { return false; }
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['dry_run']} (run_uuid,source_signature,legacy_id,source_checksum,target_type,eligible,conflict_codes_json,created_at) VALUES (%s,%s,%d,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE source_signature=VALUES(source_signature),source_checksum=VALUES(source_checksum),target_type=VALUES(target_type),eligible=VALUES(eligible),conflict_codes_json=VALUES(conflict_codes_json),created_at=VALUES(created_at)",
			$run_uuid,
			$source_signature,
			$legacy_id,
			$source_checksum,
			$target_type,
			empty( $conflicts ) ? 1 : 0,
			$conflicts_json,
			gmdate( 'Y-m-d H:i:s' )
		);
		return false !== $wpdb->query( $sql );
	}

	public static function dry_run_candidate_checked( $legacy_id, $source_signature, $run_uuid ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = self::strict_positive_id( $legacy_id );
		$source_signature = strtolower( (string) $source_signature );
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( $legacy_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_signature ) || ! self::uuid4_valid( $run_uuid ) ) { return new WP_Error( 'snfla_dry_run_candidate_identity_invalid' ); }
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT source_checksum,eligible,conflict_codes_json FROM {$t['dry_run']} WHERE legacy_id=%d AND source_signature=%s AND run_uuid=%s", $legacy_id, $source_signature, $run_uuid ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_dry_run_candidate_query_failed' ); }
		if ( ! is_array( $row ) ) { return array(); }
		$codes = json_decode( (string) $row['conflict_codes_json'], true );
		$source_checksum = strtolower( (string) ( $row['source_checksum'] ?? '' ) );
		$eligible = (string) ( $row['eligible'] ?? '' );
		if ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $source_checksum ) || ! in_array( $eligible, array( '0', '1' ), true ) ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
		return array( 'source_checksum' => $source_checksum, 'eligible' => '1' === $eligible, 'conflict_codes' => array_values( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
	}

	public static function clear_dry_run_rows( $run_uuid ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( '' === $run_uuid ) { return false; }
		return false !== $wpdb->delete( $t['dry_run'], array( 'run_uuid' => $run_uuid ), array( '%s' ) );
	}

	public static function purge_other_dry_run_rows( $run_uuid ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( '' === $run_uuid ) { return false; }
		return false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$t['dry_run']} WHERE run_uuid<>%s", $run_uuid ) );
	}

	public static function open_conflict( $legacy_id, $code, $severity, array $context = array(), $run_uuid = '' ) {
	\tglobal $wpdb;
	\t$t = SNFLA_Database::tables();
	\t$legacy_id = self::strict_positive_id( $legacy_id );
	\t$code = sanitize_key( $code );
	\t$severity = in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) ? $severity : 'blocker';
	\t$run_uuid = sanitize_text_field( (string) $run_uuid );
	\tif ( $legacy_id <= 0 || '' === $code || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) { return false; }
	\t$redacted_json = wp_json_encode( SNFLA_Audit::redact( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	\tif ( ! is_string( $redacted_json ) ) { return false; }
	\t$fingerprint = hash( 'sha256', SNFLA_Checksum::encode( array( 'run_uuid' => $run_uuid, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ) );
	\t$now = gmdate( 'Y-m-d H:i:s' );
	\t$sql = $wpdb->prepare(
	\t\t"INSERT INTO {$t['conflicts']} (legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid,created_at,resolved_at) VALUES (%d,%s,%s,%s,'open',%s,%s,%s,NULL) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status='open',redacted_context_json=VALUES(redacted_context_json),run_uuid=VALUES(run_uuid),resolved_at=NULL",
	\t\t$legacy_id, $code, $severity, $fingerprint, $redacted_json, $run_uuid, $now
	\t);
	\t$wpdb->last_error = '';
	\t$result = $wpdb->query( $sql );
	\treturn false !== $result && empty( $wpdb->last_error );
	}

	public static function conflict_ledger_integrity() {
	\tglobal $wpdb;
	\t$t = SNFLA_Database::tables();
	\t$wpdb->last_error = '';
	\t$rows = $wpdb->get_results( "SELECT id,legacy_id,conflict_code,severity,fingerprint,status,run_uuid FROM {$t['conflicts']} ORDER BY id ASC", ARRAY_A );
	\tif ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_conflict_ledger_query_failed' ); }
	\tforeach ( $rows as $row ) {
	\t\t$id = self::strict_positive_id( $row['id'] ?? 0 );
	\t\t$legacy_id = self::strict_positive_id( $row['legacy_id'] ?? 0 );
	\t\t$code = (string) ( $row['conflict_code'] ?? '' );
	\t\t$severity = (string) ( $row['severity'] ?? '' );
	\t\t$fingerprint = strtolower( (string) ( $row['fingerprint'] ?? '' ) );
	\t\t$status = (string) ( $row['status'] ?? '' );
	\t\t$run_uuid = (string) ( $row['run_uuid'] ?? '' );
	\t\tif ( $id <= 0 || $legacy_id <= 0 || '' === $code || sanitize_key( $code ) !== $code || ! in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) || ! in_array( $status, array( 'open', 'resolved' ), true ) || ( '' !== $run_uuid && ! self::uuid4_valid( $run_uuid ) ) ) {
	\t\t\treturn new WP_Error( 'snfla_conflict_ledger_corrupt' );
	\t\t}
	\t}
	\treturn true;
	}

	public static function open_conflict_count() {
	\tglobal $wpdb;
	\t$integrity = self::conflict_ledger_integrity();
	\tif ( is_wp_error( $integrity ) ) { return PHP_INT_MAX; }
	\t$t = SNFLA_Database::tables();
	\t$wpdb->last_error = '';
	\t$value = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
	\treturn ! empty( $wpdb->last_error ) || null === $value || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', (string) $value ) ? PHP_INT_MAX : (int) $value;
	}

	public static function resolve_conflict( $conflict_id, $actor_id, $resolution_code ) {
	\tglobal $wpdb;
	\t$t = SNFLA_Database::tables();
	\t$conflict_id = self::strict_positive_id( $conflict_id );
	\t$actor_id = self::strict_positive_id( $actor_id );
	\t$resolution_code = sanitize_key( $resolution_code );
	\tif ( $conflict_id <= 0 || $actor_id <= 0 || '' === $resolution_code || is_wp_error( self::conflict_ledger_integrity() ) ) { return false; }
	\tif ( ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
	\ttry {
	\t\t$wpdb->last_error = '';
	\t\t$changed = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $conflict_id, 'status' => 'open' ), array( '%s', '%s' ), array( '%d', '%s' ) );
	\t\tif ( false === $changed || ! empty( $wpdb->last_error ) || 1 !== (int) $changed ) { return false; }
	\t\tif ( SNFLA_Audit::record( 'conflict_resolved', $actor_id, array( 'conflict_id' => $conflict_id, 'resolution_code' => $resolution_code ), 'conflict:' . $conflict_id ) ) { return true; }
	\t\t$wpdb->last_error = '';
	\t\t$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', null ), array( '%d', '%s' ) );
	\t\treturn false !== $reverted && empty( $wpdb->last_error ) && 1 === (int) $reverted ? false : false;
	\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
	}

	public static function supersede_run_conflicts( $run_uuid, $actor_id ) {
	\tglobal $wpdb;
	\t$t = SNFLA_Database::tables();
	\t$run_uuid = sanitize_text_field( (string) $run_uuid );
	\t$actor_id = self::strict_positive_id( $actor_id );
	\tif ( '' === $run_uuid ) { return true; }
	\tif ( ! self::uuid4_valid( $run_uuid ) || $actor_id <= 0 || is_wp_error( self::conflict_ledger_integrity() ) ) { return false; }
	\tif ( ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
	\ttry {
	\t\t$wpdb->last_error = '';
	\t\t$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE run_uuid=%s AND status='open' ORDER BY id ASC", $run_uuid ) );
	\t\tif ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
	\t\t$ids = array_values( array_filter( array_map( array( __CLASS__, 'strict_positive_id_for_callback' ), $changed_ids ) ) );
	\t\tif ( count( $ids ) !== count( $changed_ids ) ) { return false; }
	\t\tif ( empty( $ids ) ) { return true; }
	\t\t$now = gmdate( 'Y-m-d H:i:s' );
	\t\t$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE run_uuid=%s AND status='open'", $now, $run_uuid ) );
	\t\tif ( false === $result || ! empty( $wpdb->last_error ) || (int) $result !== count( $ids ) ) { return false; }
	\t\tif ( SNFLA_Audit::record( 'dry_run_conflicts_superseded', $actor_id, array( 'run_uuid' => $run_uuid, 'resolved_count' => count( $ids ) ), 'dry-run:' . $run_uuid ) ) { return true; }
	\t\t$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	\t\t$args = array_merge( array( $now ), $ids );
	\t\t$wpdb->last_error = '';
	\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$placeholders})", $args ) );
	\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;
	\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
	}

	public static function resolve_system_conflicts( $legacy_id, array $codes, $actor_id, $resolution_code = 'automatic_reconciliation_verified' ) {
	\tglobal $wpdb;
	\t$t = SNFLA_Database::tables();
	\t$legacy_id = self::strict_positive_id( $legacy_id );
	\t$actor_id = self::strict_positive_id( $actor_id );
	\t$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
	\t$resolution_code = sanitize_key( $resolution_code );
	\tif ( $legacy_id <= 0 || $actor_id <= 0 || empty( $codes ) || '' === $resolution_code ) { return true; }
	\tif ( is_wp_error( self::conflict_ledger_integrity() ) || ! SNFLA_Database::acquire_lock( 'conflicts', 5 ) ) { return false; }
	\ttry {
	\t\t$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
	\t\t$args = array_merge( array( $legacy_id ), $codes );
	\t\t$wpdb->last_error = '';
	\t\t$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders}) ORDER BY id ASC", $args ) );
	\t\tif ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
	\t\t$ids = array_values( array_filter( array_map( array( __CLASS__, 'strict_positive_id_for_callback' ), $changed_ids ) ) );
	\t\tif ( count( $ids ) !== count( $changed_ids ) || empty( $ids ) ) { return empty( $changed_ids ); }
	\t\t$now = gmdate( 'Y-m-d H:i:s' );
	\t\t$update_args = array_merge( array( $now, $legacy_id ), $codes );
	\t\t$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders})", $update_args ) );
	\t\tif ( false === $result || ! empty( $wpdb->last_error ) || (int) $result !== count( $ids ) ) { return false; }
	\t\tif ( SNFLA_Audit::record( 'system_conflicts_resolved', $actor_id, array( 'legacy_id' => $legacy_id, 'codes' => $codes, 'resolution_code' => $resolution_code, 'resolved_count' => count( $ids ) ), 'legacy:' . $legacy_id ) ) { return true; }
	\t\t$revert_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	\t\t$revert_args = array_merge( array( $now ), $ids );
	\t\t$wpdb->last_error = '';
	\t\t$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_at=NULL WHERE resolved_at=%s AND id IN ({$revert_placeholders})", $revert_args ) );
	\t\treturn false !== $compensated && empty( $wpdb->last_error ) && (int) $compensated === count( $ids ) ? false : false;
	\t} finally { SNFLA_Database::release_lock( 'conflicts' ); }
	}

	public static function strict_positive_id_for_callback( $value ) { return self::strict_positive_id( $value ); }

}
