<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Mapping {
	public static function get( $legacy_id ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['map']} WHERE legacy_id=%d", absint( $legacy_id ) ), ARRAY_A );
	}

	public static function upsert( $legacy_id, array $data ) {
		global $wpdb;
		$t        = SNFLA_Database::tables();
		$legacy_id = absint( $legacy_id );
		$current  = self::get( $legacy_id );
		$now      = gmdate( 'Y-m-d H:i:s' );
		$source_checksum = array_key_exists( 'source_checksum', $data ) ? (string) $data['source_checksum'] : (string) ( $current['source_checksum'] ?? '' );
		$target_checksum = array_key_exists( 'target_checksum', $data ) ? (string) $data['target_checksum'] : (string) ( $current['target_checksum'] ?? '' );
		$row      = array(
			'target_id'               => absint( array_key_exists( 'target_id', $data ) ? $data['target_id'] : ( $current['target_id'] ?? 0 ) ),
			'target_type'             => sanitize_key( array_key_exists( 'target_type', $data ) ? $data['target_type'] : ( $current['target_type'] ?? '' ) ),
			'status'                  => sanitize_key( array_key_exists( 'status', $data ) ? $data['status'] : ( $current['status'] ?? 'pending' ) ),
			'source_checksum'         => preg_match( '/^[a-f0-9]{64}$/', $source_checksum ) ? $source_checksum : '',
			'target_checksum'         => preg_match( '/^[a-f0-9]{64}$/', $target_checksum ) ? $target_checksum : '',
			'run_uuid'                => sanitize_text_field( array_key_exists( 'run_uuid', $data ) ? $data['run_uuid'] : ( $current['run_uuid'] ?? '' ) ),
			'interaction_ledger_json' => isset( $data['interaction_ledger'] ) ? wp_json_encode( $data['interaction_ledger'] ) : ( $current['interaction_ledger_json'] ?? '{}' ),
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

	public static function append_interaction_row( $legacy_id, $target_id, $slug, $row_id, $original = array() ) {
		$current = self::get( $legacy_id );
		$ledger  = $current && ! empty( $current['interaction_ledger_json'] ) ? json_decode( $current['interaction_ledger_json'], true ) : array();
		$ledger  = is_array( $ledger ) ? $ledger : array();
		$slug    = sanitize_key( $slug );
		$original = is_array( $original ) ? SNFLA_Audit::redact( $original ) : array( 'status' => sanitize_key( $original ) );
		$ledger[ $slug ] = isset( $ledger[ $slug ] ) && is_array( $ledger[ $slug ] ) ? $ledger[ $slug ] : array();
		$entry = array( 'id' => absint( $row_id ), 'original' => $original, 'target_id' => absint( $target_id ) );
		$already_recorded = false;
		foreach ( $ledger[ $slug ] as $existing ) {
			if ( absint( $existing['id'] ?? 0 ) === absint( $row_id ) ) { $already_recorded = true; break; }
		}
		if ( ! $already_recorded ) { $ledger[ $slug ][] = $entry; }
		$ledger[ $slug ] = array_slice( $ledger[ $slug ], -10000 );
		return self::upsert(
			$legacy_id,
			array(
				'target_id'       => $target_id,
				'target_type'     => $current['target_type'] ?? 'post',
				'status'          => $current['status'] ?? 'migrating',
				'source_checksum' => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ),
				'target_checksum' => $current['target_checksum'] ?? '',
				'run_uuid'        => $current['run_uuid'] ?? '',
				'interaction_ledger' => $ledger,
			)
		);
	}

	public static function open_conflict( $legacy_id, $code, $severity, array $context, $run_uuid = '' ) {
		global $wpdb;
		$t           = SNFLA_Database::tables();
		$legacy_id   = absint( $legacy_id );
		$code        = sanitize_key( $code );
		$severity    = in_array( $severity, array( 'low', 'medium', 'high', 'blocker' ), true ) ? $severity : 'high';
		$redacted    = SNFLA_Audit::redact( $context );
		$fingerprint = SNFLA_Checksum::hash( array( $legacy_id, $code, $redacted ) );
		$sql = $wpdb->prepare(
			"INSERT INTO {$t['conflicts']} (legacy_id,conflict_code,severity,fingerprint,status,redacted_context_json,run_uuid,created_at) VALUES (%d,%s,%s,%s,'open',%s,%s,%s) ON DUPLICATE KEY UPDATE severity=VALUES(severity),status='open',redacted_context_json=VALUES(redacted_context_json),run_uuid=VALUES(run_uuid),resolved_at=NULL",
			$legacy_id,
			$code,
			$severity,
			$fingerprint,
			wp_json_encode( $redacted ),
			sanitize_text_field( $run_uuid ),
			gmdate( 'Y-m-d H:i:s' )
		);
		return false !== $wpdb->query( $sql );
	}

	public static function open_conflict_count() {
		global $wpdb;
		$t = SNFLA_Database::tables();
		return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" ) );
	}
}
