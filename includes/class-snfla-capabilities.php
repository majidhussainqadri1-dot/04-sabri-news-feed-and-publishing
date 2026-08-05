<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Capabilities {
	const CAP_RUN     = 'sabri_file04_run_migrations';
	const CAP_REVIEW  = 'sabri_file04_review_migrations';
	const CAP_RETIRE  = 'sabri_file04_retire_adapter';

	public static function file21_ready() {
		return defined( 'SABRI_HNF_VERSION' )
			&& defined( 'SABRI_HNF_PACKAGE_VERSION' )
			&& version_compare( SABRI_HNF_VERSION, SNFLA_FILE21_MIN_RUNTIME, '>=' )
			&& version_compare( SABRI_HNF_PACKAGE_VERSION, SNFLA_FILE21_MIN_PACKAGE, '>=' )
			&& class_exists( '\\Sabri\\HomeNewsFeed\\CanonicalIdentityAdapter' )
			&& class_exists( '\\Sabri\\HomeNewsFeed\\LegacyPublicationMigration' )
			&& class_exists( '\\Sabri\\HomeNewsFeed\\LegacyPublicationRollback' )
			&& class_exists( '\\Sabri\\HomeNewsFeed\\LegacyInteractionMigrationAdapter' );
	}

	public static function current_actor( $capability = self::CAP_RUN ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'snfla_authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		if ( ! self::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 is unavailable or incompatible.', array( 'status' => 503 ) );
		}
		$allowed = current_user_can( $capability ) || ( self::CAP_RUN === $capability && current_user_can( 'sabri_feed_run_migrations' ) );
		$allowed = (bool) apply_filters( 'snfla_actor_capability_allowed', $allowed, $user_id, $capability );
		if ( ! $allowed ) {
			return new WP_Error( 'snfla_forbidden', 'The current account lacks the migration capability supplied by canonical governance.', array( 'status' => 403 ) );
		}
		if ( ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return new WP_Error( 'snfla_step_up_required', 'Fresh File 00 identity and two-factor assurance are required.', array( 'status' => 403 ) );
		}
		return $user_id;
	}


	public static function current_read_actor( $capability = self::CAP_REVIEW ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'snfla_authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		$allowed = current_user_can( $capability ) || ( self::CAP_REVIEW === $capability && current_user_can( 'sabri_feed_run_migrations' ) );
		$allowed = (bool) apply_filters( 'snfla_read_capability_allowed', $allowed, $user_id, $capability );
		if ( ! $allowed ) {
			return new WP_Error( 'snfla_forbidden', 'The current account lacks the migration evidence capability.', array( 'status' => 403 ) );
		}
		return $user_id;
	}

	public static function verify_rest_nonce( $request ) {
		$nonce = $request instanceof WP_REST_Request ? (string) $request->get_header( 'X-WP-Nonce' ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'snfla_invalid_nonce', 'The REST security token is missing or invalid.', array( 'status' => 403 ) );
		}
		return true;
	}
}
