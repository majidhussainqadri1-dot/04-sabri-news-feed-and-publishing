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
	const VERSION                  = '1.0.0';
	const REDIRECT_EVIDENCE_OPTION = 'snfla_future18_redirect_observatory_evidence_v2';

	public static function boot() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'enforce_future_action_authority' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'harden_future_response' ), 10, 3 );
		add_filter( 'snfla_visual_migration_diff_provider_v1', array( __CLASS__, 'validate_visual_diff_evidence' ), 999, 2 );
		add_filter( 'snfla_redirect_citation_observatory_v1', array( __CLASS__, 'validate_redirect_observatory_evidence' ), 999, 2 );
		add_filter( 'snfla_disaster_recovery_gameday_v1', array( __CLASS__, 'validate_gameday_evidence' ), 999, 2 );
		add_filter( 'snfla_release_readiness_v1', array( __CLASS__, 'harden_release_readiness' ), 50, 1 );
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
	 * Receipt creation additionally requires proof that the claimed underlying
	 * migration/reconciliation/rollback/cutover event actually exists.
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
		if ( is_wp_error( $actor ) ) {
			return $actor;
		}
		if ( '/' . SNFLA_REST::NAMESPACE . '/future/receipt' === (string) $request->get_route() ) {
			$receipt_gate = self::validate_receipt_request( $request );
			if ( is_wp_error( $receipt_gate ) ) { return $receipt_gate; }
		}
		return $response;
	}

	/**
	 * A cryptographic receipt must attest a real, independently verifiable
	 * operation. Merely knowing two IDs and an operation label cannot mint an
	 * authoritative migration receipt.
	 */
	private static function validate_receipt_request( WP_REST_Request $request ) {
		$operation = sanitize_key( (string) $request->get_param( 'operation' ) );
		$legacy_id = absint( $request->get_param( 'legacy_id' ) );
		$target_id = absint( $request->get_param( 'target_id' ) );
		$mapped = $legacy_id > 0 && $target_id > 0
			&& $target_id === SNFLA_File21_Adapter::target_for( $legacy_id )
			&& SNFLA_File21_Adapter::migration_target_valid( $legacy_id, $target_id );

		switch ( $operation ) {
			case 'migration':
				$verified = $mapped && SNFLA_Checksum::migration_equivalent( $legacy_id, $target_id );
				break;
			case 'reconciliation':
				$verified = $mapped && SNFLA_Reconciliation::validate_current_report();
				break;
			case 'rollback':
				$proof = SNFLA_Rollback::proof();
				$proof_ids = is_array( $proof ) ? SNFLA_Integrity::normalized_ids( (array) ( $proof['legacy_ids'] ?? array() ), SNFLA_Rollback::MAX_BATCH ) : array();
				$locked = SNFLA_Inventory::locked();
				$signature = (string) ( $locked['source_signature'] ?? '' );
				$verified = SNFLA_Integrity::evidence_valid( $proof )
					&& in_array( $legacy_id, $proof_ids, true )
					&& '' !== $signature
					&& hash_equals( $signature, (string) ( $proof['source_signature'] ?? '' ) );
				break;
			case 'cutover':
				$locked = SNFLA_Inventory::locked();
				$signature = (string) ( $locked['source_signature'] ?? '' );
				$verified = $mapped && '' !== $signature
					&& SNFLA_Audit::has_event( 'redirect_cutover_side_effects_completed', 'cutover:' . $signature );
				break;
			default:
				$verified = false;
				break;
		}
		return $verified ? true : new WP_Error(
			'snfla_receipt_evidence_unverified',
			'The requested cryptographic receipt is blocked because the underlying operation cannot be independently verified.',
			array( 'status' => 412, 'operation' => $operation, 'legacy_id' => $legacy_id )
		);
	}

	/**
	 * Preserve real HTTP failure semantics and make redirect/citation provider
	 * evidence release-blocking rather than informational only.
	 */
	public static function harden_future_response( $response, $server, $request ) {
		unset( $server );
		if ( ! self::is_future_route( $request ) || ! is_object( $response ) || ! method_exists( $response, 'get_data' ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! array_key_exists( 'ok', $data ) ) {
			return $response;
		}
		if ( false === $data['ok'] && method_exists( $response, 'set_status' ) ) {
			$code = sanitize_key( (string) ( $data['data']['error'] ?? 'snfla_future_request_failed' ) );
			$response->set_status( self::status_for_error_code( $code ) );
			if ( method_exists( $response, 'header' ) ) { $response->header( 'X-SNFLA-Error-Code', $code ); }
			return $response;
		}
		if ( 'future18_redirect_observatory' === (string) ( $data['code'] ?? '' ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$provider = isset( $data['data']['provider_evidence'] ) && is_array( $data['data']['provider_evidence'] ) ? $data['data']['provider_evidence'] : array();
			if ( empty( $provider['verified'] ) ) {
				$data['data']['release_blocking'] = true;
				$data['data']['provider_verification_required'] = true;
				if ( method_exists( $response, 'set_data' ) ) { $response->set_data( $data ); }
			}
		}
		return $response;
	}

	/**
	 * File 20/File 25 visual-diff evidence must demonstrate the full requested
	 * matrix. A provider-level "verified" flag without critical-diff counts and
	 * explicit desktop/mobile/RTL/a11y coverage is not release evidence.
	 */
	public static function validate_visual_diff_evidence( $evidence, $request ) {
		$evidence = is_array( $evidence ) ? $evidence : array();
		$request  = is_array( $request ) ? $request : array();
		$required = array_values( array_unique( array_map( 'sanitize_key', (array) ( $request['required'] ?? array() ) ) ) );
		$matrix   = self::evidence_matrix( $evidence );
		$missing  = self::missing_or_failed( $required, $matrix );
		$source_bound = self::provider_request_bound( $evidence, $request );
		$counts_valid = isset( $evidence['diff_count'], $evidence['critical_diff_count'] )
			&& is_numeric( $evidence['diff_count'] )
			&& is_numeric( $evidence['critical_diff_count'] )
			&& (float) $evidence['diff_count'] >= 0
			&& (float) $evidence['critical_diff_count'] >= 0;
		$valid = ! empty( $evidence['verified'] )
			&& ! empty( $evidence['provider_id'] )
			&& $counts_valid
			&& 0 === absint( $evidence['critical_diff_count'] )
			&& $source_bound
			&& empty( $missing );
		$evidence['verified'] = $valid;
		$evidence['hardening_validation'] = array(
			'full_requested_matrix_passed' => empty( $missing ),
			'missing_or_failed_checks'     => $missing,
			'counts_valid'                 => $counts_valid,
			'critical_diff_count_zero'     => $counts_valid && 0 === absint( $evidence['critical_diff_count'] ),
			'provider_request_source_bound' => $source_bound,
		);
		return $evidence;
	}

	/**
	 * A valid mapping/permalink alone does not prove redirect and citation
	 * continuity. Require every requested HTTP/loop/chain/query/fragment/citation
	 * check, persist only a signed bounded summary, and feed it into production
	 * readiness without creating a second URL truth store.
	 */
	public static function validate_redirect_observatory_evidence( $evidence, $request ) {
		$evidence = is_array( $evidence ) ? $evidence : array();
		$request  = is_array( $request ) ? $request : array();
		$required = array_values( array_unique( array_map( 'sanitize_key', (array) ( $request['checks'] ?? array() ) ) ) );
		$matrix   = self::evidence_matrix( $evidence );
		$missing  = self::missing_or_failed( $required, $matrix );
		$source_bound = self::provider_request_bound( $evidence, $request );
		$valid    = ! empty( $evidence['verified'] ) && ! empty( $evidence['provider_id'] ) && ! empty( $required ) && $source_bound && empty( $missing );
		$evidence['verified'] = $valid;
		$evidence['hardening_validation'] = array(
			'all_requested_checks_passed' => empty( $missing ) && ! empty( $required ),
			'missing_or_failed_checks'    => $missing,
			'provider_request_source_bound'=> $source_bound,
		);
		$locked = SNFLA_Inventory::locked();
		$summary = array(
			'schema'            => 2,
			'feature_id'        => 'F04-FUT-014',
			'checked_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
			'source_signature'  => sanitize_text_field( (string) ( $request['source_signature'] ?? '' ) ),
			'request_digest'    => sanitize_text_field( (string) ( $request['request_digest'] ?? '' ) ),
			'provider_digest'   => ! empty( $evidence['provider_id'] ) ? hash( 'sha256', (string) $evidence['provider_id'] ) : '',
			'required_checks'   => $required,
			'failed_checks'     => $missing,
			'verified'          => $valid,
			'contains_raw_url'  => false,
			'contains_raw_pii'  => false,
		);
		$previous = get_option( self::REDIRECT_EVIDENCE_OPTION, array() );
		$signed = SNFLA_Integrity::sign_evidence( $summary );
		$persisted = update_option( self::REDIRECT_EVIDENCE_OPTION, $signed, false ) || get_option( self::REDIRECT_EVIDENCE_OPTION, array() ) === $signed;
		if ( ! $persisted ) {
			$evidence['verified'] = false;
			$evidence['hardening_validation']['persistence_failed'] = true;
			return $evidence;
		}
		if ( ! SNFLA_Audit::record( 'future18_redirect_observatory_verified', get_current_user_id(), array( 'source_signature' => (string) ( $summary['source_signature'] ?? '' ), 'request_digest' => (string) ( $summary['request_digest'] ?? '' ), 'verified' => (bool) $valid ), 'future18-redirect:' . (string) ( $summary['request_digest'] ?? '' ) ) ) {
			update_option( self::REDIRECT_EVIDENCE_OPTION, $previous, false );
			$evidence['verified'] = false;
			$evidence['hardening_validation']['audit_persistence_failed'] = true;
		}
		return $evidence;
	}

	/**
	 * A DR provider cannot self-attest success with one boolean. Every exercise
	 * requested by File 04 must have an explicit passing result and the provider
	 * must attest that no production environment was used.
	 */
	public static function validate_gameday_evidence( $evidence, $request ) {
		$evidence = is_array( $evidence ) ? $evidence : array();
		$request  = is_array( $request ) ? $request : array();
		$required = array_values( array_unique( array_map( 'sanitize_key', (array) ( $request['required_exercises'] ?? array() ) ) ) );
		$matrix   = array();
		foreach ( array( 'exercises', 'results', 'checks' ) as $key ) {
			if ( isset( $evidence[ $key ] ) && is_array( $evidence[ $key ] ) ) { $matrix = $evidence[ $key ]; break; }
		}
		$missing = self::missing_or_failed( $required, $matrix );
		$source_bound = self::provider_request_bound( $evidence, $request );
		$production_safe = empty( $evidence['production_environment'] )
			&& empty( $evidence['production_used'] )
			&& empty( $evidence['production_chaos_performed'] );
		$valid = ! empty( $evidence['verified'] )
			&& ! empty( $evidence['provider_id'] )
			&& 'disposable_staging' === sanitize_key( (string) ( $request['environment'] ?? '' ) )
			&& false === (bool) ( $request['production_chaos_allowed'] ?? true )
			&& ! empty( $required )
			&& empty( $missing )
			&& $source_bound
			&& $production_safe;
		$evidence['verified'] = $valid;
		$evidence['hardening_validation'] = array(
			'all_required_exercises_passed' => ! empty( $required ) && empty( $missing ),
			'missing_or_failed_exercises'   => $missing,
			'production_environment_refused'=> $production_safe,
			'provider_request_source_bound' => $source_bound,
		);
		return $evidence;
	}

	public static function harden_release_readiness( $readiness ) {
		$readiness = is_array( $readiness ) ? $readiness : array();
		$evidence  = get_option( self::REDIRECT_EVIDENCE_OPTION, array() );
		$locked    = SNFLA_Inventory::locked();
		$source_signature = (string) ( $locked['source_signature'] ?? '' );
		$valid = SNFLA_Integrity::evidence_valid( $evidence )
			&& ! empty( $evidence['verified'] )
			&& '' !== $source_signature
			&& hash_equals( $source_signature, (string) ( $evidence['source_signature'] ?? '' ) );
		$readiness['post_audit_hardening'] = array(
			'redirect_citation_observatory_verified' => $valid,
			'hardening_version' => self::VERSION,
		);
		if ( ! $valid ) {
			$readiness['production_ready'] = false;
			$blockers = isset( $readiness['blockers'] ) && is_array( $readiness['blockers'] ) ? $readiness['blockers'] : array();
			$blockers[] = 'redirect_citation_observatory_evidence_pending';
			$readiness['blockers'] = array_values( array_unique( $blockers ) );
		}
		return $readiness;
	}


	private static function provider_request_bound( array $evidence, array $request ) {
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$request_digest   = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$echo_signature   = strtolower( trim( (string) ( $evidence['source_signature'] ?? '' ) ) );
		$echo_digest      = strtolower( trim( (string) ( $evidence['request_digest'] ?? '' ) ) );
		$payload = $request;
		unset( $payload['request_digest'] );
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $source_signature )
			&& 1 === preg_match( '/^[a-f0-9]{64}$/', $request_digest )
			&& hash_equals( $request_digest, SNFLA_Checksum::hash( $payload ) )
			&& hash_equals( $source_signature, $echo_signature )
			&& hash_equals( $request_digest, $echo_digest );
	}

	private static function evidence_matrix( array $evidence ) {
		foreach ( array( 'checks', 'coverage', 'matrix', 'results' ) as $key ) {
			if ( isset( $evidence[ $key ] ) && is_array( $evidence[ $key ] ) ) { return $evidence[ $key ]; }
		}
		return array();
	}

	private static function missing_or_failed( array $required, array $matrix ) {
		$missing = array();
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $matrix ) || ! self::evidence_item_passed( $matrix[ $key ] ) ) { $missing[] = $key; }
		}
		return $missing;
	}

	private static function evidence_item_passed( $value ) {
		if ( true === $value || 1 === $value || '1' === $value ) { return true; }
		if ( is_string( $value ) ) { return in_array( strtolower( trim( $value ) ), array( 'pass', 'passed', 'ok', 'verified', 'green' ), true ); }
		if ( ! is_array( $value ) ) { return false; }
		if ( ! empty( $value['passed'] ) || ! empty( $value['verified'] ) || ! empty( $value['ok'] ) ) { return true; }
		$status = strtolower( trim( (string) ( $value['status'] ?? '' ) ) );
		return in_array( $status, array( 'pass', 'passed', 'ok', 'verified', 'green' ), true );
	}

	private static function status_for_error_code( $code ) {
		if ( false !== strpos( $code, 'unavailable' ) ) { return 503; }
		if ( false !== strpos( $code, 'locked' ) ) { return 423; }
		if ( false !== strpos( $code, 'persist_failed' ) || false !== strpos( $code, 'audit_failed' ) || false !== strpos( $code, 'query_failed' ) || false !== strpos( $code, 'containment_failed' ) || false !== strpos( $code, 'compensation_failed' ) || false !== strpos( $code, 'run_finish_failed' ) ) { return 500; }
		if ( false !== strpos( $code, 'inventory_changed' ) || false !== strpos( $code, 'actor_changed' ) ) { return 409; }
		if ( false !== strpos( $code, 'not_found' ) || false !== strpos( $code, 'record_missing' ) || false !== strpos( $code, 'source_missing' ) || false !== strpos( $code, 'invalid_source' ) ) { return 404; }
		if ( false !== strpos( $code, 'target_invalid' ) || false !== strpos( $code, 'proof_required' ) || false !== strpos( $code, 'not_green' ) || false !== strpos( $code, 'contract_' ) || false !== strpos( $code, 'evidence_unverified' ) ) { return 412; }
		if ( false !== strpos( $code, 'authentication_required' ) ) { return 401; }
		if ( false !== strpos( $code, 'forbidden' ) && 'snfla_gameday_environment_forbidden' !== $code ) { return 403; }
		return 400;
	}
}
