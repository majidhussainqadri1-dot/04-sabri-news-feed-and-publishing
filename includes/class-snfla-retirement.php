<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Retirement {
	const CONFIRMATION = 'RETIRE FILE 04 LEGACY ADAPTER';

	public static function retire( $actor_id, $confirmation, $expected_state, $expected_version ) {
		if ( self::CONFIRMATION !== trim( (string) $confirmation ) ) { return new WP_Error( 'snfla_retirement_confirmation_required', 'Type the exact retirement confirmation phrase.', array( 'status' => 400 ) ); }
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) { return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) ); }
		try {
			$report = SNFLA_Reconciliation::report();
			if ( ! SNFLA_Reconciliation::validate_current_report( $report ) ) { return new WP_Error( 'snfla_retirement_blocked', 'Retirement requires a fresh, untampered green reconciliation report, a valid audit chain and zero current conflicts.', array( 'status' => 412 ) ); }
			if ( ! SNFLA_Migration::backup_proof_valid() ) { return new WP_Error( 'snfla_backup_proof_required', 'A recent backup and restore proof is required.', array( 'status' => 412 ) ); }
			$rollback_proof = SNFLA_Rollback::proof();
			$rollback_time = ! empty( $rollback_proof['performed_at_utc'] ) ? strtotime( (string) $rollback_proof['performed_at_utc'] . ' UTC' ) : false;
			$source_signature = (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' );
			if ( ! SNFLA_Integrity::evidence_valid( $rollback_proof ) || false === $rollback_time || $rollback_time < time() - 7 * DAY_IN_SECONDS || $rollback_time > time() + 300 || empty( $rollback_proof['non_destructive'] ) || ! hash_equals( $source_signature, (string) ( $rollback_proof['source_signature'] ?? '' ) ) || ! SNFLA_Audit::has_event( 'rollback_completed', '', 'run_uuid', (string) ( $rollback_proof['run_uuid'] ?? '' ) ) ) { return new WP_Error( 'snfla_rollback_proof_required', 'A recent, source-bound, audited and untampered non-destructive rollback rehearsal is required before retirement.', array( 'status' => 412 ) ); }
			$window = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
			$expires = ! empty( $window['expires_at_utc'] ) ? strtotime( (string) $window['expires_at_utc'] . ' UTC' ) : false;
			if ( ! SNFLA_Integrity::evidence_valid( $window ) || false === $expires || $expires >= time() || ! SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'fallback_checksum', SNFLA_Checksum::hash( $window ) ) ) { return new WP_Error( 'snfla_fallback_window_required', 'A completed, audited, untampered and expired read-only fallback window is required.', array( 'status' => 412 ) ); }
			$evidence = SNFLA_Integrity::sign_evidence(
				array(
					'retired_at_utc'          => gmdate( 'Y-m-d H:i:s' ),
					'reconciliation_checksum' => $report['report_checksum'] ?? '',
					'rollback_proof_checksum' => SNFLA_Checksum::hash( $rollback_proof ),
					'fallback_window_checksum'=> SNFLA_Checksum::hash( $window ),
					'audit_chain'             => SNFLA_Audit::verify_chain(),
					'source_signature'        => (string) ( SNFLA_Inventory::locked()['source_signature'] ?? '' ),
					'source_retained'         => true,
					'destructive'             => false,
					'actor_digest'            => SNFLA_Audit::actor_digest( $actor_id ),
				)
			);
			$previous = get_option( SNFLA_Schema::RETIREMENT_OPTION, array() );
			if ( ! update_option( SNFLA_Schema::RETIREMENT_OPTION, $evidence, false ) ) { return new WP_Error( 'snfla_retirement_evidence_persist_failed', 'Retirement evidence could not be persisted.', array( 'status' => 500 ) ); }
			$transition = SNFLA_Schema::transition( 'retired', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'confirmation_hash' => hash( 'sha256', self::CONFIRMATION ), 'reconciliation_checksum' => $report['report_checksum'] ?? '', 'retirement_evidence_checksum' => SNFLA_Checksum::hash( $evidence ) ) );
			if ( is_wp_error( $transition ) ) { update_option( SNFLA_Schema::RETIREMENT_OPTION, $previous, false ); return $transition; }
			wp_clear_scheduled_hook( 'snfla_daily_integrity_check' );
			return array( 'evidence' => $evidence, 'lifecycle' => $transition );
		} finally { SNFLA_Database::release_lock( 'operation' ); }
	}

	public static function mutations_allowed() { return 'retired' !== SNFLA_Schema::state(); }
}
