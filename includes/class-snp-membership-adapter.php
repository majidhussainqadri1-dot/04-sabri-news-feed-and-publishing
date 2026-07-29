<?php
defined( 'ABSPATH' ) || exit;

/**
 * Read-only bridge to File 00 — Sabri Membership Core.
 */
final class SNP_Membership_Adapter {
	public static function available() {
		return defined( 'SMC_VERSION' )
			&& function_exists( 'smc_get_profile' )
			&& function_exists( 'smc_user_status' );
	}

	public static function profile( $user_id ) {
		return self::available() ? (array) smc_get_profile( absint( $user_id ) ) : array();
	}

	public static function status( $user_id ) {
		return self::available() ? sanitize_key( (string) smc_user_status( absint( $user_id ) ) ) : 'dependency_missing';
	}

	public static function is_active( $user_id ) {
		return in_array( self::status( $user_id ), array( 'approved', 'verified' ), true );
	}

	public static function account_type( $user_id ) {
		$profile = self::profile( $user_id );
		$type    = isset( $profile['account_type'] ) ? sanitize_key( (string) $profile['account_type'] ) : '';
		if ( ! $type ) {
			$type = sanitize_key( (string) get_user_meta( absint( $user_id ), '_smc_requested_role', true ) );
		}
		return $type;
	}

	public static function is_doctor( $user_id ) {
		return 'sabri_doctor' === self::account_type( $user_id );
	}

	public static function is_founder( $user_id ) {
		return self::available()
			&& function_exists( 'smc_is_founder' )
			&& smc_is_founder( absint( $user_id ) );
	}

	public static function founder_id() {
		$filtered = absint( apply_filters( 'snp_canonical_founder_user_id', 0 ) );
		if ( $filtered && self::is_founder( $filtered ) ) {
			return $filtered;
		}
		$users = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 2,
				'meta_key'   => '_smc_official_founder',
				'meta_value' => '1',
			)
		);
		return 1 === count( $users ) && self::is_founder( $users[0] ) ? absint( $users[0] ) : 0;
	}

	public static function has_capability( $user_id, $capability ) {
		$user_id    = absint( $user_id );
		$capability = sanitize_key( $capability );
		if ( ! $user_id || ! self::is_active( $user_id ) || ! $capability ) {
			return false;
		}
		$allowed = user_can( $user_id, $capability );
		return (bool) apply_filters( 'snp_membership_capability', $allowed, $user_id, $capability );
	}

	public static function audit( $event, array $context = array() ) {
		if ( ! self::available() ) {
			return false;
		}
		$payload = array(
			'event'      => sanitize_key( $event ),
			'actor_id'   => get_current_user_id(),
			'context'    => $context,
			'created_at' => current_time( 'mysql', true ),
		);
		do_action( 'smc_audit_event', 'file_04', $payload );
		return true;
	}
}
