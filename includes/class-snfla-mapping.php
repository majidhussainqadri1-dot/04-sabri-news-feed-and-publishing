<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Mapping {
	public static function get_checked( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['map']} WHERE legacy_id=%d", absint( $legacy_id ) ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'snfla_mapping_query_failed', 'The legacy mapping ledger could not be read safely.', array( 'status' => 500 ) );
		}
		return is_array( $row ) ? $row : array();
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
		if ( ! in_array( $status, $allowed_statuses, true ) ) { return false; }
		$row = array(
			'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
			'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),
			'status'                  => $status,
			'source_checksum'         => $source_checksum,
			'target_checksum'         => $target_checksum,
			'run_uuid'                => sanitize_text_field( array_key_exists( 'run_uuid', $data ) ? $data['run_uuid'] : ( $current['run_uuid'] ?? '' ) ),
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
		return false !== $wpdb->delete( $t['map'], array( 'legacy_id' => absint( $legacy_id ) ), array( '%d' ) );
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
		$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', $conflicts ) ) ) );
		$conflicts_json = wp_json_encode( $conflicts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $conflicts_json ) ) { return false; }
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['dry_run']} (run_uuid,source_signature,legacy_id,source_checksum,target_type,eligible,conflict_codes_json,created_at) VALUES (%s,%s,%d,%s,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE source_signature=VALUES(source_signature),source_checksum=VALUES(source_checksum),target_type=VALUES(target_type),eligible=VALUES(eligible),conflict_codes_json=VALUES(conflict_codes_json),created_at=VALUES(created_at)",
			sanitize_text_field( $run_uuid ),
			sanitize_text_field( $source_signature ),
			absint( $legacy_id ),
			sanitize_text_field( $source_checksum ),
			sanitize_key( $target_type ),
			empty( $conflicts ) ? 1 : 0,
			$conflicts_json,
			gmdate( 'Y-m-d H:i:s' )
		);
		return false !== $wpdb->query( $sql );
	}

	public static function dry_run_candidate_checked( $legacy_id, $source_signature, $run_uuid ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT source_checksum,eligible,conflict_codes_json FROM {$t['dry_run']} WHERE legacy_id=%d AND source_signature=%s AND run_uuid=%s", absint( $legacy_id ), sanitize_text_field( $source_signature ), sanitize_text_field( $run_uuid ) ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_dry_run_candidate_query_failed' ); }
		if ( ! is_array( $row ) ) { return array(); }
		$codes = json_decode( (string) $row['conflict_codes_json'], true );
		if ( ! is_array( $codes ) || JSON_ERROR_NONE !== json_last_error() ) { return new WP_Error( 'snfla_dry_run_candidate_corrupt' ); }
		return array( 'source_checksum' => (string) $row['source_checksum'], 'eligible' => ! empty( $row['eligible'] ), 'conflict_codes' => array_values( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
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
		global $wpdb;
		$t = SNFLA_Database::tables();
		$code = sanitize_key( $code );
		$severity = in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) ? $severity : 'blocker';
		$redacted_json = wp_json_encode( SNFLA_Audit::redact( $context ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( '' === $code || ! is_string( $redacted_json ) ) { return false; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['conflicts']} (run_uuid,legacy_id,conflict_code,severity,status,context_json,created_at,updated_at) VALUES (%s,%d,%s,%s,'open',%s,%s,%s) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status='open',context_json=VALUES(context_json),resolved_by=NULL,resolution_code='',resolved_at=NULL,updated_at=VALUES(updated_at)",
			sanitize_text_field( $run_uuid ),
			absint( $legacy_id ),
			$code,
			$severity,
			$redacted_json,
			$now,
			$now
		);
		return false !== $wpdb->query( $sql );
	}

	public static function open_conflict_count() {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$value = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
		return ! empty( $wpdb->last_error ) || null === $value ? PHP_INT_MAX : absint( $value );
	}

	public static function resolve_conflict( $conflict_id, $actor_id, $resolution_code ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$conflict_id = absint( $conflict_id );
		$resolution_code = sanitize_key( $resolution_code );
		if ( $conflict_id <= 0 || '' === $resolution_code ) { return false; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->last_error = '';
		$result = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_by' => absint( $actor_id ), 'resolution_code' => $resolution_code, 'resolved_at' => $now, 'updated_at' => $now ), array( 'id' => $conflict_id, 'status' => 'open' ), array( '%s', '%d', '%s', '%s', '%s' ), array( '%d', '%s' ) );
		return false !== $result && empty( $wpdb->last_error );
	}

	public static function supersede_run_conflicts( $run_uuid, $actor_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( '' === $run_uuid ) { return true; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->last_error = '';
		$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE run_uuid=%s AND status='open' ORDER BY id ASC", $run_uuid ) );
		if ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
		if ( empty( $changed_ids ) ) { return true; }
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_by=%d,resolution_code='superseded_by_new_dry_run',resolved_at=%s,updated_at=%s WHERE run_uuid=%s AND status='open'", absint( $actor_id ), $now, $now, $run_uuid ) );
		if ( false === $result || ! empty( $wpdb->last_error ) ) { return false; }
		$audit_ok = SNFLA_Audit::record( 'dry_run_conflicts_superseded', $actor_id, array( 'run_uuid' => $run_uuid, 'resolved_count' => absint( $result ) ), 'dry-run:' . $run_uuid );
		if ( $audit_ok ) { return true; }
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $changed_ids ) ) ) );
		if ( empty( $ids ) ) { return false; }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args = array_merge( array( $now ), $ids );
		$wpdb->last_error = '';
		$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_by=NULL,resolution_code='',resolved_at=NULL WHERE updated_at=%s AND id IN ({$placeholders})", $args ) );
		return false !== $compensated && empty( $wpdb->last_error ) && absint( $compensated ) === count( $ids );
	}

	public static function resolve_system_conflicts( $legacy_id, array $codes, $actor_id, $resolution_code = 'automatic_reconciliation_verified' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
		$resolution_code = sanitize_key( $resolution_code );
		if ( $legacy_id <= 0 || empty( $codes ) || '' === $resolution_code ) { return true; }
		$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$args = array_merge( array( $legacy_id ), $codes );
		$wpdb->last_error = '';
		$changed_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t['conflicts']} WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders}) ORDER BY id ASC", $args ) );
		if ( ! is_array( $changed_ids ) || ! empty( $wpdb->last_error ) ) { return false; }
		if ( empty( $changed_ids ) ) { return true; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$update_args = array_merge( array( absint( $actor_id ), $resolution_code, $now, $now, $legacy_id ), $codes );
		$result = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='resolved',resolved_by=%d,resolution_code=%s,resolved_at=%s,updated_at=%s WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders})", $update_args ) );
		if ( false === $result || ! empty( $wpdb->last_error ) ) { return false; }
		if ( SNFLA_Audit::record( 'system_conflicts_resolved', $actor_id, array( 'legacy_id' => $legacy_id, 'codes' => $codes, 'resolved_count' => absint( $result ) ), 'legacy:' . $legacy_id ) ) { return true; }
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $changed_ids ) ) ) );
		if ( empty( $ids ) ) { return false; }
		$revert_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$revert_args = array_merge( array( $now ), $ids );
		$wpdb->last_error = '';
		$compensated = $wpdb->query( $wpdb->prepare( "UPDATE {$t['conflicts']} SET status='open',resolved_by=NULL,resolution_code='',resolved_at=NULL WHERE updated_at=%s AND id IN ({$revert_placeholders})", $revert_args ) );
		return false !== $compensated && empty( $wpdb->last_error ) && absint( $compensated ) === count( $ids );
	}
}
