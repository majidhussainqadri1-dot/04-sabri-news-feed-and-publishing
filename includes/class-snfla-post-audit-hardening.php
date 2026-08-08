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
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'harden_future_error_status' ), 10, 3 );
	}

	private static function is_future_route( $request ) {
		if ( ! $request instanceof WP_REST_Request ) {
			return false;
		}
		return 0 === strpos( (string) $request->get_route(), '/' . SNFLA_REST::NAMESPACE . '/future/' );
	}

	/**
	 * Future18 POST endpoints persist migration evidence/control state, so an
	 * active identity alone is insufficient. Require the same fresh File 00
	 * current-action / step-up authority used by the core migration workflows.
	 */
	public static function enforce_future_action_authority( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! self::is_future_route( $request ) ) {
			return $response;
		}
		$method = strtoupper( (string) $request->get_method() );
		if ( in_array( $method, array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
			return $response;
		}
		$actor = SNFLA_Capabilities::current_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : $response;
	}

	/**
	 * Future18's original response envelope intentionally hides WP_Error objects,
	 * but that also discarded their HTTP status. Restore non-2xx transport
	 * semantics so monitoring, clients and release automation cannot interpret a
	 * failed assurance check as HTTP success.
	 */
	public static function harden_future_error_status( $response, $server, $request ) {
		unset( $server );
		if ( ! self::is_future_route( $request ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_status' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! array_key_exists( 'ok', $data ) || false !== $data['ok'] ) {
			return $response;
		}
		$code = sanitize_key( (string) ( $data['data']['error'] ?? 'snfla_future_request_failed' ) );
		$response->set_status( self::status_for_error_code( $code ) );
		if ( method_exists( $response, 'header' ) ) {
			$response->header( 'X-SNFLA-Error-Code', $code );
		}
		return $response;
	}

	private static function status_for_error_code( $code ) {
		if ( false !== strpos( $code, 'unavailable' ) ) { return 503; }
		if ( false !== strpos( $code, 'locked' ) ) { return 423; }
		if ( false !== strpos( $code, 'persist_failed' ) || false !== strpos( $code, 'audit_failed' ) || false !== strpos( $code, 'query_failed' ) || false !== strpos( $code, 'containment_failed' ) || false !== strpos( $code, 'run_finish_failed' ) ) { return 500; }
		if ( false !== strpos( $code, 'inventory_changed' ) || false !== strpos( $code, 'actor_changed' ) ) { return 409; }
		if ( false !== strpos( $code, 'not_found' ) || false !== strpos( $code, 'record_missing' ) || false !== strpos( $code, 'source_missing' ) || false !== strpos( $code, 'invalid_source' ) ) { return 404; }
		if ( false !== strpos( $code, 'target_invalid' ) || false !== strpos( $code, 'proof_required' ) || false !== strpos( $code, 'not_green' ) || false !== strpos( $code, 'contract_' ) ) { return 412; }
		if ( false !== strpos( $code, 'authentication_required' ) ) { return 401; }
		if ( false !== strpos( $code, 'forbidden' ) && 'snfla_gameday_environment_forbidden' !== $code ) { return 403; }
		return 400;
	}
}
