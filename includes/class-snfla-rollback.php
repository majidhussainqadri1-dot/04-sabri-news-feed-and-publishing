<?php
defined( 'ABSPATH' ) || exit;

/** Non-destructive, idempotent and resumable rollback orchestration. */
final class SNFLA_Rollback {
	const MAX_BATCH = 100;

	public static function execute( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $restore_handover = false, $handover_confirmation = '' ) {
		$legacy_ids = SNFLA_Integrity::strict_positive_ids( $legacy_ids, self::MAX_BATCH );
		if ( is_wp_error( $legacy_ids ) ) { return new WP_Error( 'snfla_invalid_rollback_batch', 'Rollback IDs must be positive, unique and canonical.', array( 'status' => 400 ) ); }
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_rollback_batch', 'Select at least one migrated legacy publication.', array( 'status' => 400 ) ); }
		$authorized_actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $authorized_actor ) || absint( $authorized_actor ) !== absint( $actor_id ) ) { return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability and fresh File 00 authority are required.', array( 'status' => 403 ) ); }
		if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) ); }
		$state_check = SNFLA_Schema::assert_current( $expected_state, $expected_version );
		if ( is_wp_error( $state_check ) ) { return $state_check; }

		$idempotency_key = (string) $idempotency_key;
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 190 || 1 !== preg_match( '/^[!-~]+$/D', $idempotency_key ) ) { return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable exact ASCII rollback idempotency key of 16–190 non-space characters is required.', array( 'status' => 400 ) ); }
		$signature        = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $signature ) || ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_signature_invalid', 'A current locked legacy inventory is required for rollback.', array( 'status' => 412 ) ); }
		$idempotency_hash = hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) );
		$existing = SNFLA_Migration::existing_run( 'rollback', $idempotency_hash );
		if ( is_wp_error( $existing ) ) { return $existing; }
		$existing_result = self::existing_rollback_result( $existing, $legacy_ids, $signature );
		if ( null !== $existing_result ) { return $existing_result; }

		$preflight = self::preflight( $legacy_ids );
		if ( is_wp_error( $preflight ) ) { return $preflight; }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'rollback', 5 ) ) { SNFLA_Database::release_lock( 'operation' ); return new WP_Error( 'snfla_rollback_locked', 'Another rollback operation is already running.', array( 'status' => 423 ) ); }

		$run_uuid = wp_generate_uuid4();
		try {
			$authorized_actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_RUN );
			if ( is_wp_error( $authorized_actor ) || absint( $authorized_actor ) !== absint( $actor_id ) ) {
				return new WP_Error( 'snfla_rollback_authority_changed', 'Rollback authority changed while waiting for the operation lock.', array( 'status' => 403 ) );
			}
			if ( ! SNFLA_Migration::backup_proof_valid() ) {
				return new WP_Error( 'snfla_backup_proof_changed', 'Backup and restore proof changed or expired while waiting for the rollback lock.', array( 'status' => 412 ) );
			}
			$locked_state = SNFLA_Schema::assert_current( $expected_state, $expected_version );
			if ( is_wp_error( $locked_state ) ) { return $locked_state; }
			$current_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $current_signature ) || ! hash_equals( $signature, $current_signature ) || ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_inventory_changed_after_lock', 'The locked legacy inventory changed while waiting for rollback.', array( 'status' => 409 ) );
			}
			$locked_preflight = self::preflight( $legacy_ids );
			if ( is_wp_error( $locked_preflight ) ) { return $locked_preflight; }
			$existing = SNFLA_Migration::existing_run( 'rollback', $idempotency_hash );
			if ( is_wp_error( $existing ) ) { return $existing; }
			$existing_result = self::existing_rollback_result( $existing, $legacy_ids, $signature );
			if ( null !== $existing_result ) { return $existing_result; }
			if ( ! SNFLA_Migration::create_run( $run_uuid, 'rollback', 'running', $actor_id, $idempotency_hash, $signature, array( 'legacy_ids' => $legacy_ids, 'restore_handover' => (bool) $restore_handover ) ) ) {
				return new WP_Error( 'snfla_run_create_failed', 'The rollback run ledger could not be created.', array( 'status' => 500 ) );
			}

			$already_publication_rolled = array();
			$needs_file21               = array();
			foreach ( $legacy_ids as $legacy_id ) {
				if ( SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) { $needs_file21[] = $legacy_id; } else { $already_publication_rolled[] = $legacy_id; }
			}
			$file21 = empty( $needs_file21 )
				? array( 'success' => true, 'rolled_back' => array(), 'skipped' => array(), 'already_rolled_back' => $already_publication_rolled )
				: SNFLA_File21_Adapter::rollback( $needs_file21, $actor_id );
			if ( is_wp_error( $file21 ) ) {
				SNFLA_Migration::finish_run( $run_uuid, 'failed', array( 'error' => $file21->get_error_code() ) );
				return $file21;
			}
			$newly_rolled       = SNFLA_Integrity::normalized_ids( array_keys( (array) ( $file21['rolled_back'] ?? array() ) ), self::MAX_BATCH );
			$publication_rolled = SNFLA_Integrity::normalized_ids( array_merge( $already_publication_rolled, $newly_rolled ), self::MAX_BATCH );
			$publication_missing = array_values( array_diff( $legacy_ids, $publication_rolled ) );

			$checkpoint_failures = array();
			foreach ( $publication_rolled as $legacy_id ) {
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				if ( is_wp_error( $current ) ) {
					$checkpoint_failures[ $legacy_id ] = $current->get_error_code();
					continue;
				}
				$target_id = absint( $current['target_id'] ?? 0 );
				$checkpoint_ok = SNFLA_Mapping::upsert(
					$legacy_id,
					array(
						'target_id'          => $target_id,
						'target_type'        => $current['target_type'] ?? '',
						'status'             => 'publication_rolled_back_interactions_pending',
						'source_checksum'    => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ),
						'target_checksum'    => SNFLA_Checksum::migration_projection_checksum( $target_id, false ),
						'run_uuid'           => $run_uuid,
						'interaction_ledger' => SNFLA_Mapping::progress( $legacy_id ),
						'last_error_code'    => 'interactions_pending',
					)
				);
				if ( ! $checkpoint_ok ) {
					$checkpoint_failures[ $legacy_id ] = 'rollback_checkpoint_persist_failed';
					SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_checkpoint_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
				}
			}
			if ( ! empty( $checkpoint_failures ) ) {
				SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => 'rollback_checkpoint_persist_failed', 'checkpoint_failures' => $checkpoint_failures ) );
				return new WP_Error( 'snfla_rollback_checkpoint_persist_failed', 'Rollback publication state was changed, but the local interaction checkpoint could not be persisted for every record; interaction rollback was not started.', array( 'status' => 500, 'checkpoint_failures' => $checkpoint_failures ) );
			}

			$interaction_reports  = array();
			$interaction_failures = array();
			foreach ( $publication_rolled as $legacy_id ) {
				$interaction_reports[ $legacy_id ] = SNFLA_Interaction_Provider::rollback( $legacy_id, $actor_id );
				if ( empty( $interaction_reports[ $legacy_id ]['success'] ) ) {
					$interaction_failures[ $legacy_id ] = $interaction_reports[ $legacy_id ]['errors'] ?? array( 'interaction_rollback_failed' );
				}
			}

			$complete = empty( $publication_missing ) && empty( $interaction_failures ) && count( $publication_rolled ) === count( $legacy_ids );
			if ( ! $complete ) {
				foreach ( $legacy_ids as $legacy_id ) {
					$current = SNFLA_Mapping::get_checked( $legacy_id );
					if ( is_wp_error( $current ) ) { $current = array(); }
					if ( in_array( $legacy_id, $publication_rolled, true ) ) {
						$code   = isset( $interaction_failures[ $legacy_id ] ) ? 'interaction_rollback_failed' : 'rollback_checkpoint_or_finalize_failed';
						$status = 'publication_rolled_back_interactions_pending';
					} else {
						$code   = 'file21_rollback_incomplete';
						$status = 'rollback_conflict';
					}
					SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $current['target_id'] ?? 0, 'target_type' => $current['target_type'] ?? '', 'status' => $status, 'source_checksum' => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::migration_projection_checksum( absint( $current['target_id'] ?? 0 ), false ), 'run_uuid' => $run_uuid, 'interaction_ledger' => SNFLA_Mapping::progress( $legacy_id ), 'last_error_code' => $code ) );
					SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'publication_rolled_back' => in_array( $legacy_id, $publication_rolled, true ), 'run_uuid' => $run_uuid ), $run_uuid );
				}
				SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'file21' => $file21, 'publication_missing' => $publication_missing, 'interaction_failures' => $interaction_failures ) );
				return new WP_Error( 'snfla_partial_rollback', 'Rollback was checkpointed safely but remains incomplete; retry the affected IDs with a new idempotency key after remediation.', array( 'status' => 409, 'publication_missing' => $publication_missing, 'interaction_failures' => $interaction_failures ) );
			}

			$lifecycle = SNFLA_Schema::recover_to_batch( $expected_state, $expected_version, $actor_id, array( 'rollback_count' => count( $legacy_ids ) ) );
			if ( is_wp_error( $lifecycle ) ) {
				foreach ( $legacy_ids as $legacy_id ) {
					$current = SNFLA_Mapping::get_checked( $legacy_id );
					if ( is_wp_error( $current ) ) { $current = array(); }
					SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $current['target_id'] ?? 0, 'target_type' => $current['target_type'] ?? '', 'status' => 'rollback_conflict', 'source_checksum' => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::migration_projection_checksum( absint( $current['target_id'] ?? 0 ), false ), 'run_uuid' => $run_uuid, 'interaction_ledger' => SNFLA_Mapping::progress( $legacy_id ), 'last_error_code' => 'rollback_lifecycle_recovery_failed' ) );
					SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_lifecycle_recovery_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid, 'error' => $lifecycle->get_error_code() ), $run_uuid );
				}
				SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => $lifecycle->get_error_code(), 'file21' => $file21, 'interactions' => $interaction_reports ) );
				return new WP_Error( 'snfla_rollback_lifecycle_recovery_failed', 'Rollback mutations completed but lifecycle recovery failed; all affected mappings were quarantined.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}

			foreach ( $legacy_ids as $legacy_id ) {
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				if ( is_wp_error( $current ) ) {
					SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_final_mapping_read_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
					SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => 'rollback_final_mapping_read_failed', 'legacy_id' => $legacy_id ) );
					return $current;
				}
				if ( ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $current['target_id'] ?? 0, 'target_type' => $current['target_type'] ?? '', 'status' => 'rolled_back', 'source_checksum' => $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::migration_projection_checksum( absint( $current['target_id'] ?? 0 ), false ), 'run_uuid' => $run_uuid, 'interaction_ledger' => array(), 'last_error_code' => '' ) ) ) {
					SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_final_mapping_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
					SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => 'rollback_final_mapping_persist_failed', 'legacy_id' => $legacy_id ) );
					return new WP_Error( 'snfla_rollback_final_mapping_persist_failed', 'Rollback completed, but final mapping evidence could not be persisted.', array( 'status' => 500, 'legacy_id' => $legacy_id ) );
				}
				SNFLA_Mapping::resolve_system_conflicts( $legacy_id, array( 'interaction_rollback_failed', 'file21_rollback_incomplete', 'rollback_checkpoint_or_finalize_failed' ), $actor_id, 'rollback_completed' );
			}

			$handover = array( 'requested' => (bool) $restore_handover, 'restored' => false );
			if ( $restore_handover ) {
				global $wpdb;
				$tables    = SNFLA_Database::tables();
				$wpdb->last_error = '';
				$remaining_raw = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['map']} WHERE status NOT IN ('rolled_back','quarantined')" );
				if ( ! empty( $wpdb->last_error ) || null === $remaining_raw ) {
					SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => 'handover_remaining_query_failed' ) );
					return new WP_Error( 'snfla_handover_remaining_query_failed', 'Full handover restore was blocked because remaining mappings could not be verified.', array( 'status' => 500 ) );
				}
				$remaining = absint( $remaining_raw );
				if ( $remaining > 0 ) {
					SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => 'handover_restore_blocked_by_remaining_mappings', 'remaining_mappings' => $remaining ) );
					return new WP_Error( 'snfla_handover_restore_blocked', 'Full handover restore requires every migrated File 04 mapping to be rolled back first.', array( 'status' => 409, 'remaining_mappings' => $remaining ) );
				}
				$handover_result = SNFLA_Database::restore_activation_handover( $actor_id, $handover_confirmation );
				if ( is_wp_error( $handover_result ) ) {
					SNFLA_Migration::finish_run( $run_uuid, 'partial', array( 'error' => $handover_result->get_error_code(), 'file21' => $file21, 'interactions' => $interaction_reports ) );
					return $handover_result;
				}
				$handover = array( 'requested' => true, 'restored' => true, 'evidence' => $handover_result );
			}

			$proof = SNFLA_Integrity::sign_evidence( SNFLA_Audit::redact( array( 'performed_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'source_signature' => $signature, 'legacy_ids' => $legacy_ids, 'idempotency_hash' => $idempotency_hash, 'file21' => $file21, 'interactions' => $interaction_reports, 'handover' => $handover, 'non_destructive' => true, 'run_uuid' => $run_uuid, 'lifecycle' => $lifecycle ) ) );
			if ( ! SNFLA_Migration::finish_run( $run_uuid, 'completed', $proof ) ) { return new WP_Error( 'snfla_run_finish_failed', 'Rollback completed, but its run ledger could not be finalized.', array( 'status' => 500 ) ); }
			$proof_option_recorded = update_option( 'snfla_last_rollback_proof', $proof, false ) || get_option( 'snfla_last_rollback_proof', array() ) === $proof;
			if ( ! SNFLA_Audit::record( 'rollback_completed', $actor_id, array( 'legacy_ids' => $legacy_ids, 'count' => count( $legacy_ids ), 'non_destructive' => true, 'run_uuid' => $run_uuid, 'proof_option_recorded' => (bool) $proof_option_recorded ) ) ) {
				foreach ( $legacy_ids as $legacy_id ) { SNFLA_Mapping::open_conflict( $legacy_id, 'rollback_audit_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid ); }
				return new WP_Error( 'snfla_rollback_audit_failed', 'Rollback completed and is recoverable from the run ledger, but the final audit event failed.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}
			if ( ! empty( $handover['restored'] ) ) {
				if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
				if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'plugin_basename' ) || ! function_exists( 'is_plugin_active' ) ) {
					return new WP_Error( 'snfla_handover_deactivation_unavailable', 'The legacy handover was restored, but WordPress plugin deactivation APIs are unavailable.', array( 'status' => 503 ) );
				}
				$plugin = plugin_basename( SNFLA_FILE );
				deactivate_plugins( $plugin, true );
				if ( is_plugin_active( $plugin ) ) {
					return new WP_Error( 'snfla_handover_deactivation_failed', 'The legacy handover was restored, but the adapter could not be deactivated.', array( 'status' => 500 ) );
				}
			}
			return $proof;
		} finally {
			SNFLA_Database::release_lock( 'rollback' );
			SNFLA_Database::release_lock( 'operation' );
		}
	}


	private static function existing_rollback_result( array $existing, array $legacy_ids, $signature ) {
		if ( empty( $existing ) ) { return null; }
		if ( ! SNFLA_Integrity::checkpoint_matches( $existing, $legacy_ids, $signature ) ) {
			return new WP_Error( 'snfla_idempotency_conflict', 'The rollback idempotency key was used for a different source or batch.', array( 'status' => 409 ) );
		}
		if ( SNFLA_Integrity::run_is_stale( $existing ) ) {
			if ( ! SNFLA_Migration::finish_run( $existing['run_uuid'], 'interrupted', array( 'error' => 'stale_running_operation' ) ) ) {
				return new WP_Error( 'snfla_stale_run_finalize_failed', 'The stale rollback run could not be marked interrupted; no retry is permitted until the run ledger is repaired.', array( 'status' => 500, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
			}
			return new WP_Error( 'snfla_stale_run_interrupted', 'A stale rollback was marked interrupted. Retry with a new idempotency key.', array( 'status' => 409 ) );
		}
		if ( 'running' === ( $existing['status'] ?? '' ) ) {
			return new WP_Error( 'snfla_operation_in_progress', 'The matching rollback is still running.', array( 'status' => 423 ) );
		}
		if ( in_array( (string) ( $existing['status'] ?? '' ), array( 'failed', 'partial', 'audit_failed', 'interrupted' ), true ) ) {
			return new WP_Error( 'snfla_idempotent_previous_failure', 'The idempotency key belongs to a previous incomplete rollback; use a new key after remediation.', array( 'status' => 409, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
		}
		return array( 'idempotent_replay' => true, 'run' => $existing, 'lifecycle' => SNFLA_Schema::public_status() );
	}

	private static function preflight( array $legacy_ids ) {
		$blocked = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$map = SNFLA_Mapping::get_checked( $legacy_id );
			if ( is_wp_error( $map ) ) { $blocked[ $legacy_id ] = 'mapping_ledger_read_failed'; continue; }
			$target_id = is_array( $map ) ? absint( $map['target_id'] ?? 0 ) : 0;
			$status    = is_array( $map ) ? sanitize_key( $map['status'] ?? '' ) : '';
			$allowed   = array( 'migrated', 'interaction_pending', 'conflict', 'publication_rolled_back_interactions_pending', 'rollback_conflict' );
			if ( ! is_array( $map ) || $target_id <= 0 || ! in_array( $status, $allowed, true ) ) { $blocked[ $legacy_id ] = 'mapping_unavailable'; continue; }
			$progress = SNFLA_Mapping::progress_checked( $legacy_id );
			if ( is_wp_error( $progress ) ) { $blocked[ $legacy_id ] = 'interaction_progress_corrupt'; continue; }
			$post = get_post( $target_id );
			$publication_already_rolled = in_array( $status, array( 'publication_rolled_back_interactions_pending', 'rollback_conflict' ), true ) && 0 === SNFLA_File21_Adapter::target_for( $legacy_id );
			$target_valid = $publication_already_rolled ? SNFLA_File21_Adapter::rolled_back_target_valid( $legacy_id, $target_id ) : SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id );
			if ( ! $post instanceof WP_Post || ! $target_valid ) { $blocked[ $legacy_id ] = 'target_provenance_failed'; continue; }
			$current_checksum = SNFLA_Checksum::migration_projection_checksum( $target_id, false );
			if ( '' === $current_checksum || empty( $map['target_checksum'] ) || ! hash_equals( (string) $map['target_checksum'], $current_checksum ) ) {
				$blocked[ $legacy_id ] = 'target_modified_after_migration';
				SNFLA_Mapping::open_conflict( $legacy_id, 'target_modified_after_migration', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id ), '' );
				continue;
			}
			$canonical_target = SNFLA_File21_Adapter::target_for( $legacy_id );
			if ( $canonical_target > 0 && $canonical_target !== $target_id ) { $blocked[ $legacy_id ] = 'file21_mapping_mismatch'; continue; }
			if ( 0 === $canonical_target && ! in_array( $status, array( 'publication_rolled_back_interactions_pending', 'rollback_conflict' ), true ) ) { $blocked[ $legacy_id ] = 'unexpected_file21_mapping_absence'; }
		}
		return empty( $blocked ) ? true : new WP_Error( 'snfla_rollback_conflict', 'Rollback stopped because one or more targets are unavailable, changed, or have inconsistent File 21 mapping state.', array( 'status' => 409, 'blocked' => $blocked ) );
	}

	public static function proof() {
		$proof = get_option( 'snfla_last_rollback_proof', array() );
		if ( SNFLA_Integrity::evidence_valid( $proof ) ) { return $proof; }
		global $wpdb;
		$t   = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$row = $wpdb->get_row( "SELECT summary_json FROM {$t['runs']} WHERE operation='rollback' AND status='completed' ORDER BY id DESC LIMIT 1", ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) { return array(); }
		$proof = is_array( $row ) ? json_decode( (string) $row['summary_json'], true ) : array();
		return SNFLA_Integrity::evidence_valid( $proof ) ? $proof : array();
	}
}
