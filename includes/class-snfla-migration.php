<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Migration {
	const MAX_BATCH = 100;

	public static function dry_run( $actor_id, $limit = 500, $expected_state = 'inventory_locked', $expected_version = 1 ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$check = SNFLA_Schema::assert_current( $expected_state, $expected_version );
			if ( is_wp_error( $check ) ) { return $check; }
			if ( ! SNFLA_Capabilities::file21_ready() ) {
				return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 migration services are unavailable.', array( 'status' => 503 ) );
			}
			if ( ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock. Re-lock before continuing.', array( 'status' => 409 ) );
			}
			$locked = SNFLA_Inventory::locked();
			$source_signature = (string) ( $locked['source_signature'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $source_signature ) ) {
				return new WP_Error( 'snfla_inventory_signature_invalid', 'The locked inventory signature is invalid.', array( 'status' => 412 ) );
			}
			if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_changed_after_lock', 'The legacy source changed while waiting for the migration lock.', array( 'status' => 409 ) ); }
			$state_recheck = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_recheck ) ) { return $state_recheck; }
			$run_uuid = wp_generate_uuid4();
			$batch_size = min( 1000, max( 50, absint( $limit ) ) );
			$totals = array( 'candidate_count' => 0, 'eligible_count' => 0, 'conflict_count' => 0, 'already_migrated' => 0 );
			$status_counts = array();
			$conflict_counts = array();
			$sample = array();
			$streamed = SNFLA_Inventory::each_legacy_id(
				array( 'publish', 'draft', 'pending', 'private', 'future' ),
				static function ( $legacy_id ) use ( $run_uuid, $source_signature, &$totals, &$status_counts, &$conflict_counts, &$sample ) {
					$post = get_post( $legacy_id );
					$conflicts = self::candidate_conflicts( $post, $legacy_id );
					$checksum = SNFLA_Checksum::post( $legacy_id );
					if ( ! SNFLA_Mapping::dry_run_replace( $run_uuid, $source_signature, $legacy_id, $checksum, $conflicts, 'auto' ) ) {
						return new WP_Error( 'snfla_dry_run_item_persist_failed', 'A dry-run item could not be persisted.', array( 'legacy_id' => $legacy_id ) );
					}
					$totals['candidate_count']++;
					$status = $post instanceof WP_Post ? sanitize_key( $post->post_status ) : 'invalid';
					$status_counts[ $status ] = 1 + absint( $status_counts[ $status ] ?? 0 );
					if ( empty( $conflicts ) ) { $totals['eligible_count']++; } else { $totals['conflict_count']++; }
					if ( in_array( 'already_migrated', $conflicts, true ) ) { $totals['already_migrated']++; }
					foreach ( $conflicts as $code ) {
						$conflict_counts[ $code ] = 1 + absint( $conflict_counts[ $code ] ?? 0 );
					}
					if ( count( $sample ) < 100 ) {
						$sample[] = array( 'legacy_id' => $legacy_id, 'source_checksum' => $checksum, 'conflict_codes' => $conflicts );
					}
					return true;
				},
				$batch_size
			);
			if ( is_wp_error( $streamed ) ) { SNFLA_Mapping::clear_dry_run_rows( $run_uuid ); return $streamed; }
			$planning = SNFLA_Plan_Completion::dry_run_estimates( $sample, $totals );
			if ( is_wp_error( $planning ) ) { SNFLA_Mapping::clear_dry_run_rows( $run_uuid ); return $planning; }
			$report = array(
				'schema'               => 3,
				'run_uuid'             => $run_uuid,
				'created_at_utc'       => gmdate( 'Y-m-d H:i:s' ),
				'source_signature'     => $source_signature,
				'candidate_count'      => $totals['candidate_count'],
				'eligible_count'       => $totals['eligible_count'],
				'conflict_count'       => $totals['conflict_count'],
				'already_migrated'     => $totals['already_migrated'],
				'status_counts'        => SNFLA_Checksum::canonicalize( $status_counts ),
				'conflict_counts'      => SNFLA_Checksum::canonicalize( $conflict_counts ),
				'candidate_sample'     => $sample,
				'complete_scan'        => true,
				'destructive'          => false,
				'canonical_owner'      => 'File 21',
				'interaction_provider' => SNFLA_File21_Adapter::INTERACTION_PROVIDER,
				'estimated_dispositions' => $planning['estimated_dispositions'],
				'storage_estimate'       => $planning['storage_estimate'],
				'time_estimate'          => $planning['time_estimate'],
				'sample_diffs'           => $planning['sample_diffs'],
			);
			$report['report_checksum'] = SNFLA_Checksum::hash( $report );
			$previous = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
			if ( $previous !== $report && ! update_option( SNFLA_Schema::DRY_RUN_OPTION, $report, false ) ) {
				SNFLA_Mapping::clear_dry_run_rows( $run_uuid );
				return new WP_Error( 'snfla_dry_run_persist_failed', 'The dry-run report could not be persisted.', array( 'status' => 500 ) );
			}
			$transition = SNFLA_Schema::transition( 'dry_run_ready', $expected_state, absint( $expected_version ), $actor_id, array( 'report_checksum' => $report['report_checksum'], 'run_uuid' => $run_uuid, 'complete_scan' => true ) );
			if ( is_wp_error( $transition ) ) {
				update_option( SNFLA_Schema::DRY_RUN_OPTION, $previous, false );
				SNFLA_Mapping::clear_dry_run_rows( $run_uuid );
				return $transition;
			}
			$previous_run_uuid = sanitize_text_field( (string) ( $previous['run_uuid'] ?? '' ) );
			$cleanup = array(
				'previous_conflicts_superseded' => SNFLA_Mapping::supersede_run_conflicts( $previous_run_uuid, $actor_id ),
				'obsolete_rows_purged'          => SNFLA_Mapping::purge_other_dry_run_rows( $run_uuid ),
			);
			$cleanup['complete'] = ! in_array( false, $cleanup, true );
			if ( ! $cleanup['complete'] ) {
				SNFLA_Audit::record(
					'dry_run_retention_cleanup_pending',
					$actor_id,
					array( 'run_uuid' => $run_uuid, 'cleanup' => $cleanup ),
					'dry-run:' . $run_uuid
				);
			}
			return array( 'report' => $report, 'lifecycle' => $transition, 'retention_cleanup' => $cleanup );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function candidate_conflicts( $post, $legacy_id ) {
		global $wpdb;
		$codes = array();
		$legacy_id = absint( $legacy_id );
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== $post->post_type ) { return array( 'invalid_legacy_publication' ); }
		if ( '' === trim( (string) $post->post_title ) || '' === trim( wp_strip_all_tags( (string) $post->post_content ) ) ) { $codes[] = 'missing_required_content'; }
		$author_id = absint( $post->post_author );
		$author_preflight = SNFLA_Plan_Completion::authorship_preflight( $legacy_id, $author_id );
		if ( is_wp_error( $author_preflight ) ) {
			$codes[] = $author_preflight->get_error_code();
		} elseif ( in_array( sanitize_key( (string) $post->post_status ), array( 'publish', 'future' ), true ) && ! SNFLA_File21_Adapter::public_author_eligible( absint( $author_preflight['user_id'] ?? 0 ) ) ) {
			$codes[] = 'public_author_no_longer_eligible';
		}
		if ( SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) { $codes[] = 'already_migrated'; }
		$terms = wp_get_object_terms( $legacy_id, SNFLA_Inventory::LEGACY_TAXONOMY, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) ) { $codes[] = 'taxonomy_read_failed'; }
		$is_patient_case = in_array( 'patient-cases', is_array( $terms ) ? $terms : array(), true );
		$consent_record = (string) get_post_meta( $legacy_id, '_snp_case_consent_record_id', true );
		if ( $is_patient_case && '' === $consent_record ) {
			$codes[] = 'patient_case_consent_evidence_missing';
		} elseif ( $is_patient_case ) {
			// File 21's accepted migration contract does not yet guarantee a private-until-verified
			// patient-consent transfer. Publishing first and validating later would create an
			// unacceptable exposure window, so these records remain source-only until a newer
			// canonical File 21 contract owns the complete governed migration.
			$codes[] = 'patient_case_requires_governed_canonical_migration';
		} elseif ( '' !== $consent_record ) {
			$codes[] = 'orphan_patient_case_consent_metadata';
		}

		// Known legacy fields receive an explicit, reviewable disposition. File 04
		// never guesses writes into File 21-owned metadata. Fields for which the
		// accepted File 21 migration contract has no canonical command remain
		// source-only and block migration until an approved contract is available.
		$media_preflight = SNFLA_Plan_Completion::media_preflight( $legacy_id );
		if ( is_wp_error( $media_preflight ) ) { $codes[] = $media_preflight->get_error_code(); }
		$governed_meta = array(
			'_snp_tags'           => 'legacy_tag_metadata_requires_canonical_mapping',
			'_snp_language'       => 'legacy_language_requires_canonical_mapping',
			'_snp_featured'       => 'legacy_featured_flag_requires_canonical_policy',
			'_snp_pinned'         => 'legacy_pinned_flag_requires_canonical_policy',
		);
		foreach ( $governed_meta as $meta_key => $conflict_code ) {
			$value   = get_post_meta( $legacy_id, $meta_key, true );
			$present = is_array( $value ) ? ! empty( $value ) : ( is_object( $value ) || '' !== trim( (string) $value ) );
			if ( $present ) { $codes[] = $conflict_code; }
		}
		$view_extra = SNFLA_Interaction_Provider::legacy_view_meta_extra( $legacy_id );
		if ( is_wp_error( $view_extra ) ) {
			$codes[] = $view_extra->get_error_code();
		}
		// _snp_views is migrated through the canonical interaction repository,
		// including the historical meta-only implementation. _snp_viral_score is
		// derived ranking data and is intentionally recomputed by File 21. The
		// editorial classifier is consumed by File 21's target-type resolver.
		$known_meta = array(
			'_snp_case_consent_record_id', '_snp_managed_page', '_snp_managed_page_key',
			'_snp_video_url', '_snp_tags', '_snp_language', '_snp_featured', '_snp_pinned',
			'_snp_views', '_snp_viral_score', '_snp_media_manifest', '_snp_source_ledger',
			'_snp_editorial_news',
		);
		$quoted_known = "'" . implode( "','", array_map( 'esc_sql', $known_meta ) ) . "'";
		$unknown_snp_meta = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key LIKE %s AND meta_key NOT IN ({$quoted_known})", $legacy_id, $wpdb->esc_like( '_snp_' ) . '%' ), 'legacy_meta_count_failed' );
		if ( is_wp_error( $unknown_snp_meta ) ) { $codes[] = $unknown_snp_meta->get_error_code(); } elseif ( $unknown_snp_meta > 0 ) { $codes[] = 'unmapped_snp_metadata'; }
		$attachments = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_parent=%d", $legacy_id ), 'legacy_attachment_count_failed' );
		if ( is_wp_error( $attachments ) ) { $codes[] = $attachments->get_error_code(); } elseif ( $attachments > 0 && is_wp_error( $media_preflight ) ) { $codes[] = 'unmapped_attachment_relationships'; }
		$nonapproved_comments = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID=%d AND comment_approved NOT IN ('1',1)", $legacy_id ), 'legacy_comment_state_count_failed' );
		if ( is_wp_error( $nonapproved_comments ) ) { $codes[] = $nonapproved_comments->get_error_code(); } elseif ( $nonapproved_comments > 0 ) { $codes[] = 'nonapproved_comments_require_policy'; }
		$comment_meta = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON c.comment_ID=cm.comment_id WHERE c.comment_post_ID=%d", $legacy_id ), 'legacy_comment_meta_count_failed' );
		if ( is_wp_error( $comment_meta ) ) { $codes[] = $comment_meta->get_error_code(); } elseif ( $comment_meta > 0 ) { $codes[] = 'comment_metadata_not_supported'; }
		$codes = array_merge( $codes, self::interaction_source_conflicts( $legacy_id ) );
		return array_values( array_unique( array_map( 'sanitize_key', $codes ) ) );
	}

	private static function interaction_source_conflicts( $legacy_id ) {
		global $wpdb;
		static $schemas = null;
		if ( null === $schemas ) {
			$schemas = array();
			$required = array(
				'reactions' => array( 'id', 'post_id', 'user_id' ),
				'saves'     => array( 'id', 'post_id', 'user_id' ),
				'views'     => array( 'id', 'post_id' ),
				'reports'   => array( 'id', 'post_id', 'user_id', 'reason', 'status' ),
			);
			foreach ( $required as $kind => $columns ) {
				$table = $wpdb->prefix . 'snp_' . $kind;
				$wpdb->last_error = '';
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( ! empty( $wpdb->last_error ) ) {
					$schemas[ $kind ] = array( 'exists' => false, 'table' => $table, 'missing' => array(), 'error' => 'legacy_interaction_table_probe_failed_' . $kind );
					continue;
				}
				if ( $exists !== $table ) {
					$schemas[ $kind ] = array( 'exists' => false, 'table' => $table, 'missing' => array() );
					continue;
				}
				$wpdb->last_error = '';
				$actual = array_map( 'strtolower', (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) );
				if ( ! empty( $wpdb->last_error ) ) {
					$schemas[ $kind ] = array( 'exists' => true, 'table' => $table, 'missing' => $columns, 'columns' => array(), 'error' => 'legacy_interaction_schema_probe_failed_' . $kind );
					continue;
				}
				$schemas[ $kind ] = array( 'exists' => true, 'table' => $table, 'missing' => array_values( array_diff( $columns, $actual ) ), 'columns' => $actual );
			}
		}
		$codes = array();
		foreach ( $schemas as $kind => $schema ) {
			if ( ! empty( $schema['error'] ) ) {
				$codes[] = sanitize_key( $schema['error'] );
				continue;
			}
			if ( empty( $schema['exists'] ) ) {
				continue;
			}
			if ( ! empty( $schema['missing'] ) ) {
				$codes[] = 'legacy_interaction_schema_unsupported_' . $kind;
				continue;
			}
			if ( in_array( $kind, array( 'reactions', 'saves', 'reports' ), true ) ) {
				$orphans = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM `{$schema['table']}` s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.post_id=%d AND (s.user_id<=0 OR u.ID IS NULL)", absint( $legacy_id ) ), 'legacy_interaction_orphan_query_failed_' . $kind );
				if ( is_wp_error( $orphans ) ) { $codes[] = $orphans->get_error_code(); } elseif ( $orphans > 0 ) { $codes[] = 'legacy_interaction_user_missing_' . $kind; }
			} elseif ( 'views' === $kind && in_array( 'user_id', (array) ( $schema['columns'] ?? array() ), true ) ) {
				$orphans = self::safe_count_query( $wpdb->prepare( "SELECT COUNT(*) FROM `{$schema['table']}` s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.post_id=%d AND s.user_id>0 AND u.ID IS NULL", absint( $legacy_id ) ), 'legacy_interaction_orphan_query_failed_views' );
				if ( is_wp_error( $orphans ) ) { $codes[] = $orphans->get_error_code(); } elseif ( $orphans > 0 ) { $codes[] = 'legacy_interaction_user_missing_views'; }
			}
		}
		return $codes;
	}

	public static function record_backup_proof( $actor_id, array $evidence ) {
		$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
		$reference = sanitize_text_field( (string) ( $evidence['reference'] ?? '' ) );
		$checksum = strtolower( sanitize_text_field( (string) ( $evidence['checksum'] ?? '' ) ) );
		$created = self::parse_utc_timestamp( $evidence['created_at_utc'] ?? '' );
		$restore_reference = sanitize_text_field( (string) ( $evidence['restore_reference'] ?? '' ) );
		$restore_checksum = strtolower( sanitize_text_field( (string) ( $evidence['restore_checksum'] ?? '' ) ) );
		$restored = self::parse_utc_timestamp( $evidence['restored_at_utc'] ?? '' );
		$restored_signature = strtolower( sanitize_text_field( (string) ( $evidence['restored_source_signature'] ?? '' ) ) );
		if ( '' === $reference || '' === $restore_reference || ! preg_match( '/^[a-f0-9]{64}$/', $checksum ) || ! preg_match( '/^[a-f0-9]{64}$/', $restore_checksum ) || ! preg_match( '/^[a-f0-9]{64}$/', $restored_signature ) ) {
			return new WP_Error( 'snfla_invalid_backup_restore_proof', 'Backup artifact and isolated restore rehearsal evidence with SHA-256 values are required.', array( 'status' => 400 ) );
		}
		foreach ( array( $created, $restored ) as $timestamp ) {
			if ( false === $timestamp || $timestamp < time() - 7 * DAY_IN_SECONDS || $timestamp > time() + 300 ) {
				return new WP_Error( 'snfla_stale_backup_restore_proof', 'Backup and restore evidence must be recent and use UTC timestamps.', array( 'status' => 400 ) );
			}
		}
		$locked = SNFLA_Inventory::locked();
		$source_signature = (string) ( $locked['source_signature'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $source_signature ) || ! hash_equals( $source_signature, $restored_signature ) || ! SNFLA_Inventory::unchanged() ) {
			return new WP_Error( 'snfla_inventory_restore_mismatch', 'The isolated restore evidence does not match the locked legacy inventory.', array( 'status' => 412 ) );
		}
		$expected_post_count = absint( $locked['legacy_record_count'] ?? 0 );
		$expected_comment_count = absint( $locked['comment_count'] ?? 0 );
		if ( ! array_key_exists( 'restored_post_count', $evidence ) || ! array_key_exists( 'restored_comment_count', $evidence ) || false === filter_var( $evidence['restored_post_count'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) || false === filter_var( $evidence['restored_comment_count'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ) {
			return new WP_Error( 'snfla_restore_counts_required', 'Verified non-negative restored publication and comment counts are required.', array( 'status' => 400 ) );
		}
		$restored_post_count = (int) $evidence['restored_post_count'];
		$restored_comment_count = (int) $evidence['restored_comment_count'];
		if ( $expected_post_count !== $restored_post_count || $expected_comment_count !== $restored_comment_count ) {
			return new WP_Error( 'snfla_restore_count_mismatch', 'Restored publication or comment counts do not match the locked inventory.', array( 'status' => 412 ) );
		}
		$restored_table_counts = isset( $evidence['restored_table_counts'] ) && is_array( $evidence['restored_table_counts'] ) ? $evidence['restored_table_counts'] : array();
		$expected_table_keys = array_keys( (array) ( $locked['table_counts'] ?? array() ) );
		if ( array_values( array_diff( array_keys( $restored_table_counts ), $expected_table_keys ) ) ) { return new WP_Error( 'snfla_restore_table_counts_invalid', 'Restore table-count evidence contains unexpected table keys.', array( 'status' => 400 ) ); }
		foreach ( $restored_table_counts as $key => $value ) { if ( false === filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ) return new WP_Error( 'snfla_restore_table_counts_invalid', 'Restore table counts must be non-negative integers.', array( 'status' => 400, 'table' => sanitize_key( $key ) ) ); $restored_table_counts[ $key ] = (int) $value; }
		foreach ( (array) ( $locked['table_counts'] ?? array() ) as $key => $count ) {
			if ( ! array_key_exists( $key, $restored_table_counts ) || absint( $restored_table_counts[ $key ] ) !== absint( $count ) ) {
				return new WP_Error( 'snfla_restore_table_count_mismatch', 'Restored legacy table counts do not match the locked inventory.', array( 'status' => 412, 'table' => sanitize_key( $key ) ) );
			}
		}
		$verification_request = array(
			'backup_reference_hash'  => hash( 'sha256', $reference ),
			'backup_checksum'        => $checksum,
			'restore_reference_hash' => hash( 'sha256', $restore_reference ),
			'restore_checksum'       => $restore_checksum,
			'source_signature'       => $source_signature,
			'created_at_utc'         => gmdate( 'Y-m-d H:i:s', $created ),
			'restored_at_utc'        => gmdate( 'Y-m-d H:i:s', $restored ),
			'restored_post_count'    => $restored_post_count,
			'restored_comment_count' => $restored_comment_count,
			'restored_table_counts'  => SNFLA_Checksum::canonicalize( $restored_table_counts ),
		);
		$verification_request['request_digest'] = SNFLA_Checksum::hash( $verification_request );
		$verification = apply_filters( 'snfla_verify_restore_evidence', array( 'verified' => false ), $verification_request, $locked );
		$verified_at = is_array( $verification ) ? self::parse_utc_timestamp( $verification['verified_at_utc'] ?? '' ) : false;
		$provider_bound = is_array( $verification )
			&& ! empty( $verification['verified'] )
			&& ! empty( $verification['verifier_id'] )
			&& ! empty( $verification['source_signature'] )
			&& ! empty( $verification['request_digest'] )
			&& hash_equals( $source_signature, strtolower( (string) $verification['source_signature'] ) )
			&& hash_equals( (string) $verification_request['request_digest'], strtolower( (string) $verification['request_digest'] ) )
			&& false !== $verified_at
			&& $verified_at >= time() - 15 * MINUTE_IN_SECONDS
			&& $verified_at <= time() + 300;
		if ( ! $provider_bound ) {
			return new WP_Error( 'snfla_restore_verifier_required', 'An approved staging restore-verifier must attest the exact current source-bound restore request and a fresh verification timestamp.', array( 'status' => 412 ) );
		}
		$proof = SNFLA_Integrity::sign_evidence(
			array_merge(
				$verification_request,
				array(
					'verifier_id'       => sanitize_key( $verification['verifier_id'] ),
					'verified_at_utc'   => gmdate( 'Y-m-d H:i:s', $verified_at ),
					'recorded_at_utc'   => gmdate( 'Y-m-d H:i:s' ),
					'actor_digest'      => SNFLA_Audit::actor_digest( $actor_id ),
					'restore_verified'  => true,
				)
			)
		);
		$previous = get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() );
		if ( $previous !== $proof && ! update_option( SNFLA_Schema::BACKUP_PROOF_OPTION, $proof, false ) ) {
			return new WP_Error( 'snfla_backup_proof_persist_failed', 'Backup and restore proof could not be persisted.', array( 'status' => 500 ) );
		}
		if ( ! SNFLA_Audit::record( 'backup_restore_proof_recorded', $actor_id, array( 'backup_checksum' => $checksum, 'restore_checksum' => $restore_checksum, 'source_signature' => $source_signature, 'verifier_id' => $proof['verifier_id'], 'restored_at_utc' => $proof['restored_at_utc'] ), 'backup:' . $source_signature ) ) {
			$restored_previous = update_option( SNFLA_Schema::BACKUP_PROOF_OPTION, $previous, false ) || get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() ) === $previous;
			return new WP_Error( $restored_previous ? 'snfla_backup_proof_audit_failed' : 'snfla_backup_proof_compensation_failed', $restored_previous ? 'Backup/restore proof was reverted because its audit evidence could not be written.' : 'Backup/restore audit failed and previous proof could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored_previous ) );
		}
		return $proof;
	}

	public static function backup_proof_valid() {
		$proof = get_option( SNFLA_Schema::BACKUP_PROOF_OPTION, array() );
		if ( ! SNFLA_Integrity::evidence_valid( $proof ) || empty( $proof['restore_verified'] ) || empty( $proof['verifier_id'] ) ) { return false; }
		$restored = ! empty( $proof['restored_at_utc'] ) ? strtotime( $proof['restored_at_utc'] . ' UTC' ) : false;
		$locked = SNFLA_Inventory::locked();
		$source_signature = (string) ( $locked['source_signature'] ?? '' );
		return preg_match( '/^[a-f0-9]{64}$/', (string) ( $proof['backup_checksum'] ?? '' ) )
			&& preg_match( '/^[a-f0-9]{64}$/', (string) ( $proof['restore_checksum'] ?? '' ) )
			&& preg_match( '/^[a-f0-9]{64}$/', $source_signature )
			&& hash_equals( $source_signature, (string) ( $proof['source_signature'] ?? '' ) )
			&& SNFLA_Audit::has_event( 'backup_restore_proof_recorded', 'backup:' . $source_signature, 'restore_checksum', (string) $proof['restore_checksum'] )
			&& SNFLA_Inventory::unchanged()
			&& false !== $restored
			&& $restored >= time() - 7 * DAY_IN_SECONDS
			&& $restored <= time() + 300;
	}

	public static function quarantine_disposition( $actor_id, array $legacy_ids, $reason_code, $decision_reference, $expected_state, $expected_version ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
		$reason_code = sanitize_key( (string) $reason_code );
		$allowed_reasons = array( 'approved_source_only_retention', 'approved_privacy_exclusion', 'approved_data_quality_exclusion' );
		$decision_reference = trim( (string) $decision_reference );
		$decision_hash = hash( 'sha256', $decision_reference );
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_quarantine_batch', 'Select at least one dry-run conflict candidate.', array( 'status' => 400 ) ); }
		if ( ! in_array( $reason_code, $allowed_reasons, true ) || strlen( $decision_reference ) < 8 || strlen( $decision_reference ) > 190 ) {
			return new WP_Error( 'snfla_quarantine_decision_required', 'An allowed quarantine reason and an 8–190 character decision reference are required.', array( 'status' => 400 ) );
		}
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$state = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state ) ) { return $state; }
			if ( ! in_array( SNFLA_Schema::state(), array( 'dry_run_ready', 'batch_migration', 'reconciliation' ), true ) ) {
				return new WP_Error( 'snfla_quarantine_state_invalid', 'Quarantine dispositions are allowed only before redirect cutover.', array( 'status' => 409 ) );
			}
			if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) ); }
			$dry    = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
			$locked = SNFLA_Inventory::locked();
			if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['complete_scan'] ) || empty( $dry['run_uuid'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) ( $locked['source_signature'] ?? '' ), (string) $dry['source_signature'] ) ) {
				return new WP_Error( 'snfla_complete_dry_run_required', 'A current complete dry-run is required before approving a source-only quarantine disposition.', array( 'status' => 412 ) );
			}
			$approved = array();
			$already  = array();
			$blocked  = array();
			foreach ( $legacy_ids as $legacy_id ) {
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				if ( is_wp_error( $current ) ) { return $current; }
				if ( is_array( $current ) && 'quarantined' === (string) ( $current['status'] ?? '' ) ) {
					$existing = SNFLA_Mapping::progress_checked( $legacy_id );
					if ( is_wp_error( $existing ) ) { return $existing; }
					if ( self::quarantine_valid( $legacy_id, $current )
						&& hash_equals( $reason_code, (string) ( $existing['reason_code'] ?? '' ) )
						&& hash_equals( $decision_hash, (string) ( $existing['decision_reference_hash'] ?? '' ) ) ) {
						$already[ $legacy_id ] = array( 'reason_code' => $reason_code, 'source_checksum' => (string) $current['source_checksum'] );
					} else {
						$blocked[ $legacy_id ] = 'existing_quarantine_decision_conflict';
					}
					continue;
				}
				if ( is_array( $current ) && absint( $current['target_id'] ?? 0 ) > 0 ) {
					$blocked[ $legacy_id ] = 'canonical_target_exists';
					continue;
				}
				$checksum  = SNFLA_Checksum::post( $legacy_id );
				$candidate = SNFLA_Mapping::dry_run_candidate_checked( $legacy_id, (string) $dry['source_signature'], (string) $dry['run_uuid'] );
				if ( is_wp_error( $candidate ) ) { return $candidate; }
				$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $candidate['conflict_codes'] ?? array() ) ) ) ) );
				if ( empty( $candidate ) || empty( $conflicts ) || empty( $candidate['source_checksum'] ) || '' === $checksum || ! hash_equals( (string) $candidate['source_checksum'], $checksum ) || SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) {
					$blocked[ $legacy_id ] = 'candidate_not_quarantinable';
					continue;
				}
				$evidence = array(
					'type'                    => 'source_only_quarantine',
					'reason_code'             => $reason_code,
					'decision_reference_hash' => $decision_hash,
					'conflict_codes'          => $conflicts,
					'source_signature'        => (string) $dry['source_signature'],
					'dry_run_uuid'            => (string) $dry['run_uuid'],
					'source_checksum'         => $checksum,
					'source_retained'         => true,
					'public_after_cutover'    => false,
				);
				$persisted = SNFLA_Mapping::upsert(
					$legacy_id,
					array(
						'target_id'          => 0,
						'target_type'        => 'source_only',
						'status'             => 'quarantined',
						'source_checksum'    => $checksum,
						'target_checksum'    => '',
						'run_uuid'           => (string) $dry['run_uuid'],
						'interaction_ledger' => $evidence,
						'last_error_code'    => $reason_code,
					)
				);
				if ( ! $persisted ) {
					$blocked[ $legacy_id ] = 'quarantine_mapping_persist_failed';
					continue;
				}
				$context = array_merge( array( 'legacy_id' => $legacy_id ), $evidence );
				if ( ! SNFLA_Audit::record( 'legacy_quarantine_approved', $actor_id, $context, 'legacy:' . $legacy_id ) ) {
					SNFLA_Mapping::upsert( $legacy_id, array( 'status' => 'conflict', 'last_error_code' => 'quarantine_audit_failed' ) );
					SNFLA_Mapping::open_conflict( $legacy_id, 'quarantine_audit_failed', 'blocker', $context, (string) $dry['run_uuid'] );
					$blocked[ $legacy_id ] = 'quarantine_audit_failed';
					continue;
				}
				if ( ! SNFLA_Mapping::resolve_system_conflicts( $legacy_id, $conflicts, $actor_id, 'approved_source_only_quarantine' ) ) {
					SNFLA_Mapping::upsert( $legacy_id, array( 'status' => 'conflict', 'last_error_code' => 'quarantine_conflict_resolution_failed' ) );
					SNFLA_Mapping::open_conflict( $legacy_id, 'quarantine_conflict_resolution_failed', 'blocker', $context, (string) $dry['run_uuid'] );
					$blocked[ $legacy_id ] = 'quarantine_conflict_resolution_failed';
					continue;
				}
				$approved[ $legacy_id ] = array( 'reason_code' => $reason_code, 'conflict_codes' => $conflicts, 'source_checksum' => $checksum );
			}
			$result = array(
				'success'              => empty( $blocked ),
				'partial'              => ! empty( $blocked ) && ( ! empty( $approved ) || ! empty( $already ) ),
				'approved'             => $approved,
				'already_approved'     => $already,
				'blocked'              => $blocked,
				'source_retained'      => true,
				'public_after_cutover' => false,
			);
			return empty( $approved ) && empty( $already ) && ! empty( $blocked )
				? new WP_Error( 'snfla_quarantine_failed', 'No quarantine disposition could be approved.', array_merge( array( 'status' => 409 ), $result ) )
				: $result;
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function quarantine_valid( $legacy_id, $map = null ) {
		$legacy_id = absint( $legacy_id );
		$map = is_array( $map ) ? $map : SNFLA_Mapping::get_checked( $legacy_id );
		if ( is_wp_error( $map ) ) { return false; }
		if ( ! is_array( $map ) || 'quarantined' !== (string) ( $map['status'] ?? '' ) || absint( $map['target_id'] ?? 0 ) > 0 || SNFLA_File21_Adapter::target_for( $legacy_id ) > 0 ) { return false; }
		$checksum = SNFLA_Checksum::post( $legacy_id );
		if ( empty( $map['source_checksum'] ) || '' === $checksum || ! hash_equals( (string) $map['source_checksum'], $checksum ) ) { return false; }
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['complete_scan'] ) || empty( $dry['run_uuid'] ) || ! hash_equals( (string) ( $dry['run_uuid'] ?? '' ), (string) ( $map['run_uuid'] ?? '' ) ) ) { return false; }
		$candidate = SNFLA_Mapping::dry_run_candidate_checked( $legacy_id, (string) ( $dry['source_signature'] ?? '' ), (string) $dry['run_uuid'] );
		if ( is_wp_error( $candidate ) ) { return false; }
		$conflicts = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $candidate['conflict_codes'] ?? array() ) ) ) ) );
		if ( empty( $conflicts ) || empty( $candidate['source_checksum'] ) || ! hash_equals( (string) $candidate['source_checksum'], $checksum ) ) { return false; }
		$evidence = SNFLA_Mapping::progress_checked( $legacy_id );
		if ( is_wp_error( $evidence ) ) { return false; }
		if ( 'source_only_quarantine' !== (string) ( $evidence['type'] ?? '' )
			|| ! in_array( (string) ( $evidence['reason_code'] ?? '' ), array( 'approved_source_only_retention', 'approved_privacy_exclusion', 'approved_data_quality_exclusion' ), true )
			|| ! preg_match( '/^[a-f0-9]{64}$/', (string) ( $evidence['decision_reference_hash'] ?? '' ) )
			|| ! hash_equals( (string) ( $dry['source_signature'] ?? '' ), (string) ( $evidence['source_signature'] ?? '' ) )
			|| ! hash_equals( (string) $dry['run_uuid'], (string) ( $evidence['dry_run_uuid'] ?? '' ) )
			|| ! hash_equals( $checksum, (string) ( $evidence['source_checksum'] ?? '' ) )
			|| array_values( array_diff( $conflicts, (array) ( $evidence['conflict_codes'] ?? array() ) ) ) ) {
			return false;
		}
		if ( array_intersect( $conflicts, SNFLA_Mapping::open_conflict_codes( $legacy_id ) ) ) { return false; }
		return SNFLA_Audit::has_event( 'legacy_quarantine_approved', 'legacy:' . $legacy_id, 'source_checksum', $checksum )
			&& SNFLA_Audit::has_event( 'legacy_quarantine_approved', 'legacy:' . $legacy_id, 'decision_reference_hash', (string) $evidence['decision_reference_hash'] );
	}

	public static function migrate( $actor_id, array $legacy_ids, $idempotency_key, $expected_state, $expected_version, $with_interactions = true ) {
		$legacy_ids = SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) { return new WP_Error( 'snfla_empty_batch', 'Select at least one legacy publication.', array( 'status' => 400 ) ); }
		$authorized_actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $authorized_actor ) || absint( $authorized_actor ) !== absint( $actor_id ) ) { return new WP_Error( 'snfla_canonical_migration_capability_missing', 'File 21 canonical migration capability and fresh File 00 authority are required.', array( 'status' => 403 ) ); }
		if ( ! self::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required before migration.', array( 'status' => 412 ) ); }
		if ( ! SNFLA_Inventory::unchanged() ) { return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) ); }
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$locked = SNFLA_Inventory::locked();
		if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) ( $locked['source_signature'] ?? '' ), (string) $dry['source_signature'] ) || ! SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'report_checksum', (string) ( $dry['report_checksum'] ?? '' ) ) ) {
			return new WP_Error( 'snfla_dry_run_required', 'An untampered matching dry-run report is required before migration.', array( 'status' => 412 ) );
		}
		if ( empty( $dry['complete_scan'] ) || empty( $dry['run_uuid'] ) ) {
			return new WP_Error( 'snfla_complete_dry_run_required', 'A complete persisted dry-run scan is required before migration.', array( 'status' => 412 ) );
		}
		foreach ( $legacy_ids as $legacy_id ) {
			$current_checksum = SNFLA_Checksum::post( $legacy_id );
			$candidate = SNFLA_Mapping::dry_run_candidate_checked( $legacy_id, (string) $dry['source_signature'], (string) $dry['run_uuid'] );
			if ( is_wp_error( $candidate ) ) { return $candidate; }
			if ( empty( $candidate['source_checksum'] ) || ! hash_equals( (string) $candidate['source_checksum'], $current_checksum ) ) {
				return new WP_Error( 'snfla_dry_run_batch_mismatch', 'Every selected legacy ID must appear unchanged in the complete dry-run evidence.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
			}
			if ( empty( $candidate['eligible'] ) || ! empty( $candidate['conflict_codes'] ) ) {
				return new WP_Error( 'snfla_candidate_conflicted', 'A selected dry-run candidate has unresolved blockers.', array( 'status' => 409, 'legacy_id' => $legacy_id, 'conflict_codes' => $candidate['conflict_codes'] ?? array() ) );
			}
		}
		$idempotency_key = (string) $idempotency_key;
		if ( strlen( $idempotency_key ) < 16 || strlen( $idempotency_key ) > 190 || 1 !== preg_match( '/^[!-~]+$/D', $idempotency_key ) ) { return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable exact ASCII idempotency key of 16–190 non-space characters is required.', array( 'status' => 400 ) ); }
		$idempotency_hash = hash_hmac( 'sha256', $idempotency_key, wp_salt( 'auth' ) );
		$existing = self::existing_run( 'migrate', $idempotency_hash );
		if ( is_wp_error( $existing ) ) { return $existing; }
		$existing_result = self::existing_migration_result( $existing, $legacy_ids, (string) ( $locked['source_signature'] ?? '' ) );
		if ( null !== $existing_result ) { return $existing_result; }
		if ( SNFLA_Mapping::open_conflict_count() > 0 ) { return new WP_Error( 'snfla_open_conflicts', 'Open migration conflicts must be resolved before migration.', array( 'status' => 409 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'migration', 5 ) ) { SNFLA_Database::release_lock( 'operation' ); return new WP_Error( 'snfla_migration_locked', 'Another migration operation is already running.', array( 'status' => 423 ) ); }
		try {
			$authorized_actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_RUN );
			if ( is_wp_error( $authorized_actor ) || absint( $authorized_actor ) !== absint( $actor_id ) ) {
				return new WP_Error( 'snfla_canonical_migration_capability_changed', 'Migration authority changed while waiting for the operation lock.', array( 'status' => 403 ) );
			}
			if ( ! self::backup_proof_valid() ) {
				return new WP_Error( 'snfla_backup_proof_changed', 'Backup and restore proof changed or expired while waiting for the operation lock.', array( 'status' => 412 ) );
			}
			if ( ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_inventory_changed_after_lock', 'The legacy source changed while waiting for the migration lock.', array( 'status' => 409 ) );
			}
			$state_recheck = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_recheck ) ) { return $state_recheck; }

			$locked = SNFLA_Inventory::locked();
			$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
			if ( ! SNFLA_Integrity::report_checksum_valid( $dry ) || empty( $dry['complete_scan'] ) || empty( $dry['run_uuid'] ) || empty( $dry['source_signature'] ) || ! hash_equals( (string) ( $locked['source_signature'] ?? '' ), (string) $dry['source_signature'] ) ) {
				return new WP_Error( 'snfla_dry_run_changed_after_lock', 'Dry-run evidence changed while waiting for the migration lock.', array( 'status' => 412 ) );
			}
			foreach ( $legacy_ids as $legacy_id ) {
				$current_checksum = SNFLA_Checksum::post( $legacy_id );
				$candidate = SNFLA_Mapping::dry_run_candidate_checked( $legacy_id, (string) $dry['source_signature'], (string) $dry['run_uuid'] );
				if ( is_wp_error( $candidate ) ) { return $candidate; }
				if ( empty( $candidate['source_checksum'] ) || ! hash_equals( (string) $candidate['source_checksum'], $current_checksum ) || empty( $candidate['eligible'] ) || ! empty( $candidate['conflict_codes'] ) ) {
					return new WP_Error( 'snfla_candidate_changed_after_lock', 'A selected dry-run candidate changed while waiting for the migration lock.', array( 'status' => 412, 'legacy_id' => $legacy_id ) );
				}
			}
			$existing = self::existing_run( 'migrate', $idempotency_hash );
			if ( is_wp_error( $existing ) ) { return $existing; }
			$existing_result = self::existing_migration_result( $existing, $legacy_ids, (string) ( $locked['source_signature'] ?? '' ) );
			if ( null !== $existing_result ) { return $existing_result; }

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
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				if ( is_wp_error( $current ) ) { self::finish_run( $run_uuid, 'failed', array( 'error' => $current->get_error_code(), 'legacy_id' => $legacy_id ) ); return $current; }
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
			$fatal_containment_failures = array();
			foreach ( (array) ( $result['migrated'] ?? array() ) as $legacy_id => $row ) {
				$legacy_id = absint( $legacy_id );
				$target_id = absint( $row['target_id'] ?? 0 );
				if ( $target_id <= 0 || ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) {
					$containment = $target_id > 0 ? SNFLA_File21_Adapter::contain_orphan_target( $legacy_id, $target_id, $actor_id, 'file21_mapping_persistence_failed' ) : new WP_Error( 'snfla_file21_target_missing', 'File 21 did not return a valid canonical target.' );
					$code = is_wp_error( $containment ) ? $containment->get_error_code() : 'file21_mapping_persistence_failed';
					$mapping_failures[ $legacy_id ] = $code;
					if ( is_wp_error( $containment ) && $target_id > 0 ) { $fatal_containment_failures[ $legacy_id ] = $containment->get_error_code(); }
					SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $target_id, 'target_type' => sanitize_key( $row['target_type'] ?? '' ), 'status' => 'conflict', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $target_id > 0 ? SNFLA_Checksum::migration_projection_checksum( $target_id, false ) : '', 'run_uuid' => $run_uuid, 'last_error_code' => $code, 'interaction_ledger' => is_wp_error( $containment ) ? array() : array( 'containment' => $containment ) ) );
					SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'contained' => ! is_wp_error( $containment ), 'containment' => is_wp_error( $containment ) ? $containment->get_error_code() : $containment ), $run_uuid );
					continue;
				}
				if ( ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => $target_id, 'target_type' => sanitize_key( $row['target_type'] ?? '' ), 'status' => 'migrated', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => SNFLA_Checksum::migration_projection_checksum( $target_id, false ), 'run_uuid' => $run_uuid ) ) ) {
					$containment = SNFLA_File21_Adapter::contain_orphan_target( $legacy_id, $target_id, $actor_id, 'file04_mapping_persist_failed' );
					$code = is_wp_error( $containment ) ? $containment->get_error_code() : 'mapping_final_persist_failed';
					$mapping_failures[ $legacy_id ] = $code;
					if ( is_wp_error( $containment ) ) { $fatal_containment_failures[ $legacy_id ] = $containment->get_error_code(); }
					SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'contained' => ! is_wp_error( $containment ) ), $run_uuid );
				}
			}
			if ( ! empty( $fatal_containment_failures ) ) {
				self::finish_run( $run_uuid, 'failed', array( 'error' => 'orphan_target_containment_failed', 'failures' => $fatal_containment_failures, 'file21_report' => $result ) );
				SNFLA_Audit::record( 'migration_emergency_containment_failed', $actor_id, array( 'run_uuid' => $run_uuid, 'failures' => $fatal_containment_failures ), 'run:' . $run_uuid );
				return new WP_Error( 'snfla_orphan_target_containment_failed', 'One or more File 21 targets could not be placed under a verified private hold. Immediate operational intervention is required.', array( 'status' => 500, 'run_uuid' => $run_uuid, 'failures' => $fatal_containment_failures ) );
			}
			$warnings = (array) ( $result['warnings'] ?? array() );
			$resumable = array();
			foreach ( $warnings as $legacy_id => $code ) {
				if ( 'interaction_partial' === sanitize_key( is_array( $code ) ? '' : $code ) ) {
					$resumable[ absint( $legacy_id ) ] = 'interaction_partial';
					unset( $warnings[ $legacy_id ] );
				}
			}
			foreach ( $resumable as $legacy_id => $code ) {
				$current = SNFLA_Mapping::get_checked( $legacy_id );
				$progress = SNFLA_Mapping::progress_checked( $legacy_id );
				if ( is_wp_error( $current ) || is_wp_error( $progress ) ) {
					$ledger_code = is_wp_error( $current ) ? $current->get_error_code() : $progress->get_error_code();
					$mapping_failures[ $legacy_id ] = $ledger_code;
					SNFLA_Mapping::open_conflict( $legacy_id, $ledger_code, 'blocker', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
					continue;
				}
				if ( ! SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'target_type' => $current['target_type'] ?? '', 'status' => 'interaction_pending', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'target_checksum' => $current['target_checksum'] ?? '', 'run_uuid' => $run_uuid, 'last_error_code' => $code, 'interaction_ledger' => $progress ) ) ) {
					$mapping_failures[ $legacy_id ] = 'interaction_progress_persist_failed';
					SNFLA_Mapping::open_conflict( $legacy_id, 'interaction_progress_persist_failed', 'blocker', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
					continue;
				}
				SNFLA_Mapping::open_conflict( $legacy_id, 'interaction_migration_incomplete', 'high', array( 'legacy_id' => $legacy_id, 'resumable' => true ), $run_uuid );
			}
			$conflict_outcomes = array_merge( (array) ( $result['skipped'] ?? array() ), $warnings, $mapping_failures );
			foreach ( $skipped as $legacy_id => $code ) { if ( 'already_migrated_same_checksum' !== $code ) { $conflict_outcomes[ $legacy_id ] = $code; } }
			foreach ( $conflict_outcomes as $legacy_id => $code ) {
				$legacy_id = absint( $legacy_id ); if ( $legacy_id <= 0 ) { continue; }
				SNFLA_Mapping::upsert( $legacy_id, array( 'target_id' => SNFLA_File21_Adapter::target_for( $legacy_id ), 'status' => 'conflict', 'source_checksum' => SNFLA_Checksum::post( $legacy_id ), 'run_uuid' => $run_uuid, 'last_error_code' => sanitize_key( is_array( $code ) ? 'file21_warning' : $code ) ) );
				SNFLA_Mapping::open_conflict( $legacy_id, sanitize_key( is_array( $code ) ? 'file21_warning' : $code ), 'high', array( 'legacy_id' => $legacy_id, 'code' => $code ), $run_uuid );
			}
			$status = ( $only_idempotent_skips && empty( $eligible ) ) || ( empty( $result['skipped'] ) && empty( $warnings ) && empty( $conflict_outcomes ) && empty( $resumable ) ) ? 'completed' : ( ! empty( $result['migrated'] ) ? 'partial' : 'failed' );
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


	private static function existing_migration_result( array $existing, array $legacy_ids, $source_signature ) {
		if ( empty( $existing ) ) { return null; }
		if ( ! SNFLA_Integrity::checkpoint_matches( $existing, $legacy_ids, (string) $source_signature ) ) {
			return new WP_Error( 'snfla_idempotency_conflict', 'The idempotency key was already used for a different source or batch.', array( 'status' => 409 ) );
		}
		if ( SNFLA_Integrity::run_is_stale( $existing ) ) {
			if ( ! self::finish_run( $existing['run_uuid'], 'interrupted', array( 'error' => 'stale_running_operation' ) ) ) {
				return new WP_Error( 'snfla_stale_run_finalize_failed', 'The stale migration run could not be marked interrupted; no retry is permitted until the run ledger is repaired.', array( 'status' => 500, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
			}
			return new WP_Error( 'snfla_stale_run_interrupted', 'A stale running operation was marked interrupted. Retry with a new idempotency key.', array( 'status' => 409 ) );
		}
		if ( 'running' === ( $existing['status'] ?? '' ) ) {
			return new WP_Error( 'snfla_operation_in_progress', 'The matching migration is still running.', array( 'status' => 423 ) );
		}
		if ( in_array( (string) ( $existing['status'] ?? '' ), array( 'failed', 'audit_failed', 'interrupted' ), true ) ) {
			return new WP_Error( 'snfla_idempotent_previous_failure', 'The idempotency key belongs to a previous failed or interrupted migration; use a new key after remediation.', array( 'status' => 409, 'run_uuid' => $existing['run_uuid'] ?? '' ) );
		}
		return array( 'idempotent_replay' => true, 'run' => $existing, 'lifecycle' => SNFLA_Schema::public_status() );
	}

	private static function quarantine_batch( array $legacy_ids, $code, $run_uuid ) {
		$code = sanitize_key( $code ) ?: 'migration_failed';
		foreach ( SNFLA_Integrity::normalized_ids( $legacy_ids, self::MAX_BATCH ) as $legacy_id ) {
			$current = SNFLA_Mapping::get_checked( $legacy_id );
			if ( is_wp_error( $current ) ) {
				do_action( 'snfla_operational_alert_v1', array( 'code' => 'quarantine_mapping_read_failed', 'severity' => 'critical', 'legacy_id' => $legacy_id ) );
				continue;
			}
			$persisted = SNFLA_Mapping::upsert(
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
			if ( ! $persisted ) {
				do_action( 'snfla_operational_alert_v1', array( 'code' => 'quarantine_mapping_write_failed', 'severity' => 'critical', 'legacy_id' => $legacy_id ) );
			}
			SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', array( 'legacy_id' => $legacy_id, 'run_uuid' => $run_uuid ), $run_uuid );
		}
	}


	private static function safe_count_query( $sql, $error_code ) {
		global $wpdb;
		$wpdb->last_error = '';
		$value = $wpdb->get_var( $sql );
		if ( ! empty( $wpdb->last_error ) || null === $value ) {
			return new WP_Error( sanitize_key( $error_code ), 'A required legacy data count could not be read safely.' );
		}
		return absint( $value );
	}

	private static function parse_utc_timestamp( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) { return false; }
		try {
			$time = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $error ) {
			return false;
		}
		return $time->setTimezone( new DateTimeZone( 'UTC' ) )->getTimestamp();
	}

	public static function create_run( $uuid, $operation, $status, $actor_id, $idempotency_hash, $signature, array $checkpoint ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$uuid = sanitize_text_field( (string) $uuid );
		$operation = sanitize_key( $operation );
		$status = sanitize_key( $status );
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid ) || ! in_array( $operation, array( 'migrate', 'rollback' ), true ) || 'running' !== $status ) { return false; }
		$idempotency_hash = strtolower( sanitize_text_field( (string) $idempotency_hash ) );
		$signature = strtolower( sanitize_text_field( (string) $signature ) );
		$checkpoint_json = wp_json_encode( SNFLA_Checksum::canonicalize( $checkpoint ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $idempotency_hash ) || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) || false === $checkpoint_json || '' === $uuid || '' === $operation || '' === $status ) {
			return false;
		}
		$wpdb->last_error = '';
		$result = $wpdb->insert(
			$t['runs'],
			array( 'run_uuid' => $uuid, 'operation' => $operation, 'status' => $status, 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'idempotency_hash' => $idempotency_hash, 'source_signature' => $signature, 'checkpoint_json' => $checkpoint_json, 'summary_json' => '{}', 'started_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $result && empty( $wpdb->last_error );
	}

	public static function finish_run( $uuid, $status, array $summary ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$uuid = sanitize_text_field( (string) $uuid );
		$status = sanitize_key( $status );
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $uuid ) || ! in_array( $status, array( 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' ), true ) ) { return false; }
		$summary_json = wp_json_encode( SNFLA_Audit::redact( $summary ) );
		if ( false === $summary_json ) { return false; }
		$wpdb->last_error = '';
		$result = $wpdb->update( $t['runs'], array( 'status' => $status, 'summary_json' => $summary_json, 'finished_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'run_uuid' => $uuid ), array( '%s', '%s', '%s' ), array( '%s' ) );
		return false !== $result && empty( $wpdb->last_error ) && ( 0 < (int) $result || self::run_has_status( $uuid, $status ) );
	}

	private static function run_has_status( $uuid, $status ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$t['runs']} WHERE run_uuid=%s LIMIT 1", sanitize_text_field( $uuid ) ) );
		return empty( $wpdb->last_error ) && sanitize_key( (string) $current ) === sanitize_key( $status );
	}

	public static function existing_run( $operation, $idempotency_hash ) {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$operation = sanitize_key( $operation );
		$idempotency_hash = strtolower( (string) $idempotency_hash );
		if ( ! in_array( $operation, array( 'migrate', 'rollback' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $idempotency_hash ) ) { return new WP_Error( 'snfla_run_ledger_identity_invalid', 'Run-ledger lookup identity is invalid.', array( 'status' => 400 ) ); }
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT run_uuid,operation,status,source_signature,checkpoint_json,summary_json,started_at,finished_at FROM {$t['runs']} WHERE operation=%s AND idempotency_hash=%s LIMIT 1", $operation, $idempotency_hash ), ARRAY_A );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'snfla_run_ledger_query_failed', 'The operation run ledger could not be read safely.', array( 'status' => 500 ) );
		}
		if ( ! is_array( $row ) ) { return array(); }
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', (string) ( $row['run_uuid'] ?? '' ) ) || (string) ( $row['operation'] ?? '' ) !== $operation || ! in_array( (string) ( $row['status'] ?? '' ), array( 'running', 'completed', 'partial', 'failed', 'audit_failed', 'interrupted' ), true ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', (string) ( $row['source_signature'] ?? '' ) ) ) { return new WP_Error( 'snfla_run_ledger_corrupt', 'The operation run ledger contains invalid identity or state evidence.', array( 'status' => 500 ) ); }
		$row['checkpoint'] = json_decode( (string) $row['checkpoint_json'], true );
		$row['summary'] = json_decode( (string) $row['summary_json'], true );
		if ( ! is_array( $row['checkpoint'] ) || ! is_array( $row['summary'] ) ) {
			return new WP_Error( 'snfla_run_ledger_corrupt', 'The operation run ledger contains invalid JSON evidence.', array( 'status' => 500, 'run_uuid' => sanitize_text_field( (string) ( $row['run_uuid'] ?? '' ) ) ) );
		}
		unset( $row['checkpoint_json'], $row['summary_json'] );
		return $row;
	}
}
