<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_Redirects {
	public static function register() {
		add_action( 'init', array( __CLASS__, 'disable_file21_automatic_redirect' ), 999 );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 0 );
	}

	public static function disable_file21_automatic_redirect() {
		if ( class_exists( '\\Sabri\\HomeNewsFeed\\LegacyPublicationMigration' ) ) {
			remove_action( 'template_redirect', array( '\\Sabri\\HomeNewsFeed\\LegacyPublicationMigration', 'redirect_migrated_legacy_single' ), 1 );
		}
	}

	public static function handle() {
		if ( ! is_singular( SNFLA_Inventory::LEGACY_POST_TYPE ) ) {
			return;
		}
		$state = SNFLA_Schema::state();
		if ( ! in_array( $state, array( 'redirect_cutover', 'read_only_fallback' ), true ) ) {
			self::private_legacy_response( 404 );
		}
		$legacy_id = get_queried_object_id();
		$mapping = SNFLA_Mapping::get_checked( $legacy_id );
		if ( is_wp_error( $mapping ) ) {
			self::private_legacy_response( 503 );
		}
		if ( is_array( $mapping ) && 'quarantined' === sanitize_key( (string) ( $mapping['status'] ?? '' ) ) ) {
			self::private_legacy_response( 410 );
		}
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		if ( $target_id > 0 && SNFLA_File21_Adapter::target_public( $target_id ) ) {
			$url = get_permalink( $target_id );
			if ( self::safe_target( $url, $legacy_id ) ) {
				wp_safe_redirect( $url, 301, 'Sabri File 04 Legacy Adapter' );
				exit;
			}
		}

		// The governing File 04 plan requires a time-bounded read-only fallback,
		// not a tombstone-only state. If File 21 is temporarily unavailable after
		// an otherwise valid cutover, a previously public legacy record may render
		// from its immutable source evidence for the active fallback window. The
		// legacy mutation guards remain installed globally, comments stay closed,
		// and the temporary source URL is noindex/no-store so it cannot become a
		// second permanent public route. A known non-public canonical target never
		// falls back to the public legacy body.
		if ( 'read_only_fallback' === $state && self::fallback_active() && 0 === $target_id && self::legacy_public_fallback_allowed( $legacy_id, $mapping ) ) {
			self::prepare_read_only_fallback_response();
			return;
		}

		if ( 'read_only_fallback' === $state && self::fallback_active() ) {
			self::private_legacy_response( 410 );
		}
		self::private_legacy_response( 404 );
	}

	private static function legacy_public_fallback_allowed( $legacy_id, $mapping ) {
		$post = get_post( absint( $legacy_id ) );
		if ( ! $post instanceof WP_Post || SNFLA_Inventory::LEGACY_POST_TYPE !== (string) $post->post_type || 'publish' !== (string) $post->post_status ) {
			return false;
		}
		$status = is_array( $mapping ) ? sanitize_key( (string) ( $mapping['status'] ?? '' ) ) : '';
		if ( in_array( $status, array( 'quarantined', 'conflict', 'rollback_conflict', 'rolled_back', 'publication_rolled_back_interactions_pending' ), true ) ) {
			return false;
		}
		return true;
	}

	private static function prepare_read_only_fallback_response() {
		status_header( 200 );
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-Sabri-File04-Fallback: read-only', true );
	}

	private static function safe_target( $url, $legacy_id ) {
		if ( ! is_string( $url ) || '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$home       = wp_parse_url( home_url( '/' ) );
		$target     = wp_parse_url( $url );
		$legacy_url = get_permalink( absint( $legacy_id ) );
		$legacy     = is_string( $legacy_url ) ? wp_parse_url( $legacy_url ) : false;
		if ( ! is_array( $home ) || ! is_array( $target ) || ! is_array( $legacy ) ) {
			return false;
		}
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_host     = strtolower( (string) ( $home['host'] ?? '' ) );
		$target_host   = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_port     = absint( $home['port'] ?? ( 'https' === $home_scheme ? 443 : 80 ) );
		$target_port   = absint( $target['port'] ?? ( 'https' === $target_scheme ? 443 : 80 ) );
		if ( '' === $home_scheme || '' === $home_host || $home_scheme !== $target_scheme || $home_host !== $target_host || $home_port !== $target_port ) {
			return false;
		}

		// Query strings and fragments cannot turn the same legacy path into a safe
		// redirect destination; comparing only full URLs can create a self-loop.
		$target_path = untrailingslashit( rawurldecode( '/' . ltrim( (string) ( $target['path'] ?? '/' ), '/' ) ) );
		$legacy_path = untrailingslashit( rawurldecode( '/' . ltrim( (string) ( $legacy['path'] ?? '/' ), '/' ) ) );
		return $target_path !== $legacy_path;
	}

	private static function private_legacy_response( $status ) {
		status_header( absint( $status ) );
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		global $wp_query;
		if ( 404 === absint( $status ) && isset( $wp_query ) ) {
			$wp_query->set_404();
		}
		$template = get_404_template();
		if ( $template ) {
			include $template;
		}
		exit;
	}

	public static function open_fallback( $actor_id, $hours, $expected_state, $expected_version ) {
		if ( ! SNFLA_Database::acquire_lock( 'operation', 5 ) ) {
			return new WP_Error( 'snfla_operation_locked', 'Another File 04 operation is running.', array( 'status' => 423 ) );
		}
		try {
			$authorized_actor = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_REVIEW );
			if ( is_wp_error( $authorized_actor ) ) { return $authorized_actor; }
			$state_check = SNFLA_Schema::assert_current( sanitize_key( $expected_state ), absint( $expected_version ) );
			if ( is_wp_error( $state_check ) ) { return $state_check; }
			if ( ! SNFLA_Reconciliation::validate_current_report() ) {
				return new WP_Error( 'snfla_reconciliation_not_green', 'A fresh green reconciliation report is required before opening fallback.', array( 'status' => 412 ) );
			}
			$hours = min( 168, max( 1, absint( $hours ) ) );
			$window = SNFLA_Integrity::sign_evidence( array( 'opened_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'expires_at_utc' => gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ), 'hours' => $hours, 'read_only' => true, 'tombstone_only' => false, 'public_source_fallback' => true, 'reconciliation_checksum' => SNFLA_Reconciliation::report()['report_checksum'] ?? '' ) );
			$previous = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
			if ( ! update_option( SNFLA_Schema::FALLBACK_OPTION, $window, false ) ) { return new WP_Error( 'snfla_fallback_persist_failed', 'The fallback window could not be persisted.', array( 'status' => 500 ) ); }
			$transition = SNFLA_Schema::transition( 'read_only_fallback', sanitize_key( $expected_state ), absint( $expected_version ), $actor_id, array( 'hours' => $hours, 'fallback_checksum' => SNFLA_Checksum::hash( $window ) ) );
			if ( is_wp_error( $transition ) ) { update_option( SNFLA_Schema::FALLBACK_OPTION, $previous, false ); return $transition; }
			return array( 'window' => $window, 'lifecycle' => $transition );
		} finally {
			SNFLA_Database::release_lock( 'operation' );
		}
	}

	public static function fallback_active() {
		$window = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
		$expires = is_array( $window ) && ! empty( $window['expires_at_utc'] ) ? strtotime( $window['expires_at_utc'] . ' UTC' ) : false;
		return SNFLA_Integrity::evidence_valid( $window ) && false !== $expires && $expires >= time();
	}
}
