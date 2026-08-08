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
		$current = self::get_checked( $legacy_id );
		if ( is_wp_error( $current ) ) { return false; }
		$now = gmdate( 'Y-m-d H:i:s' );
		$source_checksum = array_key_exists( 'source_checksum', $data ) ? (string) $data['source_checksum'] : (string) ( $current['source_checksum'] ?? '' );
		$target_checksum = array_key_exists( 'target_checksum', $data ) ? (string) $data['target_checksum'] : (string) ( $current['target_checksum'] ?? '' );
		$interaction_json = $current['interaction_ledger_json'] ?? '{}';
		if ( isset( $data['interaction_ledger'] ) ) {
			$interaction_json = wp_json_encode( SNFLA_Audit::redact( (array) $data['interaction_ledger'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( ! is_string( $interaction_json ) ) { return false; }
		}
		$row = array(
			'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
			'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),
			'status'                  => sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) ),
			'source_checksum'         => preg_match( '/^[a-f0-9]{64}$/', $source_checksum ) ? $source_checksum : '',
			'target_checksum'         => preg_match( '/^[a-f0-9]{64}$/', $target_checksum ) ? $target_checksum : '',
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
			$source_signature,
			absint( $legacy_id ),
			$source_checksum,
			sanitize_key( $target_type ) ?: 'auto',
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
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT legacy_id,source_checksum,target_type,eligible,conflict_codes_json,run_uuid FROM {$t['dry_run']} WHERE source_signature=%s AND run_uuid=%s AND legacy_id=%d LIMIT 1",
				(string) $source_signature,
				sanitize_text_field( (string) $run_uuid ),
				absint( $legacy_id )
			),
			ARRAY_A
		);
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'snfla_dry_run_candidate_query_failed', 'Dry-run evidence could not be read safely.', array( 'status' => 500 ) );
		}
		if ( ! is_array( $row ) ) { return array(); }
		$conflicts = json_decode( (string) $row['conflict_codes_json'], true );
		if ( ! is_array( $conflicts ) || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'snfla_dry_run_candidate_corrupt', 'Dry-run conflict evidence is malformed.', array( 'status' => 500 ) );
		}
		$row['conflict_codes'] = $conflicts;
		unset( $row['conflict_codes_json'] );
		return $row;
	}

	public static function dry_run_candidate( $legacy_id, $source_signature, $run_uuid ) {
		$row = self::dry_run_candidate_checked( $legacy_id, $source_signature, $run_uuid );
		return is_wp_error( $row ) ? array() : $row;
	}

	public static function clear_dry_run_rows( $run_uuid = '' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( '' === $run_uuid ) {
			return false !== $wpdb->query( "DELETE FROM {$t['dry_run']}" );
		}
		return false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$t['dry_run']} WHERE run_uuid=%s", $run_uuid ) );
	}

	public static function purge_other_dry_run_rows( $run_uuid ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		return false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$t['dry_run']} WHERE run_uuid<>%s", sanitize_text_field( (string) $run_uuid ) ) );
	}

	public static function supersede_run_conflicts( $run_uuid, $actor_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$run_uuid = sanitize_text_field( (string) $run_uuid );
		if ( '' === $run_uuid ) {
			return true;
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t['conflicts']} SET status='superseded',resolved_at=%s WHERE run_uuid=%s AND status='open'",
				gmdate( 'Y-m-d H:i:s' ),
				$run_uuid
			)
		);
		if ( false === $updated ) {
			return false;
		}
		return 0 === (int) $updated || SNFLA_Audit::record( 'previous_dry_run_conflicts_superseded', $actor_id, array( 'run_uuid' => $run_uuid, 'count' => (int) $updated ), 'dry-run:' . $run_uuid );
	}

	public static function open_conflict( $legacy_id, $code, $severity, array $context, $run_uuid = '' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		$code = sanitize_key( $code );
		$severity = in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) ? $severity : 'high';
		$redacted = SNFLA_Audit::redact( $context );
		$redacted_json = wp_json_encode( $redacted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( '' === $code || ! is_string( $redacted_json ) ) { return false; }
		$fingerprint = SNFLA_Checksum::hash( array( $legacy_id, $code, $redacted ) );
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['conflicts']} (legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid,created_at) VALUES (%d,%s,%s,%s,'open',%s,%s,%s) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status='open',redacted_context_json=VALUES(redacted_context_json),run_uuid=VALUES(run_uuid),resolved_at=NULL",
			$legacy_id,
			$code,
			$severity,
			$fingerprint,
			$redacted_json,
			sanitize_text_field( $run_uuid ),
			gmdate( 'Y-m-d H:i:s' )
		);
		return false !== $wpdb->query( $sql );
	}

	public static function resolve_system_conflicts( $legacy_id, array $codes, $actor_id, $resolution = 'system_verified' ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$codes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $codes ) ) ) );
		if ( empty( $codes ) ) {
			return true;
		}
		$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$args = array_merge( array( gmdate( 'Y-m-d H:i:s' ), absint( $legacy_id ) ), $codes );
		$sql = "UPDATE {$t['conflicts']} SET status='resolved',resolved_at=%s WHERE legacy_id=%d AND status='open' AND conflict_code IN ({$placeholders})";
		$updated = $wpdb->query( $wpdb->prepare( $sql, $args ) );
		if ( false === $updated ) {
			return false;
		}
		return SNFLA_Audit::record( 'system_conflicts_resolved', $actor_id, array( 'legacy_id' => absint( $legacy_id ), 'codes' => $codes, 'resolution' => sanitize_key( $resolution ) ), 'legacy:' . absint( $legacy_id ) );
	}

	/** Stream the complete migration/quarantine route disposition manifest in bounded pages. */
	public static function each_route_disposition_batch( $callback, $page_size = 500 ) {
		global $wpdb;
		$t         = SNFLA_Database::tables();
		$page_size = min( 2000, max( 1, absint( $page_size ) ) );
		$cursor    = 0;
		$total     = 0;
		$migrated  = 0;
		$gone      = 0;
		do {
			$wpdb->last_error = '';
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id,legacy_id,target_id,target_type,status,source_checksum,target_checksum FROM {$t['map']} WHERE id>%d AND status IN ('migrated','quarantined') ORDER BY id ASC LIMIT %d",
					$cursor,
					$page_size
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_route_manifest_query_failed', 'The retirement route manifest could not be read.' );
			}
			$batch = array();
			foreach ( $rows as $row ) {
				$cursor    = max( $cursor, absint( $row['id'] ?? 0 ) );
				$status    = sanitize_key( $row['status'] ?? '' );
				$legacy_id = absint( $row['legacy_id'] ?? 0 );
				$target_id = absint( $row['target_id'] ?? 0 );
				$source_checksum = (string) ( $row['source_checksum'] ?? '' );
				$target_checksum = (string) ( $row['target_checksum'] ?? '' );
				if ( $legacy_id <= 0 || ! in_array( $status, array( 'migrated', 'quarantined' ), true ) || ! preg_match( '/^[a-f0-9]{64}$/', $source_checksum ) || ( 'migrated' === $status && ( $target_id <= 0 || ! preg_match( '/^[a-f0-9]{64}$/', $target_checksum ) ) ) ) {
					return new WP_Error( 'snfla_route_manifest_invalid_row', 'The retirement route manifest contains an invalid or unsigned disposition.', array( 'legacy_id' => $legacy_id ) );
				}
				$entry = array(
					'legacy_id'       => $legacy_id,
					'disposition'     => 'migrated' === $status ? 'redirect' : 'gone',
					'target_id'       => 'migrated' === $status ? $target_id : 0,
					'target_type'     => 'migrated' === $status ? sanitize_key( $row['target_type'] ?? '' ) : '',
					'source_checksum' => $source_checksum,
					'target_checksum' => 'migrated' === $status ? $target_checksum : '',
				);
				$batch[] = $entry;
				$total++;
				if ( 'redirect' === $entry['disposition'] ) { $migrated++; } else { $gone++; }
			}
			if ( ! empty( $batch ) ) {
				$result = call_user_func( $callback, $batch );
				if ( is_wp_error( $result ) || false === $result ) {
					return is_wp_error( $result ) ? $result : new WP_Error( 'snfla_route_manifest_callback_failed', 'The retirement route manifest handoff failed.' );
				}
			}
		} while ( count( $rows ) === $page_size );
		return array( 'count' => $total, 'redirect_count' => $migrated, 'gone_count' => $gone );
	}

	public static function open_conflict_count() {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$value = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
		return ! empty( $wpdb->last_error ) || null === $value ? PHP_INT_MAX : absint( $value );
	}
}
