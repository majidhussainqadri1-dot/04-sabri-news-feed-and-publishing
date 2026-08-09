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

	public static function activation_preflight() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'snfla_activation_authorization_failed', 'An authenticated plugin administrator is required for File 04 activation.' );
		}
		if ( ! self::file21_ready() ) {
			return new WP_Error( 'snfla_file21_activation_gate_failed', 'Canonical File 21 must be active and compatible before File 04 can disable the obsolete publishing runtime.' );
		}
		if ( ! current_user_can( 'sabri_feed_run_migrations' ) ) {
			return new WP_Error( 'snfla_file21_capability_activation_gate_failed', 'The current actor lacks File 21 migration authority.' );
		}
		if ( ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return new WP_Error( 'snfla_file00_activation_gate_failed', 'Fresh File 00 identity and two-factor assurance are required for File 04 activation.' );
		}
		return $user_id;
	}

	public static function current_actor( $capability = self::CAP_RUN ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'snfla_authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		if ( ! self::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 is unavailable or incompatible.', array( 'status' => 503 ) );
		}
		if ( class_exists( 'SNFLA_Database' ) && ! SNFLA_Database::schema_healthy() ) {
			return new WP_Error( 'snfla_schema_unhealthy', 'File 04 storage schema is not accepted for protected mutation.', array( 'status' => 503 ) );
		}
		$canonical_migration_authority = in_array( $capability, array( self::CAP_RUN, self::CAP_REVIEW ), true ) && current_user_can( 'sabri_feed_run_migrations' );
		$allowed = current_user_can( $capability ) || $canonical_migration_authority;
		// Extension filters may narrow an already-authorized decision, never grant authority.
		if ( $allowed ) {
			$allowed = (bool) apply_filters( 'snfla_actor_capability_allowed', true, $user_id, $capability );
		}
		if ( ! $allowed ) {
			return new WP_Error( 'snfla_forbidden', 'The current account lacks the migration capability supplied by canonical governance.', array( 'status' => 403 ) );
		}
		if ( ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return new WP_Error( 'snfla_step_up_required', 'Fresh File 00 identity and two-factor assurance are required.', array( 'status' => 403 ) );
		}
		return $user_id;
	}

	public static function revalidate_actor( $expected_actor_id, $capability = self::CAP_RUN ) {
		if ( ! is_int( $expected_actor_id ) || $expected_actor_id <= 0 ) { return new WP_Error( 'snfla_actor_identity_invalid', 'The protected operation requires a canonical positive actor identity.', array( 'status' => 400 ) ); }
		$actor_id = self::current_actor( $capability );
		if ( is_wp_error( $actor_id ) ) {
			return $actor_id;
		}
		if ( ! is_int( $actor_id ) || $actor_id !== $expected_actor_id ) {
			return new WP_Error( 'snfla_actor_changed', 'The authenticated actor changed while the protected operation was waiting for its lock.', array( 'status' => 409 ) );
		}
		return $actor_id;
	}

	public static function current_read_actor( $capability = self::CAP_REVIEW ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'snfla_authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
		}
		if ( ! self::file21_ready() ) {
			return new WP_Error( 'snfla_file21_unavailable', 'Canonical File 21 is unavailable or incompatible.', array( 'status' => 503 ) );
		}
		$allowed = current_user_can( $capability ) || ( self::CAP_REVIEW === $capability && current_user_can( 'sabri_feed_run_migrations' ) );
		// Read-authority extension filters are deny-only for the same fail-closed reason.
		if ( $allowed ) {
			$allowed = (bool) apply_filters( 'snfla_read_capability_allowed', true, $user_id, $capability );
		}
		if ( ! $allowed ) {
			return new WP_Error( 'snfla_forbidden', 'The current account lacks the migration evidence capability.', array( 'status' => 403 ) );
		}
		if ( ! \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::subject_is_active( $user_id ) ) {
			return new WP_Error( 'snfla_inactive_identity', 'File 00 does not currently authorize this identity to view migration evidence.', array( 'status' => 403 ) );
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
