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
		if ( ! in_array( $state, array( 'redirect_cutover', 'read_only_fallback', 'retired' ), true ) ) {
			self::private_legacy_response( 404 );
		}
		$legacy_id = get_queried_object_id();
		$target_id = SNFLA_File21_Adapter::target_for( $legacy_id );
		if ( $target_id > 0 && SNFLA_File21_Adapter::target_public( $target_id ) ) {
			$url = get_permalink( $target_id );
			if ( self::safe_target( $url, $legacy_id ) ) {
				wp_safe_redirect( $url, 'retired' === $state ? 301 : 302, 'Sabri File 04 Legacy Adapter' );
				exit;
			}
		}
		if ( 'read_only_fallback' === $state && self::fallback_active() ) {
			self::private_legacy_response( 410 );
		}
		self::private_legacy_response( 404 );
	}

	private static function safe_target( $url, $legacy_id ) {
		if ( ! is_string( $url ) || '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$legacy_url = get_permalink( absint( $legacy_id ) );
		return '' !== $home_host && $home_host === $url_host && untrailingslashit( $url ) !== untrailingslashit( (string) $legacy_url );
	}

	private static function private_legacy_response( $status ) {
		status_header( absint( $status ) );
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		global $wp_query;
		if ( isset( $wp_query ) ) {
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
			if ( ! SNFLA_Reconciliation::validate_current_report() ) {
				return new WP_Error( 'snfla_reconciliation_not_green', 'A fresh green reconciliation report is required before opening fallback.', array( 'status' => 412 ) );
			}
			$hours = min( 168, max( 1, absint( $hours ) ) );
			$window = SNFLA_Integrity::sign_evidence( array( 'opened_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'expires_at_utc' => gmdate( 'Y-m-d H:i:s', time() + $hours * HOUR_IN_SECONDS ), 'hours' => $hours, 'read_only' => true, 'tombstone_only' => true, 'reconciliation_checksum' => SNFLA_Reconciliation::report()['report_checksum'] ?? '' ) );
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
