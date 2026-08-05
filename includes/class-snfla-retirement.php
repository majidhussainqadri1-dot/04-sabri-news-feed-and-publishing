<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Retirement {
	const CONFIRMATION = 'RETIRE FILE 04 LEGACY ADAPTER';

	public static function retire( $actor_id, $confirmation, $expected_state, $expected_version ) {
		if ( self::CONFIRMATION !== trim( (string) $confirmation ) ) {
			return new WP_Error( 'snfla_retirement_confirmation_required', 'Type the exact retirement confirmation phrase.', array( 'status' => 400 ) );
		}
		$report = SNFLA_Reconciliation::report();
		if ( empty( $report['green'] ) || SNFLA_Mapping::open_conflict_count() > 0 ) {
			return new WP_Error( 'snfla_retirement_blocked', 'Retirement requires green reconciliation and zero open conflicts.', array( 'status' => 412 ) );
		}
		if ( ! SNFLA_Migration::backup_proof_valid() ) {
			return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) );
		}
		$rollback_proof = get_option( 'snfla_last_rollback_proof', array() );
		if ( ! is_array( $rollback_proof ) || empty( $rollback_proof['performed_at_utc'] ) ) {
			return new WP_Error( 'snfla_rollback_proof_required', 'A successful non-destructive rollback rehearsal is required before retirement.', array( 'status' => 412 ) );
		}
		$window = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
		if ( ! is_array( $window ) || empty( $window['expires_at_utc'] ) || strtotime( $window['expires_at_utc'] . ' UTC' ) >= time() ) {
			return new WP_Error( 'snfla_fallback_window_required', 'A completed and expired read-only fallback window is required.', array( 'status' => 412 ) );
		}
		$transition = SNFLA_Schema::transition( 'retired', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'confirmation_hash' => hash( 'sha256', self::CONFIRMATION ) ) );
		if ( is_wp_error( $transition ) ) {
			return $transition;
		}
		$evidence = array(
			'retired_at_utc'         => gmdate( 'Y-m-d H:i:s' ),
			'reconciliation_checksum'=> $report['report_checksum'] ?? '',
			'rollback_proof_checksum'=> SNFLA_Checksum::hash( $rollback_proof ),
			'fallback_window_checksum'=> SNFLA_Checksum::hash( $window ),
			'source_retained'        => true,
			'destructive'            => false,
			'actor_digest'           => SNFLA_Audit::actor_digest( $actor_id ),
		);
		$evidence['evidence_checksum'] = SNFLA_Checksum::hash( $evidence );
		update_option( SNFLA_Schema::RETIREMENT_OPTION, $evidence, false );
		SNFLA_Audit::record( 'adapter_retired', $actor_id, $evidence );
		return array( 'evidence' => $evidence, 'lifecycle' => $transition );
	}

	public static function mutations_allowed() {
		return 'retired' !== SNFLA_Schema::state();
	}
}
