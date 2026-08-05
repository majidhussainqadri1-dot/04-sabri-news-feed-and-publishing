<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Rollback {
	public static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, 100 );
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_rollback_batch', 'Select at least one migrated legacy publication.', array( 'status' => 400 ) ); }
		if ( ! current_user_can( 'sabri_feed_run_migrations' ) ) { return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability is required.', array( 'status' => 403 ) ); }
		if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) ); }
		$state_check = SNFLA_Schema::assert_current( $expected_state, $expected_version ); if ( is_wp_error( $state_check ) ) { return $state_check; }
		$idempotency_key = trim( (string) $idempotency_key );
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 190 ) { return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable rollback idempotency key of 16–190 characters is required.', array( 'status' => 400 ) ); }
		$signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
		$idempotency_hash = hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) );
		$existing = SNFLA_Migration::existing_run( 'rollback', $idempotency_hash );
		if ( $existing ) {
			if ( ! SNFLA_Integrity::checkpoint_matches( $existing, $legacy_ids, $signature ) ) { return new WP_Error( 'snfla_idempotency_conflict', 'The rollback idempotency key was used for a different source or batch.', array( 'status' => 409 ) ); }
			if ( SNFLA_Integrity::run_is_stale( $existing ) ) {
				if ( ! SNFLA_Migration::finish_run( $existing['run_uuid'], 'interrupted', array( 'error' => 'stale_running_operation' ) ) ) {
					return new WP_Error( 'snfla_stale_run_finalize_failed', 'The stale rollback run could not be marked interrupted; no retry is permitted until the run ledger is repaired.', array( 'status' => 500, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
				}
				return new WP_Error( 'snfla_stale_run_interrupted', 'A stale rollback was marked interrupted. Retry with a new idempotency key.', array( 'status' => 409 ) );
			}
			if ( 'running' === ( $existing['status'] ?? '' ) ) { return new WP_Error( 'snfla_operation_in_progress', 'The matching rollback is still running.', array( 'status' => 423 ) ); }
			if ( in_array( (string) ( $existing['status'] ?? '' ), array( 'failed', 'partial', 'audit_failed', 'interrupted' ), true ) ) { return new WP_Error( 'snfla_idempotent_previous_failure', 'The idempotency key belongs to a previous incomplete rollback; use a new key after remediation.', array( 'status' => 409, 'run_uuid' => $existing['run_uuid'] ?? '' ) ); }
			return array( 'idempotent_replay' => true, 'run' => $existing, 'lifecycle' => SNFLA_Schema::public_status() );
		}
		$blocked = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$map = SNFLA_Mapping::get( $legacy_id ); $target_id = $map ? absint( $map['target_id'] ) : 0;
			if ( ! $map || $target_id <= 0 || ! in_array( $map['status'], array( 'migrated', 'conflict' ), true ) ) { $blocked[ $legacy_id ] = 'mapping_unavailable'; continue; }
			$current_target_checksum = SNFLA_Checksum::post( $target_id );
			if ( '' === $current_target_checksum || empty( $map['target_checksum'] ) || ! hash_equals( (string) $map['target_checksum'], $current_target_checksum ) ) {
				$blocked[ $legacy_id ] = 'target_modified_after_migration';
				SNFLA_Mapping::open_conflict( $legacy_id, 'target_modified_after_migration', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id ), '' );
			}
		}
		if ( ! empty( $blocked ) ) { return new WP_Error( 'snfla_rollback_conflict', 'Rollback stopped because one or more targets are unavailable or changed after migration.', array( 'status' => 409, 'blocked' => $blocked ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'rollback', 5 ) ) { SNFLA_Database::release_lock( 'operation' ); return new WP_Error( 'snfla_rollback_locked', 'Another rollback operation is already running.', array( 'status' => 423 ) ); }
		$run_uuid = wp_generate_uuid4();
		try {
			$locked_state = SNFLA_Schema::assert_current( $expected_state, $expected_version );
			if ( is_wp_error( $locked_state ) ) { return $locked_state; }
			if ( ! SNFLA_Migration::create_run( $run_uuid, 'rollback', 'running', $actor_id, $idempotency_hash, $signature, array( 'legacy_ids' => $legacy_ids ) ) ) { return new WP_Error( 'snfla_run_create_failed', 'The rollback run ledger could not be created.', array( 'status' => 500 ) ); }
			$file21 = SNFLA_File21_Adapter::rollback( $legacy_ids, $actor_id );
			if ( is_wp_error( $file21 ) ) { SNFLA_Migration::finish_run( $run_uuid, 'failed', array( 'error' => $file21->get_error_code() ) ); return $file21; }
			$rolled_ids = SNFLA_Integrity::normalized_ids( array_keys( (array) ( $file21['rolled_back'] ?? array() ) ), 100 );
			$file21_complete = ! empty( $file21['success'] ) && empty( $file21['skipped'] ) && $rolled_ids === $legacy_ids;
			$interaction_reports = array(); $interaction_failures = array();
			foreach ( $rolled_ids as $legacy_id ) {
				$interaction_reports[ $legacy_id ] = SNFLA_Interaction_Provider::rollback( $legacy_id, $actor_id );
				if ( empty( $interaction_reports[ $legacy_id ]['success'] ) ) { $interaction_failures[ $legacy_id ] = $interaction_reports[ $legacy_id ]['errors'] ?? array( 'interaction_rollback_failed' ); }
			}
			$complete = $file21_complete && empty( $interaction_failures );
			foreach ( $legacy_ids as $legacy_id ) {
				$map = SNFLA_Mapping::get( $legacy_id );
				$success_for_id = in_array( $legacy_id, $rolled_ids, true ) && ! isset( $interaction_failures[ $legacy_id ] );
				$status = $complete && $success_for_id ? 'rolled_back' : 'rollback_conflict';
				$code = $success_for_id ? '' : ( isset( $interaction_failures[ $legacy_id ] ) ? 'interaction_rollback_failed' : 'file21_rollback_incomplete' );
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $map['target_id'] ?? 0, 'target_type' => $map['target_type'] ?? '', 'status' => $status, 'source_checksum' => $map['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $map['target_checksum'] ?? '', 'run_uuid' => $run_uuid, 'interaction_ledger' => $complete ? array() : json_decode( $map['interaction_ledger_json'] ?? '{}', true ), 'last_error_code' => $code ) );
				if ( ! $complete ) { SNFLA_Mapping::open_conflict( $legacy_id, $code ?: 'rollback_incomplete', 'blocker', array( 'legacy_id' => $legacy_id, 'file21_rolled_back' => in_array( $legacy_id, $rolled_ids, true ) ), $run_uuid ); }
			}
			if ( ! $complete ) {
				SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'file21' => $file21, 'interaction_failures' => $interaction_failures ) );
				return new WP_Error( 'snfla_partial_rollback', 'Publication or interaction rollback was incomplete and every affected mapping has been quarantined.', array( 'status' => 409, 'interaction_failures' => $interaction_failures, 'file21_skipped' => $file21['skipped'] ?? array() ) );
			}
			$lifecycle = SNFLA_Schema::recover_to_batch( $expected_state, $expected_version, $actor_id, array( 'rollback_count' => count( $legacy_ids ) ) );
			if ( is_wp_error( $lifecycle ) ) {
				foreach ( $legacy_ids as $legacy_id ) {
					$current = SNFLA_Mapping::get( $legacy_id );
					SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $current['target_id'] ?? 0, 'target_type' => $current['target_type'] ?? '', 'status' => 'rollback_conflict', 'source_checksum' => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $current['target_checksum'] ?? '', 'run_uuid' => $run_uuid, 'interaction_ledger' => json_decode( $current['interaction_ledger_json'] ?? '{}', true ), 'last_error_code' => 'rollback_lifecycle_recovery_failed' ) );
					SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_lifecycle_recovery_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid, 'error' => $lifecycle->get_error_code() ), $run_uuid );
				}
				SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => $lifecycle->get_error_code(), 'file21' => $file21, 'interactions' => $interaction_reports ) );
				return new WP_Error( 'snfla_rollback_lifecycle_recovery_failed', 'Rollback mutations completed but lifecycle recovery failed; all affected mappings were quarantined.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}
			$proof = SNFLA_Integrity::sign_evidence( SNFLA_Audit::redact( array( 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'source_signature' => $signature, 'legacy_ids' => $legacy_ids, 'idempotency_hash' => $idempotency_hash, 'file21' => $file21, 'interactions' => $interaction_reports, 'non_destructive' => true, 'run_uuid' => $run_uuid, 'lifecycle' => $lifecycle ) ) );
			if ( ! SNFLA_Migration::finish_run( $run_uuid, 'completed', $proof ) ) { return new WP_Error( 'snfla_run_finish_failed', 'Rollback completed, but its run ledger could not be finalized.', array( 'status' => 500 ) ); }
			$proof_option_recorded = update_option( 'snfla_last_rollback_proof', $proof, false );
			if ( ! SNFLA_Audit::record( 'rollback_completed', $actor_id, array( 'legacy_ids' => $legacy_ids, 'count' => count( $legacy_ids ), 'non_destructive' => true, 'run_uuid' => $run_uuid, 'proof_option_recorded' => (bool) $proof_option_recorded ) ) ) {
				foreach ( $legacy_ids as $legacy_id ) { SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_audit_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid ); }
				return new WP_Error( 'snfla_rollback_audit_failed', 'Rollback completed and is recoverable from the run ledger, but the final audit event failed.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}
			return $proof;
		} finally {
			SNFLA_Database::release_lock( 'rollback' );
			SNFLA_Database::release_lock( 'operation' );
		}
	}
	public static function proof() {
		$proof = get_option( 'snfla_last_rollback_proof', array() );
		if ( SNFLA_Integrity::evidence_valid( $proof ) ) { return $proof; }
		global $wpdb; $t = SNFLA_Database::tables();
		$row = $wpdb->get_row( "SELECT summary_json FROM {$t['runs']} WHERE operation='rollback' AND status='completed' ORDER BY id DESC LIMIT 1", ARRAY_A );
		$proof = is_array( $row ) ? json_decode( (string) $row['summary_json'], true ) : array();
		return SNFLA_Integrity::evidence_valid( $proof ) ? $proof : array();
	}

}
