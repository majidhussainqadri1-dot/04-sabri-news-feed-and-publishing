<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Reconciliation {
	const MAX_REPORT_AGE = DAY_IN_SECONDS;
	public static function run( $actor_id, $expected_state, $expected_version ) {
		global $wpdb;
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) ); }
			$transition = SNFLA_Schema::transition( 'reconciliation', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id );
			if ( is_wp_error( $transition ) ) { return $transition; }
			$legacy_ids = get_posts( array( 'post_type' => SNFLA_Inventory::LEGACY_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			$retained_ids = get_posts( array( 'post_type' => SNFLA_Inventory::LEGACY_POST_TYPE, 'post_status' => array( 'trash' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			$issues = array(); $verified = 0;
			foreach ( array_map( 'absint', (array) $legacy_ids ) as $legacy_id ) {
				$codes = self::current_issue_codes( $legacy_id );
				if ( empty( $codes ) ) { $verified++; continue; }
				foreach ( $codes as $code ) { $issues[] = array( 'legacy_id' => $legacy_id, 'code' => $code ); }
			}
			foreach ( $issues as $issue ) {
				if ( ! SNFLA_Mapping::open_conflict( $issue['legacy_id'], $issue['code'], 'blocker', $issue, '' ) ) {
					return new WP_Error( 'snfla_conflict_persist_failed', 'A reconciliation conflict could not be persisted.', array( 'status' => 500, 'legacy_id' => $issue['legacy_id'] ) );
				}
			}
			$open_conflicts = SNFLA_Mapping::open_conflict_count();
			$audit = SNFLA_Audit::verify_chain();
			if ( empty( $audit['valid'] ) ) {
				$issues[] = array( 'legacy_id' => 0, 'code' => 'audit_chain_invalid' );
				SNFLA_Mapping::open_conflict( 0, 'audit_chain_invalid', 'blocker', array( 'code' => 'audit_chain_invalid', 'error' => $audit['error'] ?? '' ), '' );
				$open_conflicts = SNFLA_Mapping::open_conflict_count();
			}
			$report_uuid = wp_generate_uuid4();
			$report = array(
				'schema'                 => 2,
				'report_uuid'            => $report_uuid,
				'created_at_utc'         => gmdate( 'Y-m-d H:i:s' ),
				'source_total'           => count( $legacy_ids ),
				'retained_source_only'   => count( $retained_ids ),
				'retained_source_ids'    => array_slice( array_map( 'absint', (array) $retained_ids ), 0, 500 ),
				'verified_mappings'      => $verified,
				'issue_count'            => count( $issues ),
				'open_conflicts'         => $open_conflicts,
				'issues'                 => array_slice( $issues, 0, 500 ),
				'audit_chain'            => $audit,
				'green'                  => count( $legacy_ids ) === $verified && 0 === count( $issues ) && 0 === $open_conflicts && ! empty( $audit['valid'] ),
				'source_signature'       => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ),
			);
			$report['report_checksum'] = SNFLA_Checksum::hash( $report );
			$previous_report = get_option( 'snfla_reconciliation_report', array() );
			if ( ! update_option( 'snfla_reconciliation_report', $report, false ) ) { return new WP_Error( 'snfla_reconciliation_persist_failed', 'The reconciliation report could not be persisted.', array( 'status' => 500 ) ); }
			$object_ref = 'reconciliation:' . $report_uuid;
			if ( ! SNFLA_Audit::record( 'reconciliation_completed', $actor_id, array( 'source_total' => $report['source_total'], 'verified_mappings' => $verified, 'issue_count' => count( $issues ), 'open_conflicts' => $open_conflicts, 'green' => $report['green'], 'report_checksum' => $report['report_checksum'] ), $object_ref ) ) {
				update_option( 'snfla_reconciliation_report', $previous_report, false );
				return new WP_Error( 'snfla_reconciliation_audit_failed', 'The reconciliation report was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
			}
			return array( 'report' => $report, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function current_issue_codes( $legacy_id ) {
		$legacy_id = absint( $legacy_id );
		$codes = array();
		$map = SNFLA_Mapping::get( $legacy_id );
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		if ( empty( $map ) || 'migrated' !== ( $map['status'] ?? '' ) || $target_id <= 0 ) { return array( 'missing_active_mapping' ); }
		if ( absint( $map['target_id'] ) !== $target_id || absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id ) { $codes[] = 'mapping_provenance_mismatch'; }
		$source_checksum = SNFLA_Checksum::post( $legacy_id ); $target_checksum = SNFLA_Checksum::post( $target_id );
		if ( empty( $map['source_checksum'] ) || ! hash_equals( (string) $map['source_checksum'], $source_checksum ) ) { $codes[] = 'source_checksum_changed'; }
		if ( empty( $map['target_checksum'] ) || ! hash_equals( (string) $map['target_checksum'], $target_checksum ) ) { $codes[] = 'target_checksum_changed'; }
		if ( ! SNFLA_Checksum::migration_equivalent( $legacy_id, $target_id ) ) { $codes[] = 'publication_projection_mismatch'; }
		$interaction = SNFLA_Interaction_Provider::reconcile( $legacy_id, $target_id );
		if ( empty( $interaction['valid'] ) ) { $codes = array_merge( $codes, (array) $interaction['issues'] ); }
		return array_values( array_unique( array_map( 'sanitize_key', $codes ) ) );
	}

	public static function report() {
		$report = get_option( 'snfla_reconciliation_report', array() ); return is_array( $report ) ? $report : array();
	}

	public static function validate_current_report( $report = null ) {
		$report = null === $report ? self::report() : $report;
		if ( ! SNFLA_Integrity::report_checksum_valid( $report ) || empty( $report['green'] ) || ! empty( $report['open_conflicts'] ) ) { return false; }
		$created = ! empty( $report['created_at_utc'] ) ? strtotime( (string) $report['created_at_utc'] . ' UTC' ) : false;
		if ( false === $created || $created < time() - self::MAX_REPORT_AGE || $created > time() + 300 ) { return false; }
		$report_uuid = sanitize_text_field( (string) ( $report['report_uuid'] ?? '' ) );
		if ( '' === $report_uuid || ! SNFLA_Audit::has_event( 'reconciliation_completed', 'reconciliation:' . $report_uuid, 'report_checksum', (string) $report['report_checksum'] ) ) { return false; }
		$locked = SNFLA_Inventory::locked();
		if ( empty( $locked['source_signature'] ) || ! hash_equals( (string) $locked['source_signature'], (string) ( $report['source_signature'] ?? '' ) ) || ! SNFLA_Inventory::unchanged() ) { return false; }
		if ( 0 !== SNFLA_Mapping::open_conflict_count() ) { return false; }
		$legacy_ids = get_posts( array( 'post_type' => SNFLA_Inventory::LEGACY_POST_TYPE, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
		if ( count( (array) $legacy_ids ) !== absint( $report['source_total'] ?? -1 ) ) { return false; }
		foreach ( array_map( 'absint', (array) $legacy_ids ) as $legacy_id ) {
			if ( ! empty( self::current_issue_codes( $legacy_id ) ) ) { return false; }
		}
		$audit = SNFLA_Audit::verify_chain();
		return ! empty( $audit['valid'] );
	}

	public static function approve_cutover( $actor_id, $expected_state, $expected_version ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$report = self::report();
			if ( ! self::validate_current_report( $report ) ) { return new WP_Error( 'snfla_reconciliation_not_green', 'A fresh, untampered green reconciliation report with zero conflicts and a valid audit chain is required.', array( 'status' => 412 ) ); }
			if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) ); }
			return SNFLA_Schema::transition( 'redirect_cutover', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'reconciliation_checksum' => $report['report_checksum'] ?? '' ) );
		} finally { SNFLA_Database::release_lock( 'operation' ); }
	}

	public static function resolve_conflict( $actor_id, $conflict_id, $resolution_code ) {
		global $wpdb;
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$t = SNFLA_Database::tables(); $conflict_id = absint( $conflict_id ); $resolution_code = sanitize_key( $resolution_code );
			$allowed = array( 'external_remediation_verified', 'canonical_target_repaired', 'mapping_rebuilt', 'source_restored_to_locked_state' );
			if ( $conflict_id <= 0 || ! in_array( $resolution_code, $allowed, true ) ) { return new WP_Error( 'snfla_invalid_conflict_resolution', 'Use an allowed remediation code after the underlying defect has actually been corrected.', array( 'status' => 400 ) ); }
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,legacy_id,conflict_code,status,redacted_context_json FROM {$t['conflicts']} WHERE id=%d", $conflict_id ), ARRAY_A );
			if ( ! is_array( $row ) || 'open' !== $row['status'] ) { return new WP_Error( 'snfla_conflict_unavailable', 'The conflict is unavailable or already resolved.', array( 'status' => 404 ) ); }
			$legacy_id = absint( $row['legacy_id'] ); $code = sanitize_key( $row['conflict_code'] );
			$remaining = 0 === $legacy_id && 'audit_chain_invalid' === $code ? ( empty( SNFLA_Audit::verify_chain()['valid'] ) ? array( 'audit_chain_invalid' ) : array() ) : array_merge( self::current_issue_codes( $legacy_id ), self::candidate_conflicts_if_unmapped( $legacy_id ) );
			if ( in_array( $code, $remaining, true ) ) { return new WP_Error( 'snfla_conflict_still_present', 'The underlying defect is still present; the conflict cannot be administratively dismissed.', array( 'status' => 409, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ); }
			$context = json_decode( (string) $row['redacted_context_json'], true ); $context = is_array( $context ) ? $context : array();
			$context['resolution'] = array( 'code' => $resolution_code, 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'resolved_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
			$updated = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_at' => gmdate( 'Y-m-d H:i:s' ), 'redacted_context_json' => wp_json_encode( SNFLA_Audit::redact( $context ) ) ), array( 'id' => $conflict_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			if ( false === $updated ) { return new WP_Error( 'snfla_conflict_update_failed', 'The conflict resolution could not be recorded.', array( 'status' => 500 ) ); }
			if ( ! SNFLA_Audit::record( 'conflict_resolved', $actor_id, array( 'conflict_id' => $conflict_id, 'legacy_id' => $legacy_id, 'conflict_code' => $code, 'resolution_code' => $resolution_code ), 'conflict:' . $conflict_id ) ) {
				$wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null, 'redacted_context_json' => (string) $row['redacted_context_json'] ), array( 'id' => $conflict_id ) );
				return new WP_Error( 'snfla_conflict_audit_failed', 'Conflict resolution was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
			}
			return array( 'resolved' => true, 'conflict_id' => $conflict_id );
		} finally { SNFLA_Database::release_lock( 'operation' ); }
	}

	private static function candidate_conflicts_if_unmapped( $legacy_id ) {
		$map = SNFLA_Mapping::get( $legacy_id );
		if ( $map && 'migrated' === ( $map['status'] ?? '' ) ) { return array(); }
		return SNFLA_Migration::candidate_conflicts( get_post( $legacy_id ), $legacy_id );
	}
}
