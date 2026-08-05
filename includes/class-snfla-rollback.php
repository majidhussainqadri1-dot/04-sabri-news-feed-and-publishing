<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Rollback {
	public static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version ) {
		$legacy_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $legacy_ids ) ) ) ), 0, 100 );
		if ( empty( $legacy_ids ) ) {
			return new WP_Error( 'snfla_empty_rollback_batch', 'Select at least one migrated legacy publication.', array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'sabri_feed_run_migrations' ) ) {
			return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability is required.', array( 'status' => 403 ) );
		}
		if ( ! SNFLA_Migration::backup_proof_valid() ) {
			return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) );
		}
		$state_check = SNFLA_Schema::assert_current( $expected_state, $expected_version );
		if ( is_wp_error( $state_check ) ) {
			return $state_check;
		}
		$idempotency_key = trim( (string) $idempotency_key );
		if ( strlen( $idempotency_key ) < 16 ) {
			return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable rollback idempotency key is required.', array( 'status' => 400 ) );
		}
		$blocked = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$map = SNFLA_Mapping::get( $legacy_id );
			$target_id = $map ? absint( $map['target_id'] ) : 0;
			if ( ! $map || $target_id <= 0 || ! in_array( $map['status'], array( 'migrated', 'conflict' ), true ) ) {
				$blocked[ $legacy_id ] = 'mapping_unavailable';
				continue;
			}
			$current_target_checksum = SNFLA_Checksum::post( $target_id );
			if ( '' === $current_target_checksum || ! hash_equals( (string) $map['target_checksum'], $current_target_checksum ) ) {
				$blocked[ $legacy_id ] = 'target_modified_after_migration';
				SNFLA_Mapping::open_conflict( $legacy_id, 'target_modified_after_migration', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id ), '' );
			}
		}
		if ( ! empty( $blocked ) ) {
			return new WP_Error( 'snfla_rollback_conflict', 'Rollback stopped because one or more targets changed after migration.', array( 'status' => 409, 'blocked' => $blocked ) );
		}
		if ( ! SNFLA_Database::acquire_lock( 'rollback', 5 ) ) {
			return new WP_Error( 'snfla_rollback_locked', 'Another rollback operation is already running.', array( 'status' => 423 ) );
		}
		try {
			$file21 = SNFLA_File21_Adapter::rollback( $legacy_ids, $actor_id );
			if ( is_wp_error( $file21 ) ) {
				return $file21;
			}
			$interaction_reports = array();
			$interaction_failures = array();
			foreach ( $legacy_ids as $legacy_id ) {
				$interaction_reports[ $legacy_id ] = SNFLA_Interaction_Provider::rollback( $legacy_id, $actor_id );
				if ( empty( $interaction_reports[ $legacy_id ]['success'] ) ) {
					$interaction_failures[ $legacy_id ] = $interaction_reports[ $legacy_id ]['errors'] ?? array( 'interaction_rollback_failed' );
					SNFLA_Mapping::open_conflict( $legacy_id, 'interaction_rollback_failed', 'blocker', array( 'legacy_id' => $legacy_id ), '' );
					continue;
				}
				$map = SNFLA_Mapping::get( $legacy_id );
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $map['target_id'] ?? 0, 'target_type' => $map['target_type'] ?? '', 'status' => 'rolled_back', 'source_checksum' => $map['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $map['target_checksum'] ?? '', 'run_uuid' => $map['run_uuid'] ?? '', 'interaction_ledger' => array(), 'last_error_code' => '' ) );
			}
			if ( ! empty( $interaction_failures ) || empty( $file21['rolled_back'] ) ) {
				return new WP_Error( 'snfla_partial_rollback', 'Publication or interaction rollback was incomplete and has been quarantined.', array( 'status' => 409, 'interaction_failures' => $interaction_failures, 'file21_skipped' => $file21['skipped'] ?? array() ) );
			}
			$proof = array( 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'legacy_ids' => $legacy_ids, 'idempotency_hash' => hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) ), 'file21' => $file21, 'interactions' => $interaction_reports, 'non_destructive' => true );
			update_option( 'snfla_last_rollback_proof', SNFLA_Audit::redact( $proof ), false );
			$lifecycle = SNFLA_Schema::recover_to_batch( $expected_state, $expected_version, $actor_id, array( 'rollback_count' => count( $legacy_ids ) ) );
			if ( is_wp_error( $lifecycle ) ) {
				return $lifecycle;
			}
			SNFLA_Audit::record( 'rollback_completed', $actor_id, array( 'legacy_ids' => $legacy_ids, 'count' => count( $legacy_ids ), 'non_destructive' => true ) );
			$proof['lifecycle'] = $lifecycle;
			return $proof;
		} finally {
			SNFLA_Database::release_lock( 'rollback' );
		}
	}
}
