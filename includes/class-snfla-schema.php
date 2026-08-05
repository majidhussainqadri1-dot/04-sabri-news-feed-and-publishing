<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Schema {
	const STATE_OPTION = 'snfla_lifecycle_state';
	const STATE_VERSION_OPTION = 'snfla_lifecycle_version';
	const INVENTORY_OPTION = 'snfla_inventory_lock';
	const DRY_RUN_OPTION = 'snfla_last_dry_run';
	const BACKUP_PROOF_OPTION = 'snfla_backup_proof';
	const FALLBACK_OPTION = 'snfla_fallback_window';
	const RETIREMENT_OPTION = 'snfla_retirement_evidence';

	public static function states() {
		return array(
			'legacy_active',
			'inventory_locked',
			'dry_run_ready',
			'batch_migration',
			'reconciliation',
			'redirect_cutover',
			'read_only_fallback',
			'retired',
		);
	}

	public static function transitions() {
		return array(
			'legacy_active'       => array( 'inventory_locked' ),
			'inventory_locked'    => array( 'legacy_active', 'inventory_locked', 'dry_run_ready' ),
			'dry_run_ready'       => array( 'inventory_locked', 'dry_run_ready', 'batch_migration', 'reconciliation' ),
			'batch_migration'     => array( 'dry_run_ready', 'batch_migration', 'reconciliation' ),
			'reconciliation'      => array( 'batch_migration', 'reconciliation', 'redirect_cutover' ),
			'redirect_cutover'    => array( 'reconciliation', 'read_only_fallback' ),
			'read_only_fallback'  => array( 'redirect_cutover', 'retired' ),
			'retired'             => array(),
		);
	}

	public static function state() {
		$state = sanitize_key( (string) get_option( self::STATE_OPTION, 'legacy_active' ) );
		return in_array( $state, self::states(), true ) ? $state : 'legacy_active';
	}

	public static function version() {
		return max( 1, absint( get_option( self::STATE_VERSION_OPTION, 1 ) ) );
	}

	public static function transition( $to, $expected_state, $expected_version, $actor_id, $context = array() ) {
		$to               = sanitize_key( $to );
		$expected_state   = sanitize_key( $expected_state );
		$expected_version = absint( $expected_version );
		$current          = self::state();
		$version          = self::version();

		if ( $current !== $expected_state || $version !== $expected_version ) {
			return new WP_Error( 'snfla_state_conflict', 'The lifecycle state changed. Reload status before retrying.', array( 'status' => 409 ) );
		}
		$allowed = self::transitions();
		if ( ! isset( $allowed[ $current ] ) || ! in_array( $to, $allowed[ $current ], true ) ) {
			return new WP_Error( 'snfla_invalid_transition', 'The requested lifecycle transition is not permitted.', array( 'status' => 409 ) );
		}

		update_option( self::STATE_OPTION, $to, false );
		update_option( self::STATE_VERSION_OPTION, $version + 1, false );
		SNFLA_Audit::record(
			'lifecycle_transitioned',
			$actor_id,
			array_merge(
				array( 'from' => $current, 'to' => $to, 'previous_version' => $version, 'new_version' => $version + 1 ),
				is_array( $context ) ? $context : array()
			)
		);
		return array( 'state' => $to, 'version' => $version + 1 );
	}


	public static function assert_current( $expected_state, $expected_version ) {
		$expected_state   = sanitize_key( $expected_state );
		$expected_version = absint( $expected_version );
		if ( self::state() !== $expected_state || self::version() !== $expected_version ) {
			return new WP_Error( 'snfla_state_conflict', 'The lifecycle state changed. Reload status before retrying.', array( 'status' => 409 ) );
		}
		return true;
	}

	public static function recover_to_batch( $expected_state, $expected_version, $actor_id, $context = array() ) {
		$check = self::assert_current( $expected_state, $expected_version );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$current = self::state();
		if ( 'retired' === $current || ! in_array( $current, array( 'dry_run_ready', 'batch_migration', 'reconciliation', 'redirect_cutover', 'read_only_fallback' ), true ) ) {
			return new WP_Error( 'snfla_recovery_transition_denied', 'The current lifecycle state cannot enter rollback recovery.', array( 'status' => 409 ) );
		}
		$version = self::version();
		update_option( self::STATE_OPTION, 'batch_migration', false );
		update_option( self::STATE_VERSION_OPTION, $version + 1, false );
		SNFLA_Audit::record( 'lifecycle_recovered_to_batch', $actor_id, array_merge( array( 'from' => $current, 'to' => 'batch_migration', 'previous_version' => $version, 'new_version' => $version + 1 ), is_array( $context ) ? $context : array() ) );
		return array( 'state' => 'batch_migration', 'version' => $version + 1 );
	}

	public static function public_status() {
		return array(
			'state'   => self::state(),
			'version' => self::version(),
		);
	}
}
