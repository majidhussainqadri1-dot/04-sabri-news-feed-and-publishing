<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Migration {
	const MAX_BATCH = 100;

	public static function dry_run( $actor_id, $limit = 100, $expected_state = 'inventory_locked', $expected_version = 1 ) {
		if ( ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock. Re-lock before continuing.', array( 'status' => 409 ) );
		}
		$preview = SNFLA_File21_Adapter::preview( min( self::MAX_BATCH, max( 1, absint( $limit ) ) ) );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		$locked = SNFLA_Inventory::locked();
		$report = array(
			'schema'              => 1,
			'created_at_utc'      => gmdate( 'Y-m-d H:i:s' ),
			'source_signature'    => (string) ( $locked['source_signature'] ?? '' ),
			'candidate_count'     => absint( $preview['candidate_count'] ?? 0 ),
			'candidates'          => array(),
			'conflicts'           => array(),
			'destructive'         => false,
			'canonical_owner'     => 'File 21',
			'interaction_provider'=> SNFLA_File21_Adapter::INTERACTION_PROVIDER,
		);
		foreach ( (array) ( $preview['candidates'] ?? array() ) as $candidate ) {
			$legacy_id = absint( is_array( $candidate ) ? ( $candidate['legacy_id'] ?? $candidate['id'] ?? 0 ) : 0 );
			if ( $legacy_id <= 0 ) {
				continue;
			}
			$post      = get_post( $legacy_id );
			$checksum  = SNFLA_Checksum::post( $legacy_id );
			$conflicts = self::candidate_conflicts( $post, $legacy_id );
			$report['candidates'][] = array( 'legacy_id' => $legacy_id, 'source_checksum' => $checksum, 'target_type' => isset( $candidate['target_type'] ) ? sanitize_key( $candidate['target_type'] ) : 'auto', 'conflict_codes' => $conflicts );
			foreach ( $conflicts as $code ) {
				SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'code' => $code ), '' );
				$report['conflicts'][] = array( 'legacy_id' => $legacy_id, 'code' => $code );
			}
		}
		$report['report_checksum'] = SNFLA_Checksum::hash( $report );
		update_option( SNFLA_Schema::DRY_RUN_OPTION, $report, false );
		$transition = SNFLA_Schema::transition( 'dry_run_ready', $expected_state, absint( $expected_version ), $actor_id, array( 'report_checksum' => $report['report_checksum'] ) );
		if ( is_wp_error( $transition ) ) {
			return $transition;
		}
		SNFLA_Audit::record( 'dry_run_completed', $actor_id, array( 'candidate_count' => count( $report['candidates'] ), 'conflict_count' => count( $report['conflicts'] ), 'report_checksum' => $report['report_checksum'] ) );
		return array( 'report' => $report, 'lifecycle' => $transition );
	}

	private static function candidate_conflicts( $post, $legacy_id ) {
		$codes = array();
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) {
			return array( 'invalid_legacy_publication' );
		}
		if ( '' === trim( (string) $post->post_title ) || '' === trim( wp_strip_all_tags( (string) $post->post_content ) ) ) {
			$codes[] = 'missing_required_content';
		}
		if ( absint( $post->post_author ) <= 0 || ! get_userdata( absint( $post->post_author ) ) ) {
			$codes[] = 'author_missing';
		}
		if ( SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) {
			$codes[] = 'already_migrated';
		}
		$terms = wp_get_object_terms( $legacy_id, SNFLA_Inventory::LEGACY_TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) {
			$codes[] = 'taxonomy_read_failed';
		}
		if ( in_array( 'patient-cases', is_array( $terms ) ? $terms : array(), true ) ) {
			$consent_id = (string) get_post_meta( $legacy_id, '_snp_case_consent_record_id', true );
			if ( '' === $consent_id ) {
				$codes[] = 'patient_case_consent_evidence_missing';
			}
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
		$proof = array( 'reference' => $reference, 'checksum' => $checksum, 'created_at_utc' => gmdate( 'Y-m-d H:i:s', $timestamp ), 'recorded_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ) );
		update_option( SNFLA_Schema::BACKUP_PROOF_OPTION, $proof, false );
		SNFLA_Audit::record( 'backup_proof_recorded', $actor_id, array( 'reference_hash' => hash( 'sha256', $reference ), 'checksum' => $checksum, 'created_at_utc' => $proof['created_at_utc'] ) );
		return $proof;
	}

	public static function backup_proof_valid() {
		$proof = get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() );
		if ( ! is_array( $proof ) || empty( $proof['checksum'] ) || empty( $proof['created_at_utc'] ) ) {
			return false;
		}
		$timestamp = strtotime( $proof['created_at_utc'] . ' UTC' );
		return preg_match( '/^[a-f0-9]{64}$/', (string) $proof['checksum'] ) && false !== $timestamp && $timestamp >= time() - 7 * DAY_IN_SECONDS;
	}

	public static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
		$legacy_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $legacy_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) {
			return new WP_Error( 'snfla_empty_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) );
		}
		if ( ! current_user_can( 'sabri_feed_run_migrations' ) ) {
			return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability is required.', array( 'status' => 403 ) );
		}
		if ( ! self::backup_proof_valid() ) {
			return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required before migration.', array( 'status' => 412 ) );
		}
		if ( ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) );
		}
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$locked = SNFLA_Inventory::locked();
		if ( ! is_array( $dry ) || empty( $dry['report_checksum'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) $locked['source_signature'], (string) $dry['source_signature'] ) ) {
			return new WP_Error( 'snfla_dry_run_required', 'A matching dry-run report is required before migration.', array( 'status' => 412 ) );
		}
		$dry_candidates = array();
		foreach ( (array) ( $dry['candidates'] ?? array() ) as $candidate ) {
			$id = absint( $candidate['legacy_id'] ?? 0 );
			if ( $id > 0 ) { $dry_candidates[ $id ] = $candidate; }
		}
		foreach ( $legacy_ids as $legacy_id ) {
			$current_checksum = SNFLA_Checksum::post( $legacy_id );
			if ( ! isset( $dry_candidates[ $legacy_id ] ) || empty( $dry_candidates[ $legacy_id ]['source_checksum'] ) || ! hash_equals( (string) $dry_candidates[ $legacy_id ]['source_checksum'], $current_checksum ) ) {
				return new WP_Error( 'snfla_dry_run_batch_mismatch', 'Every selected legacy ID must appear unchanged in the current dry-run batch.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
			}
		}
		$idempotency_key = trim( (string) $idempotency_key );
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 190 ) {
			return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable idempotency key of 16–190 characters is required.', array( 'status' => 400 ) );
		}
		$idempotency_hash = hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) );
		$existing = self::existing_run( 'migrate', $idempotency_hash );
		if ( $existing ) {
			return array( 'idempotent_replay' => true, 'run' => $existing, 'lifecycle' => SNFLA_Schema::public_status() );
		}
		if ( SNFLA_Mapping::open_conflict_count() > 0 ) {
			return new WP_Error( 'snfla_open_conflicts', 'Open migration conflicts must be resolved before migration.', array( 'status' => 409 ) );
		}
		if ( ! SNFLA_Database::acquire_lock( 'migration', 5 ) ) {
			return new WP_Error( 'snfla_migration_locked', 'Another migration operation is already running.', array( 'status' => 423 ) );
		}
		try {
			$transition = SNFLA_Schema::transition( 'batch_migration', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'batch_size' => count( $legacy_ids ) ) );
			if ( is_wp_error( $transition ) ) {
				return $transition;
			}
			$run_uuid = wp_generate_uuid4();
			self::create_run( $run_uuid, 'migrate', 'running', $actor_id, $idempotency_hash, $locked['source_signature'], array( 'legacy_ids' => $legacy_ids ) );
			$eligible = array();
			$skipped  = array();
			foreach ( $legacy_ids as $legacy_id ) {
				$checksum = SNFLA_Checksum::post( $legacy_id );
				$current  = SNFLA_Mapping::get( $legacy_id );
				$target   = SNFLA_File21_Adapter::target_for( $legacy_id );
				if ( $target > 0 && $current && ! empty( $current['source_checksum'] ) && hash_equals( $current['source_checksum'], $checksum ) ) {
					$skipped[ $legacy_id ] = 'already_migrated_same_checksum';
					continue;
				}
				if ( $target > 0 || ( $current && ! empty( $current['source_checksum'] ) && ! hash_equals( $current['source_checksum'], $checksum ) ) ) {
					SNFLA_Mapping::open_conflict( $legacy_id, 'source_or_mapping_changed', 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target ), $run_uuid );
					$skipped[ $legacy_id ] = 'source_or_mapping_changed';
					continue;
				}
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => 0, 'target_type' => '', 'status' => 'migrating', 'source_checksum' => $checksum, 'run_uuid' => $run_uuid ) );
				$eligible[] = $legacy_id;
			}
			$only_idempotent_skips = ! empty( $skipped ) && empty( array_filter( $skipped, static function ( $code ) { return 'already_migrated_same_checksum' !== $code; } ) );
			$result = empty( $eligible ) ? array( 'success' => $only_idempotent_skips || empty( $skipped ), 'migrated' => array(), 'skipped' => array(), 'warnings' => array() ) : SNFLA_File21_Adapter::migrate( $eligible, $actor_id, $with_interactions );
			if ( is_wp_error( $result ) ) {
				self::finish_run( $run_uuid, 'failed', array( 'error' => $result->get_error_code() ) );
				return $result;
			}
			foreach ( (array) ( $result['migrated'] ?? array() ) as $legacy_id => $row ) {
				$legacy_id = absint( $legacy_id );
				$target_id = absint( $row['target_id'] ?? 0 );
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $target_id, 'target_type' => sanitize_key( $row['target_type'] ?? '' ), 'status' => 'migrated', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::post( $target_id ), 'run_uuid' => $run_uuid ) );
			}
			$conflict_outcomes = array_merge( (array) ( $result['skipped'] ?? array() ), (array) ( $result['warnings'] ?? array() ) );
			foreach ( $skipped as $legacy_id => $code ) {
				if ( 'already_migrated_same_checksum' !== $code ) { $conflict_outcomes[ $legacy_id ] = $code; }
			}
			foreach ( $conflict_outcomes as $legacy_id => $code ) {
				$legacy_id = absint( $legacy_id );
				if ( $legacy_id <= 0 ) {
					continue;
				}
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'status' => 'conflict', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'run_uuid' => $run_uuid, 'last_error_code' => sanitize_key( $code ) ) );
				SNFLA_Mapping::open_conflict( $legacy_id, sanitize_key( $code ), 'high', array( 'legacy_id' => $legacy_id, 'code' => $code ), $run_uuid );
			}
			$status = ( $only_idempotent_skips && empty( $eligible ) ) || ( empty( $result['skipped'] ) && empty( $result['warnings'] ) && empty( $conflict_outcomes ) ) ? 'completed' : ( ! empty( $result['migrated'] ) ? 'partial' : 'failed' );
			$summary = array( 'file21_report' => $result, 'preflight_skipped' => $skipped, 'run_uuid' => $run_uuid );
			self::finish_run( $run_uuid, $status, $summary );
			SNFLA_Audit::record( 'migration_batch_completed', $actor_id, array( 'run_uuid' => $run_uuid, 'status' => $status, 'migrated_count' => count( (array) ( $result['migrated'] ?? array() ) ), 'skipped_count' => count( $skipped ) + count( (array) ( $result['skipped'] ?? array() ) ) ), 'run:' . $run_uuid );
			return array( 'idempotent_replay' => false, 'run_uuid' => $run_uuid, 'status' => $status, 'report' => $summary, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'migration' );
		}
	}

	private static function create_run( $uuid, $operation, $status, $actor_id, $idempotency_hash, $signature, array $checkpoint ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->insert( $t['runs'], array( 'run_uuid' => $uuid, 'operation' => sanitize_key( $operation ), 'status' => sanitize_key( $status ), 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'idempotency_hash' => $idempotency_hash, 'source_signature' => $signature, 'checkpoint_json' => wp_json_encode( $checkpoint ), 'summary_json' => '{}', 'started_at' => gmdate( 'Y-m-d H:i:s' ) ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	private static function finish_run( $uuid, $status, array $summary ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->update( $t['runs'], array( 'status' => sanitize_key( $status ), 'summary_json' => wp_json_encode( SNFLA_Audit::redact( $summary ) ), 'finished_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'run_uuid' => sanitize_text_field( $uuid ) ), array( '%s', '%s', '%s' ), array( '%s' ) );
	}

	private static function existing_run( $operation, $idempotency_hash ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT run_uuid,operation,status,source_signature,checkpoint_json,summary_json,started_at,finished_at FROM {$t['runs']} WHERE operation=%s AND idempotency_hash=%s LIMIT 1", sanitize_key( $operation ), $idempotency_hash ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return array();
		}
		$row['checkpoint'] = json_decode( $row['checkpoint_json'], true );
		$row['summary']    = json_decode( $row['summary_json'], true );
		unset( $row['checkpoint_json'], $row['summary_json'] );
		return $row;
	}
}
