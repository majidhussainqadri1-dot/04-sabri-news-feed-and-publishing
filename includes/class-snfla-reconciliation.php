<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Reconciliation {
	const MAX_REPORT_AGE = DAY_IN_SECONDS;

	public static function run( $actor_id, $expected_state, $expected_version ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			if ( ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_inventory_changed', 'The legacy source changed after inventory lock.', array( 'status' => 409 ) );
			}
			$state_check = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_check ) ) { return $state_check; }

			$source_total = 0;
			$verified = 0;
			$issue_count = 0;
			$issue_sample = array();
			$issue_counts = array();
			$stream = SNFLA_Inventory::each_legacy_id(
				array( 'publish', 'draft', 'pending', 'private', 'future' ),
				static function ( $legacy_id ) use ( &$source_total, &$verified, &$issue_count, &$issue_sample, &$issue_counts ) {
					$source_total++;
					$codes = self::current_issue_codes( $legacy_id );
					if ( empty( $codes ) ) {
						$verified++;
						return true;
					}
					foreach ( $codes as $code ) {
						$issue_count++;
						$issue_counts[ $code ] = 1 + absint( $issue_counts[ $code ] ?? 0 );
						$issue = array( 'legacy_id' => $legacy_id, 'code' => $code );
						if ( count( $issue_sample ) < 500 ) { $issue_sample[] = $issue; }
						if ( ! SNFLA_Mapping::open_conflict( $legacy_id, $code, 'blocker', $issue, '' ) ) {
							return new WP_Error( 'snfla_conflict_persist_failed', 'A reconciliation conflict could not be persisted.', array( 'legacy_id' => $legacy_id ) );
						}
					}
					return true;
				}
			);
			if ( is_wp_error( $stream ) ) { return $stream; }

			$retained_count = 0;
			$retained_sample = array();
			$retained_stream = SNFLA_Inventory::each_legacy_id(
				array( 'trash' ),
				static function ( $legacy_id ) use ( &$retained_count, &$retained_sample ) {
					$retained_count++;
					if ( count( $retained_sample ) < 500 ) { $retained_sample[] = $legacy_id; }
					return true;
				}
			);
			if ( is_wp_error( $retained_stream ) ) { return $retained_stream; }

			$audit = SNFLA_Audit::verify_chain();
			if ( empty( $audit['valid'] ) ) {
				$issue_count++;
				$issue_counts['audit_chain_invalid'] = 1 + absint( $issue_counts['audit_chain_invalid'] ?? 0 );
				if ( count( $issue_sample ) < 500 ) { $issue_sample[] = array( 'legacy_id' => 0, 'code' => 'audit_chain_invalid' ); }
				if ( ! SNFLA_Mapping::open_conflict( 0, 'audit_chain_invalid', 'blocker', array( 'error' => $audit['error'] ?? '' ), '' ) ) {
					return new WP_Error( 'snfla_conflict_persist_failed', 'The invalid audit-chain conflict could not be persisted.', array( 'status' => 500 ) );
				}
			}
			$open_conflicts = SNFLA_Mapping::open_conflict_count();
			$locked = SNFLA_Inventory::locked();
			$current_inventory = SNFLA_Inventory::capture();
			if ( is_wp_error( $current_inventory ) ) { return $current_inventory; }
			global $wpdb;
			$tables = SNFLA_Database::tables();
			$wpdb->last_error = '';
			$migrated_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['map']} WHERE status='migrated'" );
			if ( ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_reconciliation_mapping_count_failed', 'Migrated mapping counts could not be read safely.', array( 'status' => 500 ) );
			}
			$wpdb->last_error = '';
			$quarantined_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['map']} WHERE status='quarantined'" );
			if ( ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'snfla_reconciliation_quarantine_count_failed', 'Quarantine mapping counts could not be read safely.', array( 'status' => 500 ) );
			}
			$disposition_counts = array(
				'migrated'    => absint( $migrated_count ),
				'quarantined' => absint( $quarantined_count ),
			);
			$report_uuid = wp_generate_uuid4();
			$report = array(
				'schema'                    => 3,
				'report_uuid'               => $report_uuid,
				'created_at_utc'            => gmdate( 'Y-m-d H:i:s' ),
				'source_total'              => $source_total,
				'retained_source_only'      => $retained_count,
				'retained_source_ids'       => $retained_sample,
				'verified_dispositions'      => $verified,
				'verified_mappings'         => $verified,
				'disposition_counts'        => $disposition_counts,
				'issue_count'               => $issue_count,
				'issue_counts'              => SNFLA_Checksum::canonicalize( $issue_counts ),
				'open_conflicts'            => $open_conflicts,
				'issues'                    => $issue_sample,
				'audit_chain'               => $audit,
				'complete_scan'             => true,
				'green'                     => $source_total === $verified && 0 === $issue_count && 0 === $open_conflicts && ! empty( $audit['valid'] ),
				'source_signature'          => (string) ( $locked['source_signature'] ?? '' ),
				'final_delta_signature'     => (string) ( $current_inventory['source_signature'] ?? '' ),
				'final_delta_matches_lock'  => ! empty( $locked['source_signature'] ) && hash_equals( (string) $locked['source_signature'], (string) ( $current_inventory['source_signature'] ?? '' ) ),
			);
			$report['green'] = $report['green'] && $report['final_delta_matches_lock'];
			$report['report_checksum'] = SNFLA_Checksum::hash( $report );
			$previous_report = get_option( 'snfla_reconciliation_report', array() );
			if ( $previous_report !== $report && ! update_option( 'snfla_reconciliation_report', $report, false ) ) {
				return new WP_Error( 'snfla_reconciliation_persist_failed', 'The reconciliation report could not be persisted.', array( 'status' => 500 ) );
			}
			$object_ref = 'reconciliation:' . $report_uuid;
			if ( ! SNFLA_Audit::record( 'reconciliation_completed', $actor_id, array( 'source_total' => $source_total, 'verified_mappings' => $verified, 'issue_count' => $issue_count, 'open_conflicts' => $open_conflicts, 'green' => $report['green'], 'complete_scan' => true, 'final_delta_matches_lock' => $report['final_delta_matches_lock'], 'report_checksum' => $report['report_checksum'] ), $object_ref ) ) {
				$restored_previous = update_option( 'snfla_reconciliation_report', $previous_report, false ) || get_option( 'snfla_reconciliation_report', array() ) === $previous_report;
				return new WP_Error( $restored_previous ? 'snfla_reconciliation_audit_failed' : 'snfla_reconciliation_compensation_failed', $restored_previous ? 'The reconciliation report was reverted because audit evidence could not be written.' : 'Reconciliation audit failed and the previous report could not be restored exactly.', array( 'status' => 500, 'manual_recovery_required' => ! $restored_previous ) );
			}
			$transition = SNFLA_Schema::transition( 'reconciliation', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'report_checksum' => $report['report_checksum'], 'report_uuid' => $report_uuid, 'green' => (bool) $report['green'] ) );
			if ( is_wp_error( $transition ) ) {
				return $transition;
			}
			return array( 'report' => $report, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function current_issue_codes( $legacy_id ) {
		$legacy_id = absint( $legacy_id );
		$codes = array();
		$map = SNFLA_Mapping::get_checked( $legacy_id );
		if ( is_wp_error( $map ) ) { return array( 'mapping_ledger_read_failed' ); }
		if ( is_array( $map ) && 'quarantined' === (string) ( $map['status'] ?? '' ) ) {
			return SNFLA_Migration::quarantine_valid( $legacy_id, $map ) ? array() : array( 'quarantine_disposition_invalid' );
		}
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		if ( empty( $map ) || ! in_array( (string) ( $map['status'] ?? '' ), array( 'migrated', 'interaction_pending' ), true ) || $target_id <= 0 ) { return array( 'missing_active_mapping' ); }
		if ( ! SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id ) ) { $codes[] = 'canonical_target_invalid'; }
		if ( 'interaction_pending' === (string) $map['status'] ) { $codes[] = 'interaction_migration_incomplete'; }
		if ( absint( $map['target_id'] ) !== $target_id || absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) !== $legacy_id ) { $codes[] = 'mapping_provenance_mismatch'; }
		$source_checksum = SNFLA_Checksum::post( $legacy_id );
		$target_checksum = SNFLA_Checksum::migration_projection_checksum( $target_id, false );
		if ( empty( $map['source_checksum'] ) || ! hash_equals( (string) $map['source_checksum'], $source_checksum ) ) { $codes[] = 'source_checksum_changed'; }
		if ( empty( $map['target_checksum'] ) || ! hash_equals( (string) $map['target_checksum'], $target_checksum ) ) { $codes[] = 'target_checksum_changed'; }
		if ( ! SNFLA_Checksum::migration_equivalent( $legacy_id, $target_id ) ) { $codes[] = 'publication_projection_mismatch'; }
		$interaction = SNFLA_Interaction_Provider::reconcile( $legacy_id, $target_id );
		if ( empty( $interaction['valid'] ) ) { $codes = array_merge( $codes, (array) $interaction['issues'] ); }
		return array_values( array_unique( array_map( 'sanitize_key', $codes ) ) );
	}

	public static function report() {
		$report = get_option( 'snfla_reconciliation_report', array() );
		return is_array( $report ) ? $report : array();
	}

	public static function validate_current_report( $report = null ) {
		$report = null === $report ? self::report() : $report;
		if ( ! SNFLA_Integrity::report_checksum_valid( $report ) || empty( $report['green'] ) || empty( $report['complete_scan'] ) || empty( $report['final_delta_matches_lock'] ) || ! empty( $report['open_conflicts'] ) ) { return false; }
		$created = ! empty( $report['created_at_utc'] ) ? strtotime( (string) $report['created_at_utc'] . ' UTC' ) : false;
		if ( false === $created || $created < time() - self::MAX_REPORT_AGE || $created > time() + 300 ) { return false; }
		$report_uuid = sanitize_text_field( (string) ( $report['report_uuid'] ?? '' ) );
		if ( '' === $report_uuid || ! SNFLA_Audit::has_event( 'reconciliation_completed', 'reconciliation:' . $report_uuid, 'report_checksum', (string) $report['report_checksum'] ) ) { return false; }
		$locked = SNFLA_Inventory::locked();
		if ( empty( $locked['source_signature'] ) || ! hash_equals( (string) $locked['source_signature'], (string) ( $report['source_signature'] ?? '' ) ) || ! hash_equals( (string) $locked['source_signature'], (string) ( $report['final_delta_signature'] ?? '' ) ) || ! SNFLA_Inventory::unchanged() ) { return false; }
		if ( 0 !== SNFLA_Mapping::open_conflict_count() || SNFLA_Inventory::count_active() !== absint( $report['source_total'] ?? -1 ) ) { return false; }
		$valid = true;
		$stream = SNFLA_Inventory::each_legacy_id(
			array( 'publish', 'draft', 'pending', 'private', 'future' ),
			static function ( $legacy_id ) use ( &$valid ) {
				if ( ! empty( self::current_issue_codes( $legacy_id ) ) ) { $valid = false; return false; }
				return true;
			}
		);
		if ( is_wp_error( $stream ) || ! $valid ) { return false; }
		$audit = SNFLA_Audit::verify_chain();
		return ! empty( $audit['valid'] );
	}

	public static function approve_cutover( $actor_id, $expected_state, $expected_version ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$state_check = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_check ) ) { return $state_check; }
			$report = self::report();
			if ( ! self::validate_current_report( $report ) ) { return new WP_Error( 'snfla_reconciliation_not_green', 'A fresh, complete, untampered green reconciliation report with zero conflicts is required.', array( 'status' => 412 ) ); }
			if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent independently verified backup and restore rehearsal is required.', array( 'status' => 412 ) ); }
			$before = SNFLA_Inventory::capture();
			if ( is_wp_error( $before ) ) { return $before; }
			$locked = SNFLA_Inventory::locked();
			if ( empty( $locked['source_signature'] ) || ! hash_equals( (string) $locked['source_signature'], (string) ( $before['source_signature'] ?? '' ) ) ) { return new WP_Error( 'snfla_final_delta_changed', 'The legacy source changed immediately before cutover.', array( 'status' => 409 ) ); }
			$context = array( 'canonical_owner' => 'File 21', 'source_signature' => $locked['source_signature'], 'reconciliation_checksum' => (string) ( $report['report_checksum'] ?? '' ) );
			$context['request_digest'] = SNFLA_Checksum::hash( $context );
			do_action( 'sabri_hnf_invalidate_cache' );
			do_action( 'snfla_targeted_cache_invalidation', $context );
			$cache_evidence = apply_filters( 'snfla_verify_cutover_cache_invalidation', array( 'verified' => false ), $context );
			if ( ! self::integration_evidence_valid( $cache_evidence, array( 'completed', 'not_required' ), $context ) ) {
				return new WP_Error( 'snfla_cutover_cache_evidence_required', 'A canonical cache provider must verify cutover cache invalidation.', array( 'status' => 412 ) );
			}
			do_action( 'snfla_request_search_reindex', $context );
			$search_evidence = apply_filters( 'snfla_verify_cutover_search_reindex', array( 'verified' => false ), $context );
			if ( ! self::integration_evidence_valid( $search_evidence, array( 'completed', 'accepted', 'not_required' ), $context ) ) {
				return new WP_Error( 'snfla_cutover_search_evidence_required', 'The canonical search/index provider must verify completion, acceptance, or a documented not-required state.', array( 'status' => 412 ) );
			}
			$side_effect_evidence = array(
				'cache'            => SNFLA_Audit::redact( $cache_evidence ),
				'search'           => SNFLA_Audit::redact( $search_evidence ),
				'source_signature' => $locked['source_signature'],
			);
			if ( ! SNFLA_Audit::record( 'redirect_cutover_side_effects_completed', $actor_id, $side_effect_evidence, 'cutover:' . $locked['source_signature'] ) ) {
				return new WP_Error( 'snfla_cutover_side_effect_audit_failed', 'Cache/search preparation completed but its audit evidence could not be written.', array( 'status' => 500 ) );
			}
			$transition = SNFLA_Schema::transition( 'redirect_cutover', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'reconciliation_checksum' => $report['report_checksum'] ?? '', 'final_delta_signature' => $locked['source_signature'], 'cache_provider' => sanitize_key( (string) ( $cache_evidence['provider_id'] ?? '' ) ), 'search_provider' => sanitize_key( (string) ( $search_evidence['provider_id'] ?? '' ) ) ) );
			if ( is_wp_error( $transition ) ) { return $transition; }
			return $transition;
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	private static function integration_evidence_valid( $evidence, array $allowed_statuses, array $request ) {
		if ( ! is_array( $evidence ) || empty( $evidence['verified'] ) || empty( $evidence['provider_id'] ) ) {
			return false;
		}
		$status = sanitize_key( (string) ( $evidence['status'] ?? '' ) );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			return false;
		}
		if ( 'not_required' === $status && empty( $evidence['reason_code'] ) ) {
			return false;
		}
		$source_signature = strtolower( (string) ( $request['source_signature'] ?? '' ) );
		$reconciliation_checksum = strtolower( (string) ( $request['reconciliation_checksum'] ?? '' ) );
		$request_digest = strtolower( (string) ( $request['request_digest'] ?? '' ) );
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/', $source_signature )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $reconciliation_checksum )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $request_digest )
			|| empty( $evidence['source_signature'] )
			|| empty( $evidence['reconciliation_checksum'] )
			|| empty( $evidence['request_digest'] )
			|| ! hash_equals( $source_signature, strtolower( (string) $evidence['source_signature'] ) )
			|| ! hash_equals( $reconciliation_checksum, strtolower( (string) $evidence['reconciliation_checksum'] ) )
			|| ! hash_equals( $request_digest, strtolower( (string) $evidence['request_digest'] ) ) ) {
			return false;
		}
		$verified_at = ! empty( $evidence['verified_at_utc'] ) ? strtotime( (string) $evidence['verified_at_utc'] . ' UTC' ) : false;
		return false !== $verified_at && $verified_at >= time() - 15 * MINUTE_IN_SECONDS && $verified_at <= time() + 300;
	}

	public static function resolve_conflict( $actor_id, $conflict_id, $resolution_code ) {
		global $wpdb;
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$t = SNFLA_Database::tables();
			$conflict_id = absint( $conflict_id );
			$resolution_code = sanitize_key( $resolution_code );
			$allowed = array( 'external_remediation_verified', 'canonical_target_repaired', 'mapping_rebuilt', 'source_restored_to_locked_state', 'migration_policy_extended' );
			if ( $conflict_id <= 0 || ! in_array( $resolution_code, $allowed, true ) ) { return new WP_Error( 'snfla_invalid_conflict_resolution', 'Use an allowed remediation code after the underlying defect has actually been corrected.', array( 'status' => 400 ) ); }
			$wpdb->last_error = '';
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT id,legacy_id,conflict_code,status,redacted_context_json FROM {$t['conflicts']} WHERE id=%d", $conflict_id ), ARRAY_A );
			if ( ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_conflict_query_failed', 'The conflict ledger could not be read safely.', array( 'status' => 500 ) ); }
			if ( ! is_array( $row ) || 'open' !== $row['status'] ) { return new WP_Error( 'snfla_conflict_unavailable', 'The conflict is unavailable or already resolved.', array( 'status' => 404 ) ); }
			$legacy_id = absint( $row['legacy_id'] );
			$code = sanitize_key( $row['conflict_code'] );
			$remaining = 0 === $legacy_id && 'audit_chain_invalid' === $code ? ( empty( SNFLA_Audit::verify_chain()['valid'] ) ? array( 'audit_chain_invalid' ) : array() ) : array_merge( self::current_issue_codes( $legacy_id ), self::candidate_conflicts_if_unmapped( $legacy_id ) );
			if ( in_array( $code, $remaining, true ) ) { return new WP_Error( 'snfla_conflict_still_present', 'The underlying defect is still present; the conflict cannot be administratively dismissed.', array( 'status' => 409, 'legacy_id' => $legacy_id, 'conflict_code' => $code ) ); }
			$context = json_decode( (string) $row['redacted_context_json'], true );
			$context = is_array( $context ) ? $context : array();
			$context['resolution'] = array( 'code' => $resolution_code, 'actor_digest' => SNFLA_Audit::actor_digest( $actor_id ), 'resolved_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
			$encoded_context = wp_json_encode( SNFLA_Audit::redact( $context ) );
			if ( false === $encoded_context ) { return new WP_Error( 'snfla_conflict_context_encode_failed', 'The conflict resolution evidence could not be encoded.', array( 'status' => 500 ) ); }
			$wpdb->last_error = '';
			$updated = $wpdb->update( $t['conflicts'], array( 'status' => 'resolved', 'resolved_at' => gmdate( 'Y-m-d H:i:s' ), 'redacted_context_json' => $encoded_context ), array( 'id' => $conflict_id, 'status' => 'open' ), array( '%s', '%s', '%s' ), array( '%d', '%s' ) );
			if ( false === $updated || ! empty( $wpdb->last_error ) ) { return new WP_Error( 'snfla_conflict_update_failed', 'The conflict resolution could not be recorded.', array( 'status' => 500 ) ); }
			if ( 0 === $updated ) { return new WP_Error( 'snfla_conflict_concurrently_changed', 'The conflict changed before the resolution could be recorded.', array( 'status' => 409 ) ); }
			if ( ! SNFLA_Audit::record( 'conflict_resolved', $actor_id, array( 'conflict_id' => $conflict_id, 'legacy_id' => $legacy_id, 'conflict_code' => $code, 'resolution_code' => $resolution_code ), 'conflict:' . $conflict_id ) ) {
				$wpdb->last_error = '';
				$reverted = $wpdb->update( $t['conflicts'], array( 'status' => 'open', 'resolved_at' => null, 'redacted_context_json' => (string) $row['redacted_context_json'] ), array( 'id' => $conflict_id, 'status' => 'resolved' ), array( '%s', '%s', '%s' ), array( '%d', '%s' ) );
				if ( false === $reverted || 0 === $reverted || ! empty( $wpdb->last_error ) ) {
					return new WP_Error( 'snfla_conflict_audit_and_revert_failed', 'Conflict audit evidence failed and the compensating ledger reversion also failed.', array( 'status' => 500 ) );
				}
				return new WP_Error( 'snfla_conflict_audit_failed', 'Conflict resolution was reverted because audit evidence could not be written.', array( 'status' => 500 ) );
			}
			return array( 'resolved' => true, 'conflict_id' => $conflict_id );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	private static function candidate_conflicts_if_unmapped( $legacy_id ) {
		$map = SNFLA_Mapping::get_checked( $legacy_id );
		if ( is_wp_error( $map ) ) { return array( 'mapping_ledger_read_failed' ); }
		if ( $map && in_array( (string) ( $map['status'] ?? '' ), array( 'migrated', 'interaction_pending' ), true ) ) { return array(); }
		return SNFLA_Migration::candidate_conflicts( get_post( $legacy_id ), $legacy_id );
	}
}
