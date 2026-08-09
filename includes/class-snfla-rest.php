<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_REST {
	const NS = 'sabri/file04/v1';

	public static function register() {
		register_rest_route( self::NS, '/status', array( 'methods' => WP_REST_Server::READABLE, 'permission_callback' => array( __CLASS__, 'can_read' ), 'callback' => array( __CLASS__, 'status' ) ) );
		register_rest_route( self::NS, '/inventory/capture', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'capture_inventory' ), 'args' => self::state_args() ) );
		register_rest_route( self::NS, '/dry-run', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'dry_run' ), 'args' => array_merge( self::state_args(), array( 'force' => array( 'type' => 'boolean', 'default' => false ) ) ) ) );
		register_rest_route( self::NS, '/backup-proof', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'backup_proof' ), 'args' => array(
			'reference' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'checksum' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
			'created_at_utc' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'restore_reference' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'restore_checksum' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
			'restored_at_utc' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'restored_source_signature' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static function($v){ return is_string($v) && 1===preg_match('/^[a-f0-9]{64}$/Di',$v); } ),
			'restored_post_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ),
			'restored_comment_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ),
			'restored_table_counts' => array( 'required' => true, 'type' => 'object' ),
		) ) );
		register_rest_route( self::NS, '/migrate', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'migrate' ), 'args' => array_merge( self::state_args(), self::id_args(), array( 'idempotency_key' => self::opaque_idempotency_arg() ) ) ) );
		register_rest_route( self::NS, '/reconcile', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'reconcile' ), 'args' => self::state_args() ) );
		register_rest_route( self::NS, '/cutover', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'cutover' ), 'args' => self::state_args() ) );
		register_rest_route( self::NS, '/fallback/open', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'open_fallback' ), 'args' => array_merge( self::state_args(), array( 'hours' => array( 'type' => 'integer', 'default' => 24, 'minimum' => 1, 'maximum' => 168 ) ) ) ) );
		register_rest_route( self::NS, '/rollback', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_run' ), 'callback' => array( __CLASS__, 'rollback' ), 'args' => array_merge( self::state_args(), self::id_args(), array( 'idempotency_key' => self::opaque_idempotency_arg(), 'restore_handover' => array( 'type' => 'boolean', 'default' => false ), 'handover_confirmation' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) ) ) );
		register_rest_route( self::NS, '/retire', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_retire' ), 'callback' => array( __CLASS__, 'retire' ), 'args' => array_merge( self::state_args(), array( 'confirmation' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ) ) ) ) );
		register_rest_route( self::NS, '/conflicts/(?P<conflict_id>[1-9][0-9]*)/resolve', array( 'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => array( __CLASS__, 'can_review' ), 'callback' => array( __CLASS__, 'resolve_conflict' ), 'args' => array( 'conflict_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ), 'resolution_code' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ) ) ) );
	}

	private static function state_args() {
		return array(
			'expected_state' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => static function ( $value ) { return in_array( sanitize_key( (string) $value ), SNFLA_Schema::states(), true ); } ),
			'expected_version' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => static function ( $value ) { return is_int( $value ) && $value >= 1 ? $value : 0; }, 'validate_callback' => static function ( $value ) { return is_int( $value ) && $value >= 1; } ),
		);
	}

	private static function id_args() {
		return array(
			'legacy_ids' => array(
				'required' => true,
				'type' => 'array',
				'minItems' => 1,
				'maxItems' => SNFLA_Migration::MAX_BATCH,
				'items' => array( 'type' => 'integer', 'minimum' => 1 ),
				'validate_callback' => static function ( $value ) {
					if ( ! is_array( $value ) || empty( $value ) || count( $value ) > SNFLA_Migration::MAX_BATCH ) { return false; }
					$ids = array();
					foreach ( $value as $id ) {
						if ( ! is_int( $id ) || $id <= 0 || isset( $ids[ $id ] ) ) { return false; }
						$ids[ $id ] = true;
					}
					return true;
				},
			),
		);
	}

	private static function opaque_idempotency_arg() {
		return array( 'required' => true, 'type' => 'string', 'validate_callback' => static function ( $value ) { return is_string( $value ) && strlen( $value ) >= 16 && strlen( $value ) <= 190 && 1 === preg_match( '/^[!-~]+$/D', $value ); } );
	}

	public static function can_read( WP_REST_Request $request ) {
		$nonce = SNFLA_Capabilities::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) { return $nonce; }
		return SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
	}
	public static function can_run( WP_REST_Request $request ) { return self::authorize( $request, SNFLA_Capabilities::CAP_RUN ); }
	public static function can_review( WP_REST_Request $request ) { return self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW ); }
	public static function can_retire( WP_REST_Request $request ) { return self::authorize( $request, SNFLA_Capabilities::CAP_RETIRE ); }

	public static function status( WP_REST_Request $request ) {
		$actor = self::can_read( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$mapping = self::mapping_counts();
		if ( is_wp_error( $mapping ) ) { return self::failure( $mapping ); }
		$inventory = SNFLA_Inventory::locked();
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$reconciliation = SNFLA_Reconciliation::report();
		$dry_trusted = SNFLA_Integrity::report_checksum_valid( $dry )
			&& ! empty( $dry['report_checksum'] )
			&& SNFLA_Audit::has_event( 'lifecycle_transitioned', '', 'report_checksum', (string) $dry['report_checksum'] );
		$reconciliation_uuid = sanitize_text_field( (string) ( $reconciliation['report_uuid'] ?? '' ) );
		$reconciliation_trusted = SNFLA_Integrity::report_checksum_valid( $reconciliation )
			&& '' !== $reconciliation_uuid
			&& SNFLA_Audit::has_event( 'reconciliation_completed', 'reconciliation:' . $reconciliation_uuid, 'report_checksum', (string) ( $reconciliation['report_checksum'] ?? '' ) );
		$data = array(
			'file'            => '04',
			'version'         => SNFLA_VERSION,
			'lifecycle'       => SNFLA_Schema::public_status(),
			'inventory'       => SNFLA_Inventory::public_summary(),
			'dry_run'         => $dry_trusted ? array( 'evidence_valid' => true, 'created_at_utc' => $dry['created_at_utc'] ?? '', 'candidate_count' => $dry['candidate_count'] ?? 0, 'conflict_count' => absint( $dry['conflict_count'] ?? 0 ), 'eligible_count' => absint( $dry['eligible_count'] ?? 0 ), 'complete_scan' => ! empty( $dry['complete_scan'] ), 'report_checksum' => $dry['report_checksum'] ?? '' ) : array( 'evidence_valid' => false ),
			'reconciliation'  => $reconciliation_trusted ? array( 'evidence_valid' => true, 'green' => ! empty( $reconciliation['green'] ), 'source_total' => $reconciliation['source_total'] ?? 0, 'verified_mappings' => $reconciliation['verified_mappings'] ?? 0, 'open_conflicts' => $reconciliation['open_conflicts'] ?? 0, 'report_checksum' => $reconciliation['report_checksum'] ?? '' ) : array( 'evidence_valid' => false ),
			'mapping'         => $mapping,
			'fallback'        => self::fallback_status(),
			'retirement'      => self::retirement_status(),
			'integrity'       => self::integrity_status(),
			'legacy_writes'   => false,
			'canonical_owner' => 'File 21',
			'viewer'          => array( 'actor_digest' => SNFLA_Audit::actor_digest( $actor ) ),
		);
		return self::success( 'snfla_status', $data );
	}

	public static function capture_inventory( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_inventory_captured', SNFLA_Inventory::capture( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function dry_run( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_dry_run_completed', SNFLA_Migration::dry_run( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ), (bool) $request->get_param( 'force' ) ) );
	}

	public static function backup_proof( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$evidence=array(); foreach(array('reference','checksum','created_at_utc','restore_reference','restore_checksum','restored_at_utc','restored_source_signature','restored_post_count','restored_comment_count','restored_table_counts') as $key){$evidence[$key]=$request->get_param($key);} 
		return self::result( 'snfla_backup_proof_recorded', SNFLA_Migration::record_backup_proof( $actor, $evidence ) );
	}

	public static function migrate( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$key = self::idempotency_key( $request );
		if ( is_wp_error( $key ) ) { return self::failure( $key ); }
		return self::result( 'snfla_migration_completed', SNFLA_Migration::migrate( $actor, (array) $request->get_param( 'legacy_ids' ), $key, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function reconcile( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_reconciliation_completed', SNFLA_Reconciliation::run( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function cutover( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_cutover_completed', SNFLA_Reconciliation::approve_cutover( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function open_fallback( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_fallback_opened', SNFLA_Redirects::open_fallback( $actor, $request->get_param( 'hours' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function rollback( WP_REST_Request $request ) {
		$actor = self::can_run( $request );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$key = self::idempotency_key( $request );
		if ( is_wp_error( $key ) ) { return self::failure( $key ); }
		return self::result( 'snfla_rollback_completed', SNFLA_Rollback::execute( $actor, (array) $request->get_param( 'legacy_ids' ), $key, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ), (bool) $request->get_param( 'restore_handover' ), $request->get_param( 'handover_confirmation' ) ) );
	}

	public static function retire( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RETIRE );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_adapter_retired', SNFLA_Retirement::retire( $actor, $request->get_param( 'confirmation' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function resolve_conflict( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_conflict_resolved', SNFLA_Reconciliation::resolve_conflict( $actor, $request->get_param( 'conflict_id' ), $request->get_param( 'resolution_code' ) ) );
	}

	private static function idempotency_key( WP_REST_Request $request ) {
		// Idempotency keys are opaque security tokens, not human text. Sanitizing them can normalize distinct raw keys into one value and make unrelated requests share an operation ledger entry.
		$header = (string) $request->get_header( 'Idempotency-Key' );
		$body   = (string) $request->get_param( 'idempotency_key' );
		if ( '' !== $header && '' !== $body && ! hash_equals( $header, $body ) ) {
			return new WP_Error( 'snfla_idempotency_key_mismatch', 'The Idempotency-Key header and request field do not match.', array( 'status' => 400 ) );
		}
		$key = '' !== $header ? $header : $body;
		$length = strlen( $key );
		if ( $length < 16 || $length > 190 || 1 !== preg_match( '/^[!-~]+$/D', $key ) ) {
			return new WP_Error( 'snfla_invalid_idempotency_key', 'A stable ASCII idempotency key of 16–190 printable non-space characters is required.', array( 'status' => 400 ) );
		}
		return $key;
	}

	private static function mapping_counts() {
		global $wpdb;
		$t = SNFLA_Database::tables();
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( "SELECT status,COUNT(*) total FROM {$t['map']} GROUP BY status", ARRAY_A );
		if ( ! is_array( $rows ) || ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'snfla_mapping_status_query_failed', 'Migration status evidence could not be read safely.', array( 'status' => 500 ) );
		}
		$allowed_statuses = array( 'pending', 'migrating', 'migrated', 'interaction_pending', 'conflict', 'quarantined', 'publication_rolled_back_interactions_pending', 'rollback_conflict', 'rolled_back' );
		$raw = array();
		foreach ( $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			$total  = (string) ( $row['total'] ?? '' );
			if ( ! in_array( $status, $allowed_statuses, true ) || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', $total ) ) {
				return new WP_Error( 'snfla_mapping_status_corrupt', 'Migration status evidence contains an invalid state or count.', array( 'status' => 500 ) );
			}
			$raw[ $status ] = (int) $total;
		}
		$wpdb->last_error = '';
		$open_conflicts = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
		if ( ! empty( $wpdb->last_error ) || null === $open_conflicts || 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', (string) $open_conflicts ) ) {
			return new WP_Error( 'snfla_conflict_status_query_failed', 'Migration conflict evidence could not be read safely.', array( 'status' => 500 ) );
		}
		return array(
			'open_conflicts' => (int) $open_conflicts,
			'statuses'       => array(
				'migrated'           => (int) ( $raw['migrated'] ?? 0 ),
				'quarantined'        => (int) ( $raw['quarantined'] ?? 0 ),
				'interaction_pending' => (int) ( $raw['interaction_pending'] ?? 0 ),
				'rollback_pending'    => (int) ( $raw['publication_rolled_back_interactions_pending'] ?? 0 ),
				'rolled_back'        => (int) ( $raw['rolled_back'] ?? 0 ),
				'conflict'           => (int) ( $raw['conflict'] ?? 0 ) + (int) ( $raw['rollback_conflict'] ?? 0 ),
			),
		);
	}

	private static function fallback_status() {
		$window = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
		$trusted = SNFLA_Integrity::evidence_valid( $window );
		return array( 'evidence_valid' => $trusted, 'active' => $trusted && SNFLA_Redirects::fallback_active(), 'opened_at_utc' => $trusted ? sanitize_text_field( (string) ( $window['opened_at_utc'] ?? '' ) ) : '', 'expires_at_utc' => $trusted ? sanitize_text_field( (string) ( $window['expires_at_utc'] ?? '' ) ) : '', 'read_only' => $trusted && ! empty( $window['read_only'] ) );
	}

	private static function retirement_status() {
		$evidence = get_option( SNFLA_Schema::RETIREMENT_OPTION, array() );
		$trusted = SNFLA_Integrity::evidence_valid( $evidence );
		return array( 'recorded' => $trusted, 'retired_at_utc' => $trusted ? sanitize_text_field( (string) ( $evidence['retired_at_utc'] ?? '' ) ) : '', 'source_retained' => $trusted && ! empty( $evidence['source_retained'] ), 'redirect_handoff_count' => $trusted ? absint( $evidence['redirect_handoff_count'] ?? 0 ) : 0 );
	}

	private static function integrity_status() {
		$chain = SNFLA_Audit::verify_chain();
		$last  = get_option( 'snfla_last_integrity_check', array() );
		$last_trusted = SNFLA_Integrity::evidence_valid( $last );
		return array(
			'audit_chain' => array( 'valid' => ! empty( $chain['valid'] ), 'checked' => absint( $chain['checked'] ?? 0 ), 'error' => sanitize_key( (string) ( $chain['error'] ?? '' ) ) ),
			'last_check'  => $last_trusted ? array( 'evidence_valid' => true, 'checked_at_utc' => sanitize_text_field( (string) ( $last['checked_at_utc'] ?? '' ) ), 'ok' => ! empty( $last['ok'] ) ) : array( 'evidence_valid' => false ),
		);
	}

	private static function authorize( WP_REST_Request $request, $capability ) {
		if ( ! SNFLA_Retirement::mutations_allowed() ) {
			return new WP_Error( 'snfla_retired', 'The adapter is retired and mutation endpoints are disabled.', array( 'status' => 410 ) );
		}
		$nonce = SNFLA_Capabilities::verify_rest_nonce( $request );
		return is_wp_error( $nonce ) ? $nonce : SNFLA_Capabilities::current_actor( $capability );
	}

	private static function result( $code, $result ) {
		return is_wp_error( $result ) ? self::failure( $result ) : self::success( $code, $result );
	}

	private static function success( $code, $data ) {
		$response = new WP_REST_Response( array( 'ok' => true, 'code' => sanitize_key( $code ), 'trace_id' => wp_generate_uuid4(), 'data' => $data ), 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}

	private static function failure( WP_Error $error ) {
		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
		if ( $status < 400 || $status > 599 ) { $status = 500; }
		$public_data = is_array( $data ) ? SNFLA_Audit::redact( $data ) : array();
		unset( $public_data['status'] );
		$response = new WP_REST_Response( array( 'ok' => false, 'code' => sanitize_key( $error->get_error_code() ), 'trace_id' => wp_generate_uuid4(), 'message' => sanitize_text_field( $error->get_error_message() ), 'data' => $public_data ), $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}
}
