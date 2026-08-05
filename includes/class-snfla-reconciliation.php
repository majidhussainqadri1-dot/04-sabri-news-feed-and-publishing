<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Reconciliation {
	public static function run( $actor_id, $expected_state, $expected_version ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		if ( ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) );
		}
		$transition = SNFLA_Schema::transition( 'reconciliation', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id );
		if ( is_wp_error( $transition ) ) {
			return $transition;
		}
		$legacy_ids = get_posts(
			array(
				'post_type'      => SNFLA_Inventory::LEGACY_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$rows = $wpdb->get_results( "SELECT * FROM {$t['map']} ORDER BY legacy_id ASC", ARRAY_A );
		$by_legacy = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by_legacy[ absint( $row['legacy_id'] ) ] = $row;
		}
		$issues = array();
		$verified = 0;
		foreach ( array_map( 'absint', $legacy_ids ) as $legacy_id ) {
			$map = $by_legacy[ $legacy_id ] ?? array();
			$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
			if ( empty( $map ) || 'migrated' !== ( $map['status'] ?? '' ) || $target_id <= 0 ) {
				$issues[] = array( 'legacy_id' => $legacy_id, 'code' => 'missing_active_mapping' );
				continue;
			}
			if ( absint( $map['target_id'] ) !== $target_id || absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id ) {
				$issues[] = array( 'legacy_id' => $legacy_id, 'code' => 'mapping_provenance_mismatch' );
				continue;
			}
			$source_checksum = SNFLA_Checksum::post( $legacy_id );
			$target_checksum = SNFLA_Checksum::post( $target_id );
			if ( ! hash_equals( (string) $map['source_checksum'], $source_checksum ) ) {
				$issues[] = array( 'legacy_id' => $legacy_id, 'code' => 'source_checksum_changed' );
				continue;
			}
			if ( ! hash_equals( (string) $map['target_checksum'], $target_checksum ) ) {
				$issues[] = array( 'legacy_id' => $legacy_id, 'code' => 'target_checksum_changed' );
				continue;
			}
			$verified++;
		}
		foreach ( $issues as $issue ) {
			SNFLA_Mapping::open_conflict( $issue['legacy_id'], $issue['code'], 'blocker', $issue, '' );
		}
		$open_conflicts = SNFLA_Mapping::open_conflict_count();
		$report = array(
			'schema'             => 1,
			'created_at_utc'     => gmdate( 'Y-m-d H:i:s' ),
			'source_total'       => count( $legacy_ids ),
			'verified_mappings'  => $verified,
			'issue_count'        => count( $issues ),
			'open_conflicts'     => $open_conflicts,
			'issues'             => array_slice( $issues, 0, 500 ),
			'green'              => count( $legacy_ids ) === $verified && 0 === count( $issues ) && 0 === $open_conflicts,
			'source_signature'   => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ),
		);
		$report['report_checksum'] = SNFLA_Checksum::hash( $report );
		update_option( 'snfla_reconciliation_report', $report, false );
		SNFLA_Audit::record( 'reconciliation_completed', $actor_id, array( 'source_total' => $report['source_total'], 'verified_mappings' => $verified, 'issue_count' => count( $issues ), 'open_conflicts' => $open_conflicts, 'green' => $report['green'], 'report_checksum' => $report['report_checksum'] ) );
		return array( 'report' => $report, 'lifecycle' => $transition );
	}

	public static function report() {
		$report = get_option( 'snfla_reconciliation_report', array() );
		return is_array( $report ) ? $report : array();
	}

	public static function approve_cutover( $actor_id, $expected_state, $expected_version ) {
		$report = self::report();
		if ( empty( $report['green'] ) || ! empty( $report['open_conflicts'] ) ) {
			return new WP_Error( 'snfla_reconciliation_not_green', 'A green reconciliation report with zero open conflicts is required.', array( 'status' => 412 ) );
		}
		if ( ! SNFLA_Migration::backup_proof_valid() ) {
			return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) );
		}
		return SNFLA_Schema::transition( 'redirect_cutover', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'reconciliation_checksum' => $report['report_checksum'] ?? '' ) );
	}

	public static function resolve_conflict( $actor_id, $conflict_id, $resolution_code ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$conflict_id = absint( $conflict_id );
		$resolution_code = sanitize_key( $resolution_code );
		if ( $conflict_id <= 0 || '' === $resolution_code ) {
			return new WP_Error( 'snfla_invalid_conflict_resolution', 'A conflict and resolution code are required.', array( 'status' => 400 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,legacy_id,conflict_code,status FROM {$t['conflicts']} WHERE id=%d", $conflict_id ), ARRAY_A );
		if ( ! is_array( $row ) || 'open' !== $row['status'] ) {
			return new WP_Error( 'snfla_conflict_unavailable', 'The conflict is unavailable or already resolved.', array( 'status' => 404 ) );
		}
		$updated = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_at' => gmdate( 'Y-m-d H:i:s' ), 'redacted_context_json' => wp_json_encode( array( 'resolution_code' => $resolution_code ) ) ), array( 'id' => $conflict_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'snfla_conflict_update_failed', 'The conflict resolution could not be recorded.', array( 'status' => 500 ) );
		}
		SNFLA_Audit::record( 'conflict_resolved', $actor_id, array( 'conflict_id' => $conflict_id, 'legacy_id' => absint( $row['legacy_id'] ), 'conflict_code' => $row['conflict_code'], 'resolution_code' => $resolution_code ), 'conflict:' . $conflict_id );
		return array( 'resolved' => true, 'conflict_id' => $conflict_id );
	}
}
