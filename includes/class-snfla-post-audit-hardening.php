<?php
defined( 'ABSPATH' ) || exit;

/**
 * Post-Future18 hardening controls discovered by the ten-round adversarial audit.
 *
 * This class does not create a new domain owner. It only tightens authorization,
 * evidence validation and REST/release semantics around File 04-owned migration
 * assurance surfaces.
 */
final class SNFLA_Post_Audit_Hardening {
	const VERSION = '1.0.0';

	public static function boot() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'enforce_future_action_authority' ), 10, 3 );
	}

	/**
	 * Future18 POST endpoints persist migration evidence/control state, so an
	 * active identity alone is insufficient. Require the same fresh File 00
	 * current-action / step-up authority used by the core migration workflows.
	 */
	public static function enforce_future_action_authority( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! $request instanceof WP_REST_Request ) {
			return $response;
		}
		$route = (string) $request->get_route();
		$prefix = '/' . SNFLA_REST::NAMESPACE . '/future/';
		if ( 0 !== strpos( $route, $prefix ) ) {
			return $response;
		}
		$method = strtoupper( (string) $request->get_method() );
		if ( in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return $response;
		}
		$actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : $response;
	}
}
