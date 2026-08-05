<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Migration {
	const MAX_BATCH = 100;

	public static function dry_run( $actor_id, $limit = 100, $expected_state = 'inventory_locked', $expected_version = 1 ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			$check = SNFLA_Schema::assert_current( $expected_state, $expected_version );
			if ( is_wp_error( $check ) ) { return $check; }
			if ( ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock. Re-lock before continuing.', array( 'status' => 409 ) );
			}
			$preview = SNFLA_File21_Adapter::preview( min( self::MAX_BATCH, max( 1, absint( $limit ) ) ) );
			if ( is_wp_error( $preview ) ) { return $preview; }
			$locked = SNFLA_Inventory::locked();
			$report = array(
				'schema'               => 2,
				'created_at_utc'       => gmdate( 'Y-m-d H:i:s' ),
				'source_signature'     => (string) ( $locked['source_signature'] ?? '' ),
				'candidate_count'      => absint( $preview['candidate_count'] ?? 0 ),
				'candidates'           => array(),
				'conflicts'            => array(),
				'destructive'          => false,
				'canonical_owner'      => 'File 21',
				'interaction_provider' => SNFLA_File21_Adapter::INTERACTION_PROVIDER,
			);
			foreach ( (array) ( $preview['candidates'] ?? array() ) as $candidate ) {
				$legacy_id = absint( is_array( $candidate ) ? ( $candidate['legacy_id'] ?? $candidate['id'] ?? 0 ) : 0 );
				if ( $legacy_id <= 0 ) { continue; }
				$conflicts = self::candidate_conflicts( get_post( $legacy_id ), $legacy_id );
				$report['candidates'][] = array(
					'legacy_id'       => $legacy_id,
					'source_checksum' => SNFLA_Checksum::post( $legacy_id ),
					'target_type'     => isset( $candidate['target_type'] ) ? sanitize_key( $candidate['target_type'] ) : 'auto',
					'conflict_codes'  => $conflicts,
				);
				foreach ( $conflicts as $code ) {
					if ( ! SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'code' => $code ), '' ) ) {
						return new WP_Error( 'snfla_conflict_persist_failed', 'A dry-run conflict could not be persisted.', array( 'status' => 500, 'legacy_id' => $legacy_id ) );
					}
					$report['conflicts'][] = array( 'legacy_id' => $legacy_id, 'code' => $code );
				}
			}
			$report['report_checksum'] = SNFLA_Checksum::hash( $report );
			$previous = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
			if ( ! update_option( SNFLA_Schema::DRY_RUN_OPTION, $report, false ) ) {
				return new WP_Error( 'snfla_dry_run_persist_failed', 'The dry-run report could not be persisted.', array( 'status' => 500 ) );
			}
			$transition = SNFLA_Schema::transition( 'dry_run_ready', $expected_state, absint( $expected_version ), $actor_id, array( 'report_checksum' => $report['report_checksum'] ) );
			if ( is_wp_error( $transition ) ) {
				update_option( SNFLA_Schema::DRY_RUN_OPTION, $previous, false );
				return $transition;
			}
			return array( 'report' => $report, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function candidate_conflicts( $post, $legacy_id ) {
		$codes = array();
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) { return array( 'invalid_legacy_publication' ); }
		if ( '' === trim( (string) $post->post_title ) || '' === trim( wp_strip_all_tags( (string) $post->post_content ) ) ) { $codes[] = 'missing_required_content'; }
		if ( absint( $post->post_author ) <= 0 || ! get_userdata( absint( $post->post_author ) ) ) { $codes[] = 'author_missing'; }
		if ( SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) { $codes[] = 'already_migrated'; }
		$terms = wp_get_object_terms( $legacy_id, SNFLA_Inventory::LEGACY_TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) { $codes[] = 'taxonomy_read_failed'; }
		if ( in_array( 'patient-cases', is_array( $terms ) ? $terms : array(), true ) && '' === (string) get_post_meta( $legacy_id, '_snp_case_consent_record_id', true ) ) {
			$codes[] = 'patient_case_consent_evidence_missing';
		}
		return array_values( array_unique( $codes ) );
	}

	public static function record_backup_proof( $actor_id, $reference, $checksum, $created_at_utc ) {
		$reference = sanitize_text_field( $reference );
		$checksum  = strtolower( sanitize_text_field( $checksum ) );
		$timestamp = strtotime( sanitize_text_field( $created_at_utc ) . ' UTC' );
		if ( '' === $reference || ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) || false === $timestamp || $timestamp < time() - 7 * DAY_IN_SECONDS || $timestamp > time() + 300 ) {
			return new WP_Error( 'snfla_invalid_backup_proof', 'A recent backup/restore proof reference and SHA-256 are required.', array( 'status' => 400 ) );
		}
		$locked = SNFLA_Inventory::locked();
		$source_signature = (string) ( $locked['source_signature'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $source_signature ) || ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_required', 'Lock an unchanged legacy inventory before recording backup proof.', array( 'status' => 412 ) );
		}
		$proof = SNFLA_Integrity::sign_evidence(
			array(
				'reference_hash'   => hash( 'sha256', $reference ),
				'reference_length' => strlen( $reference ),
				'checksum'         => $checksum,
				'source_signature' => $source_signature,
				'created_at_utc'   => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'recorded_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
				'actor_digest'     => SNFLA_Audit::actor_digest( $actor_id ),
			)
		);
		$previous = get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() );
		if ( ! update_option( SNFLA_Schema::BACKUP_PROOF_OPTION, $proof, false ) ) {
			return new WP_Error( 'snfla_backup_proof_persist_failed', 'Backup proof could not be persisted.', array( 'status' => 500 ) );
		}
		if ( ! SNFLA_Audit::record( 'backup_proof_recorded', $actor_id, array( 'reference_hash' => hash( 'sha256', $reference ), 'checksum' => $checksum, 'source_signature' => $source_signature, 'created_at_utc' => $proof['created_at_utc'] ), 'backup:' . $source_signature ) ) {
			update_option( SNFLA_Schema::BACKUP_PROOF_OPTION, $previous, false );
			return new WP_Error( 'snfla_backup_proof_audit_failed', 'Backup proof was reverted because its audit evidence could not be written.', array( 'status' => 500 ) );
		}
		return $proof;
	}

	public static function backup_proof_valid() {
		$proof = get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() );
		if ( ! SNFLA_Integrity::evidence_valid( $proof ) || empty( $proof['checksum'] ) || empty( $proof['created_at_utc'] ) ) { return false; }
		$timestamp = strtotime( $proof['created_at_utc'] . ' UTC' );
		$locked = SNFLA_Inventory::locked();
		$source_signature = (string) ( $locked['source_signature'] ?? '' );
		return preg_match( '/^[a-f0-9]{64}$/', (string) $proof['checksum'] )
			&& preg_match( '/^[a-f0-9]{64}$/', $source_signature )
			&& hash_equals( $source_signature, (string) ( $proof['source_signature'] ?? '' ) )
			&& SNFLA_Audit::has_event( 'backup_proof_recorded', 'backup:' . $source_signature, 'checksum', (string) $proof['checksum'] )
			&& SNFLA_Inventory::unchanged()
			&& false !== $timestamp
			&& $timestamp >= time() - 7 * DAY_IN_SECONDS
			&& $timestamp <= time() + 300;
	}

	public static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }
		if ( ! current_user_can( 'sabri_feed_run_migrations' ) ) { return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability is required.', array( 'status' => 403 ) ); }
		if ( ! self::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required before migration.', array( 'status' => 412 ) ); }
		if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) ); }
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$locked = SNFLA_Inventory::locked();
		if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) ( $locked['source_signature'] ?? '' ), (string) $dry['source_signature'] ) || ! SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'report_checksum', (string) ( $dry['report_checksum'] ?? '' ) ) ) {
			return new WP_Error( 'snfla_dry_run_required', 'An untampered matching dry-run report is required before migration.', array( 'status' => 412 ) );
		}
		$dry_candidates = array();
		foreach ( (array) ( $dry['candidates'] ?? array() ) as $candidate ) {
			$id = absint( $candidate['legacy_id'] ?? 0 );
			if ( $id > 0 ) { $dry_candidates[ $id ] = $candidate; }
		}
		foreach ( $legacy_ids as $legacy_id ) {
			$current_checksum = SNFLA_Checksum::post( $legacy_id );
			$candidate = $dry_candidates[ $legacy_id ] ?? array();
			if ( empty( $candidate['source_checksum'] ) || ! hash_equals( (string) $candidate['source_checksum'], $current_checksum ) ) {
				return new WP_Error( 'snfla_dry_run_batch_mismatch', 'Every selected legacy ID must appear unchanged in the current dry-run batch.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
			}
			if ( ! empty( $candidate['conflict_codes'] ) ) {
				return new WP_Error( 'snfla_candidate_conflicted', 'A selected dry-run candidate has unresolved blockers.', array( 'status' => 409, 'legacy_id' => $legacy_id, 'conflict_codes' => $candidate['conflict_codes'] ) );
			}
		}
		$idempotency_key = trim( (string) $idempotency_key );
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 190 ) { return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable idempotency key of 16–190 characters is required.', array( 'status' => 400 ) ); }
		$idempotency_hash = hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) );
		$existing = self::existing_run( 'migrate', $idempotency_hash );
		if ( $existing ) {
			if ( ! SNFLA_Integrity::checkpoint_matches( $existing, $legacy_ids, (string) ( $locked['source_signature'] ?? '' ) ) ) {
				return new WP_Error( 'snfla_idempotency_conflict', 'The idempotency key was already used for a different source or batch.', array( 'status' => 409 ) );
			}
			if ( SNFLA_Integrity::run_is_stale( $existing ) ) {
				if ( ! self::finish_run( $existing['run_uuid'], 'interrupted', array( 'error' => 'stale_running_operation' ) ) ) {
					return new WP_Error( 'snfla_stale_run_finalize_failed', 'The stale migration run could not be marked interrupted; no retry is permitted until the run ledger is repaired.', array( 'status' => 500, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
				}
				return new WP_Error( 'snfla_stale_run_interrupted', 'A stale running operation was marked interrupted. Retry with a new idempotency key.', array( 'status' => 409 ) );
			}
			if ( 'running' === ( $existing['status'] ?? '' ) ) { return new WP_Error( 'snfla_operation_in_progress', 'The matching migration is still running.', array( 'status' => 423 ) ); }
			if ( in_array( (string) ( $existing['status'] ?? '' ), array( 'failed', 'audit_failed', 'interrupted' ), true ) ) { return new WP_Error( 'snfla_idempotent_previous_failure', 'The idempotency key belongs to a previous failed or interrupted migration; use a new key after remediation.', array( 'status' => 409, 'run_uuid' => $existing['run_uuid'] ?? '' ) ); }
			return array( 'idempotent_replay' => true, 'run' => $existing, 'lifecycle' => SNFLA_Schema::public_status() );
		}
		if ( SNFLA_Mapping::open_conflict_count() > 0 ) { return new WP_Error( 'snfla_open_conflicts', 'Open migration conflicts must be resolved before migration.', array( 'status' => 409 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'migration', 5 ) ) { SNFLA_Database::release_lock( 'operation' ); return new WP_Error( 'snfla_migration_locked', 'Another migration operation is already running.', array( 'status' => 423 ) ); }
		try {
			$run_uuid = wp_generate_uuid4();
			if ( ! self::create_run( $run_uuid, 'migrate', 'running', $actor_id, $idempotency_hash, (string) $locked['source_signature'], array( 'legacy_ids' => $legacy_ids ) ) ) {
				return new WP_Error( 'snfla_run_create_failed', 'The migration run ledger could not be created.', array( 'status' => 500 ) );
			}
			$transition = SNFLA_Schema::transition( 'batch_migration', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'batch_size' => count( $legacy_ids ), 'run_uuid' => $run_uuid ) );
			if ( is_wp_error( $transition ) ) {
				self::finish_run( $run_uuid, 'failed', array( 'error' => $transition->get_error_code() ) );
				return $transition;
			}
			$eligible = array();
			$skipped = array();
			foreach ( $legacy_ids as $legacy_id ) {
				$checksum = SNFLA_Checksum::post( $legacy_id );
				$current = SNFLA_Mapping::get( $legacy_id );
				$target = SNFLA_File21_Adapter::target_for( $legacy_id );
				if ( $target > 0 && $current && ! empty( $current['source_checksum'] ) && hash_equals( (string) $current['source_checksum'], $checksum ) ) { $skipped[ $legacy_id ] = 'already_migrated_same_checksum'; continue; }
				if ( $target > 0 || ( $current && ! empty( $current['source_checksum'] ) && ! hash_equals( (string) $current['source_checksum'], $checksum ) ) ) {
					SNFLA_Mapping::open_conflict( $legacy_id, 'source_or_mapping_changed', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target ), $run_uuid );
					$skipped[ $legacy_id ] = 'source_or_mapping_changed'; continue;
				}
				if ( ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => 0, 'target_type' => '', 'status' => 'migrating', 'source_checksum' => $checksum, 'run_uuid' => $run_uuid ) ) ) {
					SNFLA_Mapping::open_conflict( $legacy_id, 'mapping_preflight_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id ), $run_uuid );
					$skipped[ $legacy_id ] = 'mapping_preflight_persist_failed'; continue;
				}
				$eligible[] = $legacy_id;
			}
			$only_idempotent_skips = ! empty( $skipped ) && empty( array_filter( $skipped, static function ( $code ) { return 'already_migrated_same_checksum' !== $code; } ) );
			$result = empty( $eligible ) ? array( 'success' => $only_idempotent_skips || empty( $skipped ), 'migrated' => array(), 'skipped' => array(), 'warnings' => array() ) : SNFLA_File21_Adapter::migrate( $eligible, $actor_id, $with_interactions );
			if ( is_wp_error( $result ) ) {
				self::quarantine_batch( $eligible, $result->get_error_code(), $run_uuid );
				self::finish_run( $run_uuid, 'failed', array( 'error' => $result->get_error_code() ) );
				return $result;
			}
			if ( ! is_array( $result ) || ( empty( $result['success'] ) && empty( $result['migrated'] ) ) ) {
				$error_code = is_array( $result ) ? sanitize_key( $result['error'] ?? 'file21_migration_failed' ) : 'file21_invalid_response';
				self::quarantine_batch( $eligible, $error_code, $run_uuid );
				self::finish_run( $run_uuid, 'failed', array( 'error' => $error_code ) );
				return new WP_Error( 'snfla_file21_migration_failed', 'Canonical File 21 did not complete the requested migration.', array( 'status' => 409, 'file21_error' => $error_code ) );
			}
			$mapping_failures = array();
			foreach ( (array) ( $result['migrated'] ?? array() ) as $legacy_id => $row ) {
				$legacy_id = absint( $legacy_id ); $target_id = absint( $row['target_id'] ?? 0 );
				if ( $target_id <= 0 || ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $target_id, 'target_type' => sanitize_key( $row['target_type'] ?? '' ), 'status' => 'migrated', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::post( $target_id ), 'run_uuid' => $run_uuid ) ) ) {
					$mapping_failures[ $legacy_id ] = 'mapping_final_persist_failed';
					SNFLA_Mapping::open_conflict( $legacy_id, 'mapping_final_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id ), $run_uuid );
				}
			}
			$conflict_outcomes = array_merge( (array) ( $result['skipped'] ?? array() ), (array) ( $result['warnings'] ?? array() ), $mapping_failures );
			foreach ( $skipped as $legacy_id => $code ) { if ( 'already_migrated_same_checksum' !== $code ) { $conflict_outcomes[ $legacy_id ] = $code; } }
			foreach ( $conflict_outcomes as $legacy_id => $code ) {
				$legacy_id = absint( $legacy_id ); if ( $legacy_id <= 0 ) { continue; }
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'status' => 'conflict', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'run_uuid' => $run_uuid, 'last_error_code' => sanitize_key( is_array( $code ) ? 'file21_warning' : $code ) ) );
				SNFLA_Mapping::open_conflict( $legacy_id, sanitize_key( is_array( $code ) ? 'file21_warning' : $code ), 'high', array( 'legacy_id' => $legacy_id, 'code' => $code ), $run_uuid );
			}
			$status = ( $only_idempotent_skips && empty( $eligible ) ) || ( empty( $result['skipped'] ) && empty( $result['warnings'] ) && empty( $conflict_outcomes ) ) ? 'completed' : ( ! empty( $result['migrated'] ) ? 'partial' : 'failed' );
			$summary = array( 'file21_report' => $result, 'preflight_skipped' => $skipped, 'mapping_failures' => $mapping_failures, 'run_uuid' => $run_uuid );
			if ( ! self::finish_run( $run_uuid, $status, $summary ) ) {
				self::quarantine_batch( array_keys( (array) ( $result['migrated'] ?? array() ) ), 'run_ledger_finalize_failed', $run_uuid );
				return new WP_Error( 'snfla_run_finish_failed', 'Migration targets were quarantined because the run ledger could not be finalized.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}
			if ( ! SNFLA_Audit::record( 'migration_batch_completed', $actor_id, array( 'run_uuid' => $run_uuid, 'status' => $status, 'migrated_count' => count( (array) ( $result['migrated'] ?? array() ) ), 'skipped_count' => count( $skipped ) + count( (array) ( $result['skipped'] ?? array() ) ) ), 'run:' . $run_uuid ) ) {
				self::finish_run( $run_uuid, 'audit_failed', array_merge( $summary, array( 'error' => 'migration_audit_failed' ) ) );
				foreach ( array_keys( (array) ( $result['migrated'] ?? array() ) ) as $legacy_id ) {
					SNFLA_Mapping::upsert( absint( $legacy_id ), array( 'status' => 'conflict', 'last_error_code' => 'migration_audit_failed', 'run_uuid' => $run_uuid ) );
					SNFLA_Mapping::open_conflict( absint( $legacy_id ), 'migration_audit_failed', 'blocker', array( 'legacy_id' => absint( $legacy_id ), 'run_uuid' => $run_uuid ), $run_uuid );
				}
				return new WP_Error( 'snfla_migration_audit_failed', 'Migration targets were quarantined because the final audit event could not be written.', array( 'status' => 500, 'run_uuid' => $run_uuid ) );
			}
			return array( 'idempotent_replay' => false, 'run_uuid' => $run_uuid, 'status' => $status, 'report' => $summary, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'migration' );
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	private static function quarantine_batch( array $legacy_ids, $code, $run_uuid ) {
		$code = sanitize_key( $code ) ?: 'migration_failed';
		foreach ( SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH ) as $legacy_id ) {
			$current = SNFLA_Mapping::get( $legacy_id );
			SNFLA_Mapping::upsert(
				$legacy_id,
				array(
					'target_id'       => absint( $current['target_id'] ?? SNFLA_File21_Adapter::target_for( $legacy_id ) ),
					'target_type'     => sanitize_key( $current['target_type'] ?? '' ),
					'status'          => 'conflict',
					'source_checksum' => (string) ( $current['source_checksum'] ?? SNFLA_Checksum::post( $legacy_id ) ),
					'target_checksum' => (string) ( $current['target_checksum'] ?? '' ),
					'run_uuid'        => sanitize_text_field( $run_uuid ),
					'last_error_code' => $code,
				)
			);
			SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
		}
	}

	public static function create_run( $uuid, $operation, $status, $actor_id, $idempotency_hash, $signature, array $checkpoint ) {
		global $wpdb; $t = SNFLA_Database::tables();
		return false !== $wpdb->insert( $t['runs'], array( 'run_uuid' => $uuid, 'operation' => sanitize_key( $operation ), 'status' => sanitize_key( $status ), 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'idempotency_hash' => $idempotency_hash, 'source_signature' => $signature, 'checkpoint_json' => wp_json_encode( $checkpoint ), 'summary_json' => '{}', 'started_at' => gmdate( 'Y-m-d H:i:s' ) ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function finish_run( $uuid, $status, array $summary ) {
		global $wpdb; $t = SNFLA_Database::tables();
		return false !== $wpdb->update( $t['runs'], array( 'status' => sanitize_key( $status ), 'summary_json' => wp_json_encode( SNFLA_Audit::redact( $summary ) ), 'finished_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'run_uuid' => sanitize_text_field( $uuid ) ), array( '%s', '%s', '%s' ), array( '%s' ) );
	}

	public static function existing_run( $operation, $idempotency_hash ) {
		global $wpdb; $t = SNFLA_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT run_uuid,operation,status,source_signature,checkpoint_json,summary_json,started_at,finished_at FROM {$t['runs']} WHERE operation=%s AND idempotency_hash=%s LIMIT 1", sanitize_key( $operation ), $idempotency_hash ), ARRAY_A );
		if ( ! is_array( $row ) ) { return array(); }
		$row['checkpoint'] = json_decode( $row['checkpoint_json'], true );
		$row['summary'] = json_decode( $row['summary_json'], true );
		unset( $row['checkpoint_json'], $row['summary_json'] );
		return $row;
	}
}
