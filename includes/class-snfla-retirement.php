<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Retirement {
	const CONFIRMATION = 'RETIRE FILE 04 LEGACY ADAPTER';

	public static function retire( $actor_id, $confirmation, $expected_state, $expected_version ) {
		if ( self::CONFIRMATION !== trim( (string) $confirmation ) ) { return new WP_Error( 'snfla_retirement_confirmation_required', 'Type the exact retirement confirmation phrase.', array( 'status' => 400 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RETIRE );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$state_check = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_check ) ) { return $state_check; }
			if ( ! self::deactivation_api_available() ) {
				return new WP_Error( 'snfla_retirement_deactivation_unavailable', 'WordPress plugin deactivation APIs are unavailable; retirement cannot complete safely.', array( 'status' => 503 ) );
			}
			$report = SNFLA_Reconciliation::report();
			if ( ! SNFLA_Reconciliation::validate_current_report( $report ) ) { return new WP_Error( 'snfla_retirement_blocked', 'Retirement requires a fresh, untampered green reconciliation report, a valid audit chain and zero current conflicts.', array( 'status' => 412 ) ); }
			if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) ); }
			$rollback_proof  = SNFLA_Rollback::proof();
			$rollback_time   = ! empty( $rollback_proof['performed_at_utc'] ) ? strtotime( (string) $rollback_proof['performed_at_utc'] . ' UTC' ) : false;
			$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
			if ( ! preg_match( '/^[a-f0-9]{64}$/', $source_signature ) || ! SNFLA_Inventory::unchanged() ) {
				return new WP_Error( 'snfla_retirement_source_lock_invalid', 'A current, unchanged and cryptographically valid locked source inventory is required before retirement.', array( 'status' => 412 ) );
			}
			if ( ! SNFLA_Integrity::evidence_valid( $rollback_proof ) || false === $rollback_time || $rollback_time < time() - 7 * DAY_IN_SECONDS || $rollback_time > time() + 300 || empty( $rollback_proof['non_destructive'] ) || ! hash_equals( $source_signature, (string) ( $rollback_proof['source_signature'] ?? '' ) ) || ! SNFLA_Audit::has_event( 'rollback_completed', '', 'run_uuid', (string) ( $rollback_proof['run_uuid'] ?? '' ) ) ) { return new WP_Error( 'snfla_rollback_proof_required', 'A recent, source-bound, audited and untampered non-destructive rollback rehearsal is required before retirement.', array( 'status' => 412 ) ); }
			$window  = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
			$expires = ! empty( $window['expires_at_utc'] ) ? strtotime( (string) $window['expires_at_utc'] . ' UTC' ) : false;
			if ( ! SNFLA_Integrity::evidence_valid( $window ) || false === $expires || $expires >= time() || ! SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'fallback_checksum', SNFLA_Checksum::hash( $window ) ) ) { return new WP_Error( 'snfla_fallback_window_required', 'A completed, audited, untampered and expired read-only fallback window is required.', array( 'status' => 412 ) ); }

			$handoff = self::handoff_routes( $actor_id, $source_signature, absint( $report['source_total'] ?? 0 ) );
			if ( is_wp_error( $handoff ) ) { return $handoff; }

			$evidence = SNFLA_Integrity::sign_evidence(
				array(
					'retired_at_utc'                   => gmdate( 'Y-m-d H:i:s' ),
					'reconciliation_checksum'          => $report['report_checksum'] ?? '',
					'rollback_proof_checksum'          => SNFLA_Checksum::hash( $rollback_proof ),
					'fallback_window_checksum'         => SNFLA_Checksum::hash( $window ),
					'redirect_handoff_checksum'        => (string) ( $handoff['manifest_checksum'] ?? '' ),
					'redirect_handoff_provider_digest' => hash( 'sha256', (string) ( $handoff['provider_id'] ?? '' ) ),
					'redirect_handoff_count'           => absint( $handoff['count'] ?? 0 ),
					'audit_chain'                      => SNFLA_Audit::verify_chain(),
					'source_signature'                 => $source_signature,
					'source_retained'                  => true,
					'destructive'                      => false,
					'actor_digest'                     => SNFLA_Audit::actor_digest( $actor_id ),
				)
			);
			$previous = get_option( SNFLA_Schema::RETIREMENT_OPTION, array() );
			if ( ! update_option( SNFLA_Schema::RETIREMENT_OPTION, $evidence, false ) ) { return new WP_Error( 'snfla_retirement_evidence_persist_failed', 'Retirement evidence could not be persisted.', array( 'status' => 500 ) ); }
			$transition = SNFLA_Schema::transition( 'retired', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'confirmation_hash' => hash( 'sha256', self::CONFIRMATION ), 'reconciliation_checksum' => $report['report_checksum'] ?? '', 'retirement_evidence_checksum' => SNFLA_Checksum::hash( $evidence ), 'redirect_handoff_checksum' => $handoff['manifest_checksum'] ?? '' ) );
			if ( is_wp_error( $transition ) ) {
				$restored_previous = update_option( SNFLA_Schema::RETIREMENT_OPTION, $previous, false ) || get_option( SNFLA_Schema::RETIREMENT_OPTION, array() ) === $previous;
				return $restored_previous ? $transition : new WP_Error( 'snfla_retirement_compensation_failed', 'Retirement transition failed and previous retirement evidence could not be restored exactly.', array( 'status' => 500, 'cause' => $transition->get_error_code(), 'manual_recovery_required' => true ) );
			}
			wp_clear_scheduled_hook( 'snfla_daily_integrity_check' );
			$deactivated = self::deactivate_retired_plugin();
			if ( ! $deactivated ) {
				SNFLA_Audit::record( 'retirement_deactivation_failed', $actor_id, array( 'source_signature' => $source_signature, 'retirement_evidence_checksum' => SNFLA_Checksum::hash( $evidence ) ) );
				return new WP_Error( 'snfla_retirement_deactivation_failed', 'The adapter entered the retired state but WordPress could not remove it from the active plugin list; manual deactivation is required before release acceptance.', array( 'status' => 500, 'retired' => true ) );
			}
			return array( 'evidence' => $evidence, 'lifecycle' => $transition, 'redirect_handoff' => SNFLA_Audit::redact( $handoff ), 'plugin_deactivated' => true );
		} finally { SNFLA_Database::release_lock( 'operation' ); }
	}

	/**
	 * Transfer every redirect/gone disposition in bounded, checksum-bound batches
	 * to an approved canonical route owner before this temporary adapter retires.
	 */
	private static function handoff_routes( $actor_id, $source_signature, $expected_count ) {
		$chain       = str_repeat( '0', 64 );
		$batch_no    = 0;
		$provider_id = '';
		$stream = SNFLA_Mapping::each_route_disposition_batch(
			static function ( array $entries ) use ( &$chain, &$batch_no, &$provider_id, $source_signature ) {
				$batch_no++;
				$batch_checksum = SNFLA_Checksum::hash( array( 'source_signature' => $source_signature, 'batch_number' => $batch_no, 'entries' => $entries ) );
				$request = array(
					'schema'           => 2,
					'batch_number'     => $batch_no,
					'batch_count'      => count( $entries ),
					'batch_checksum'   => $batch_checksum,
					'previous_checksum'=> $chain,
					'source_signature' => $source_signature,
					'entries'          => $entries,
				);
				$request['request_digest'] = SNFLA_Checksum::hash( $request );
				$response = apply_filters( 'snfla_retirement_redirect_handoff_batch', array( 'verified' => false ), $request );
				$verified_at = is_array( $response ) && ! empty( $response['verified_at_utc'] ) ? strtotime( (string) $response['verified_at_utc'] . ' UTC' ) : false;
				if ( ! is_array( $response ) || empty( $response['verified'] )
					|| absint( $response['accepted_count'] ?? 0 ) !== count( $entries )
					|| ! hash_equals( $batch_checksum, (string) ( $response['batch_checksum'] ?? '' ) )
					|| ! hash_equals( $source_signature, (string) ( $response['source_signature'] ?? '' ) )
					|| ! hash_equals( $chain, (string) ( $response['previous_checksum'] ?? '' ) )
					|| ! hash_equals( (string) $request['request_digest'], (string) ( $response['request_digest'] ?? '' ) )
					|| false === $verified_at || $verified_at < time() - 15 * MINUTE_IN_SECONDS || $verified_at > time() + 300
					|| '' === sanitize_text_field( (string) ( $response['provider_id'] ?? '' ) ) ) {
					return new WP_Error( 'snfla_retirement_redirect_handoff_batch_failed', 'The canonical route owner did not verify a retirement handoff batch.', array( 'batch_number' => $batch_no ) );
				}
				$current_provider = sanitize_text_field( (string) $response['provider_id'] );
				if ( '' !== $provider_id && ! hash_equals( $provider_id, $current_provider ) ) {
					return new WP_Error( 'snfla_retirement_redirect_handoff_provider_changed', 'The route-handoff provider changed during retirement.' );
				}
				$provider_id = $current_provider;
				$chain       = hash( 'sha256', $chain . '|' . $batch_checksum . '|' . count( $entries ) );
				return true;
			},
			500
		);
		if ( is_wp_error( $stream ) ) { return $stream; }
		if ( absint( $stream['count'] ?? 0 ) !== absint( $expected_count ) ) {
			return new WP_Error( 'snfla_retirement_route_manifest_incomplete', 'The route handoff does not cover every reconciled legacy record.', array( 'expected' => absint( $expected_count ), 'actual' => absint( $stream['count'] ?? 0 ) ) );
		}
		$summary = array(
			'schema'            => 2,
			'provider_id'       => $provider_id,
			'source_signature'  => $source_signature,
			'manifest_checksum' => $chain,
			'count'             => absint( $stream['count'] ?? 0 ),
			'redirect_count'    => absint( $stream['redirect_count'] ?? 0 ),
			'gone_count'        => absint( $stream['gone_count'] ?? 0 ),
			'batch_count'       => $batch_no,
		);
		$summary['request_digest'] = SNFLA_Checksum::hash( $summary );
		$response = apply_filters( 'snfla_verify_retirement_redirect_handoff', array( 'verified' => false ), $summary );
		$response_provider = is_array( $response ) ? sanitize_text_field( (string) ( $response['provider_id'] ?? '' ) ) : '';
		if ( '' === $provider_id && '' !== $response_provider ) {
			$provider_id           = $response_provider;
			$summary['provider_id'] = $provider_id;
		}
		$summary_verified_at = is_array( $response ) && ! empty( $response['verified_at_utc'] ) ? strtotime( (string) $response['verified_at_utc'] . ' UTC' ) : false;
		if ( ! is_array( $response ) || empty( $response['verified'] ) || 'completed' !== sanitize_key( (string) ( $response['status'] ?? '' ) ) || '' === $provider_id || ! hash_equals( $provider_id, $response_provider ) || ! hash_equals( $source_signature, (string) ( $response['source_signature'] ?? '' ) ) || ! hash_equals( $chain, (string) ( $response['manifest_checksum'] ?? '' ) ) || ! hash_equals( (string) $summary['request_digest'], (string) ( $response['request_digest'] ?? '' ) ) || absint( $response['count'] ?? 0 ) !== $summary['count'] || false === $summary_verified_at || $summary_verified_at < time() - 15 * MINUTE_IN_SECONDS || $summary_verified_at > time() + 300 ) {
			return new WP_Error( 'snfla_retirement_redirect_handoff_unverified', 'The canonical route owner did not verify the complete redirect/gone manifest.', array( 'status' => 412 ) );
		}
		if ( ! SNFLA_Audit::record( 'retirement_redirect_handoff_verified', $actor_id, array( 'provider_digest' => hash( 'sha256', $provider_id ), 'source_signature' => $source_signature, 'manifest_checksum' => $chain, 'count' => $summary['count'], 'redirect_count' => $summary['redirect_count'], 'gone_count' => $summary['gone_count'], 'batch_count' => $batch_no ), 'retirement-handoff:' . $chain ) ) {
			return new WP_Error( 'snfla_retirement_redirect_handoff_audit_failed', 'The verified route handoff could not be recorded.' );
		}
		return $summary;
	}


	private static function deactivation_api_available() {
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return function_exists( 'deactivate_plugins' )
			&& function_exists( 'plugin_basename' )
			&& function_exists( 'is_plugin_active' );
	}

	/** Make an already-retired adapter inert and remove it from active plugins. */
	public static function deactivate_retired_plugin() {
		if ( 'retired' !== SNFLA_Schema::state() ) { return false; }
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'deactivate_plugins' ) || ! function_exists( 'plugin_basename' ) || ! function_exists( 'is_plugin_active' ) ) { return false; }
		$plugin  = plugin_basename( SNFLA_FILE );
		$network = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $plugin );
		if ( $network ) {
			do_action( 'snfla_operational_alert_v1', array( 'code' => 'network_retirement_requires_network_operator', 'severity' => 'high' ) );
			return false;
		}
		if ( ! is_plugin_active( $plugin ) ) { return true; }
		deactivate_plugins( $plugin, true, false );
		return ! is_plugin_active( $plugin );
	}

	public static function mutations_allowed() {
		$state = SNFLA_Schema::state();
		return SNFLA_Schema::state_valid() && 'retired' !== $state;
	}
}
