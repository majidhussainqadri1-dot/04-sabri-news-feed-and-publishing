<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_REST {
	const NAMESPACE = 'sabri/v1/legacy/file-04';

	public static function register() {
		$state_args = array(
			'expected_state' => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'validate_callback' => static function ( $value ) { return in_array( sanitize_key( (string) $value ), SNFLA_Schema::states(), true ); } ),
			'expected_version' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint', 'validate_callback' => static function ( $value ) { return absint( $value ) >= 1; } ),
		);
		$ids_arg = array( 'legacy_ids' => array( 'required' => true, 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'validate_callback' => static function ( $value ) { return is_array( $value ) && count( $value ) >= 1 && count( $value ) <= SNFLA_Migration::MAX_BATCH; } ) );
		self::route( '/status', WP_REST_Server::READABLE, 'status', array(), false );
		self::route( '/inventory', WP_REST_Server::CREATABLE, 'inventory', $state_args );
		self::route( '/dry-run', WP_REST_Server::CREATABLE, 'dry_run', array_merge( $state_args, array( 'limit' => array( 'type' => 'integer', 'default' => 500, 'minimum' => 50, 'maximum' => 1000, 'sanitize_callback' => 'absint' ) ) ) );
		self::route( '/backup-proof', WP_REST_Server::CREATABLE, 'backup_proof', array(
			'reference' => self::bounded_string_arg( true, 1, 190 ), 'checksum' => self::sha_arg(), 'created_at_utc' => self::bounded_string_arg( true, 10, 64 ),
			'restore_reference' => self::bounded_string_arg( true, 1, 190 ), 'restore_checksum' => self::sha_arg(), 'restored_at_utc' => self::bounded_string_arg( true, 10, 64 ),
			'restored_source_signature' => self::sha_arg(), 'restored_post_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ),
			'restored_comment_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 0 ), 'restored_table_counts' => array( 'required' => true, 'type' => 'object' ),
		) );
		self::route( '/migrate', WP_REST_Server::CREATABLE, 'migrate', array_merge( $state_args, $ids_arg, array( 'idempotency_key' => self::bounded_string_arg( false, 16, 190 ) ) ) );
		self::route( '/quarantine', WP_REST_Server::CREATABLE, 'quarantine', array_merge( $state_args, $ids_arg, array( 'reason_code' => self::bounded_string_arg( true, 3, 96 ), 'decision_reference' => self::bounded_string_arg( true, 8, 190 ) ) ) );
		self::route( '/interactions/resume', WP_REST_Server::CREATABLE, 'resume_interactions', array( 'legacy_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'max_records' => array( 'type' => 'integer', 'default' => SNFLA_Interaction_Provider::DEFAULT_RECORD_BUDGET, 'minimum' => SNFLA_Interaction_Provider::PAGE_SIZE, 'maximum' => SNFLA_Interaction_Provider::MAX_RECORD_BUDGET, 'sanitize_callback' => 'absint' ) ) );
		self::route( '/reconcile', WP_REST_Server::CREATABLE, 'reconcile', $state_args );
		self::route( '/cutover', WP_REST_Server::CREATABLE, 'cutover', $state_args );
		self::route( '/fallback', WP_REST_Server::CREATABLE, 'fallback', array_merge( $state_args, array( 'hours' => array( 'type' => 'integer', 'default' => 24, 'minimum' => 1, 'maximum' => 168, 'sanitize_callback' => 'absint' ) ) ) );
		self::route( '/rollback', WP_REST_Server::CREATABLE, 'rollback', array_merge( $state_args, $ids_arg, array( 'idempotency_key' => self::bounded_string_arg( false, 16, 190 ), 'restore_handover' => array( 'type' => 'boolean', 'default' => false ), 'handover_confirmation' => self::bounded_string_arg( false, 0, 190 ) ) ) );
		self::route( '/retire', WP_REST_Server::CREATABLE, 'retire', array_merge( $state_args, array( 'confirmation' => self::bounded_string_arg( true, 10, 190 ) ) ) );
		self::route( '/conflicts/resolve', WP_REST_Server::CREATABLE, 'resolve_conflict', array( 'conflict_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint' ), 'resolution_code' => self::bounded_string_arg( true, 3, 96 ) ) );
	}

	private static function route( $path, $methods, $callback, array $args, $mutating = true ) {
		register_rest_route( self::NAMESPACE, $path, array( 'methods' => $methods, 'callback' => array( __CLASS__, $callback ), 'permission_callback' => array( __CLASS__, $mutating ? 'permission_mutate' : 'permission_read' ), 'args' => $args ) );
	}

	private static function bounded_string_arg( $required, $minimum, $maximum ) {
		return array( 'required' => (bool) $required, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => static function ( $value ) use ( $minimum, $maximum ) { $length = strlen( trim( (string) $value ) ); return $length >= $minimum && $length <= $maximum; } );
	}

	private static function sha_arg() {
		return array( 'required' => true, 'type' => 'string', 'sanitize_callback' => static function ( $value ) { return strtolower( sanitize_text_field( (string) $value ) ); }, 'validate_callback' => static function ( $value ) { return 1 === preg_match( '/^[a-f0-9]{64}$/', strtolower( (string) $value ) ); } );
	}

	public static function permission_read( WP_REST_Request $request ) {
		unset( $request );
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		return is_wp_error( $actor ) ? $actor : true;
	}

	public static function permission_mutate( WP_REST_Request $request ) {
		if ( ! SNFLA_Retirement::mutations_allowed() ) {
			return new WP_Error( 'snfla_retired', 'The adapter is retired and mutation endpoints are disabled.', array( 'status' => 410 ) );
		}
		$nonce = SNFLA_Capabilities::verify_rest_nonce( $request );
		if ( is_wp_error( $nonce ) ) { return $nonce; }
		return get_current_user_id() > 0 ? true : new WP_Error( 'snfla_authentication_required', 'Authentication is required.', array( 'status' => 401 ) );
	}

	public static function status( WP_REST_Request $request ) {
		unset( $request );
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$mapping_counts = self::mapping_counts();
		if ( is_wp_error( $mapping_counts ) ) { return self::failure( $mapping_counts ); }
		$inventory = SNFLA_Inventory::locked();
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$reconciliation = SNFLA_Reconciliation::report();
		$data = array(
			'plugin'          => array( 'version' => SNFLA_VERSION, 'schema_version' => SNFLA_SCHEMA_VERSION, 'role' => 'legacy_foundation_adapter', 'canonical_owner' => 'File 21' ),
			'lifecycle'       => SNFLA_Schema::public_status(),
			'file21'          => SNFLA_File21_Adapter::status(),
			'inventory'       => empty( $inventory ) ? array() : array( 'captured_at_utc' => $inventory['captured_at_utc'] ?? '', 'source_signature' => $inventory['source_signature'] ?? '', 'post_counts' => $inventory['post_counts'] ?? array(), 'table_counts' => $inventory['table_counts'] ?? array() ),
			'dry_run'         => empty( $dry ) ? array() : array( 'created_at_utc' => $dry['created_at_utc'] ?? '', 'candidate_count' => $dry['candidate_count'] ?? 0, 'conflict_count' => absint( $dry['conflict_count'] ?? 0 ), 'eligible_count' => absint( $dry['eligible_count'] ?? 0 ), 'complete_scan' => ! empty( $dry['complete_scan'] ), 'report_checksum' => $dry['report_checksum'] ?? '' ),
			'reconciliation'  => empty( $reconciliation ) ? array() : array( 'green' => ! empty( $reconciliation['green'] ), 'source_total' => $reconciliation['source_total'] ?? 0, 'verified_mappings' => $reconciliation['verified_mappings'] ?? 0, 'open_conflicts' => $reconciliation['open_conflicts'] ?? 0, 'report_checksum' => $reconciliation['report_checksum'] ?? '' ),
			'open_conflicts'  => $mapping_counts['open_conflicts'],
			'mapping_counts'  => $mapping_counts['statuses'],
			'backup_proof'     => array( 'valid' => SNFLA_Migration::backup_proof_valid() ),
			'fallback'         => self::fallback_status(),
			'retirement'       => self::retirement_status(),
			'legacy_page_quarantine' => array( 'count' => count( SNFLA_Database::public_page_quarantine_status() ) ),
			'mutations_allowed'=> SNFLA_Retirement::mutations_allowed(),
			'integrity'        => self::integrity_status(),
		);
		return self::success( 'snfla_status', $data );
	}

	public static function inventory( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_inventory_locked', SNFLA_Inventory::lock( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function dry_run( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_dry_run_completed', SNFLA_Migration::dry_run( $actor, $request->get_param( 'limit' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function backup_proof( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_backup_proof_recorded', SNFLA_Migration::record_backup_proof( $actor, array( 'reference' => $request->get_param( 'reference' ), 'checksum' => $request->get_param( 'checksum' ), 'created_at_utc' => $request->get_param( 'created_at_utc' ), 'restore_reference' => $request->get_param( 'restore_reference' ), 'restore_checksum' => $request->get_param( 'restore_checksum' ), 'restored_at_utc' => $request->get_param( 'restored_at_utc' ), 'restored_source_signature' => $request->get_param( 'restored_source_signature' ), 'restored_post_count' => $request->get_param( 'restored_post_count' ), 'restored_comment_count' => $request->get_param( 'restored_comment_count' ), 'restored_table_counts' => (array) $request->get_param( 'restored_table_counts' ) ) ) );
	}

	public static function migrate( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$idempotency_key = self::idempotency_key( $request );
		if ( is_wp_error( $idempotency_key ) ) { return self::failure( $idempotency_key ); }
		return self::result( 'snfla_migration_completed', SNFLA_Migration::migrate( $actor, (array) $request->get_param( 'legacy_ids' ), $idempotency_key, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ), true ) );
	}

	public static function quarantine( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result(
			'snfla_quarantine_disposition_recorded',
			SNFLA_Migration::quarantine_disposition(
				$actor,
				(array) $request->get_param( 'legacy_ids' ),
				$request->get_param( 'reason_code' ),
				$request->get_param( 'decision_reference' ),
				$request->get_param( 'expected_state' ),
				$request->get_param( 'expected_version' )
			)
		);
	}

	public static function resume_interactions( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_interaction_migration_resumed', SNFLA_Interaction_Provider::resume( $actor, $request->get_param( 'legacy_id' ), $request->get_param( 'max_records' ) ?: SNFLA_Interaction_Provider::DEFAULT_RECORD_BUDGET ) );
	}

	public static function reconcile( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_reconciliation_completed', SNFLA_Reconciliation::run( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function cutover( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_redirect_cutover_approved', SNFLA_Reconciliation::approve_cutover( $actor, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function fallback( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_fallback_opened', SNFLA_Redirects::open_fallback( $actor, $request->get_param( 'hours' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
	}

	public static function rollback( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		$idempotency_key = self::idempotency_key( $request );
		if ( is_wp_error( $idempotency_key ) ) { return self::failure( $idempotency_key ); }
		return self::result( 'snfla_rollback_completed', SNFLA_Rollback::execute( $actor, (array) $request->get_param( 'legacy_ids' ), $idempotency_key, $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ), (bool) $request->get_param( 'restore_handover' ), $request->get_param( 'handover_confirmation' ) ) );
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
		// Idempotency keys are opaque security tokens, not human text. Sanitizing
		// them can normalize distinct raw keys into one value and make unrelated
		// requests share an operation ledger entry. Accept exact printable ASCII
		// bytes only, compare them byte-for-byte, and hash that exact accepted key.
		$header = trim( (string) $request->get_header( 'Idempotency-Key' ) );
		$body   = trim( (string) $request->get_param( 'idempotency_key' ) );
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
		$raw = array();
		foreach ( $rows as $row ) {
			$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
			if ( '' !== $status ) { $raw[ $status ] = absint( $row['total'] ?? 0 ); }
		}
		$wpdb->last_error = '';
		$open_conflicts = $wpdb->get_var( "SELECT COUNT(*) FROM {$t['conflicts']} WHERE status='open'" );
		if ( ! empty( $wpdb->last_error ) || null === $open_conflicts ) {
			return new WP_Error( 'snfla_conflict_status_query_failed', 'Migration conflict evidence could not be read safely.', array( 'status' => 500 ) );
		}
		return array(
			'open_conflicts' => absint( $open_conflicts ),
			'statuses'       => array(
				'migrated'           => absint( $raw['migrated'] ?? 0 ),
				'quarantined'        => absint( $raw['quarantined'] ?? 0 ),
				'interaction_pending' => absint( $raw['interaction_pending'] ?? 0 ),
				'rollback_pending'    => absint( $raw['publication_rolled_back_interactions_pending'] ?? 0 ),
				'rolled_back'        => absint( $raw['rolled_back'] ?? 0 ),
				'conflict'           => absint( $raw['conflict'] ?? 0 ) + absint( $raw['rollback_conflict'] ?? 0 ),
			),
		);
	}

	private static function fallback_status() {
		$window = get_option( SNFLA_Schema::FALLBACK_OPTION, array() );
		return array( 'active' => SNFLA_Redirects::fallback_active(), 'opened_at_utc' => is_array( $window ) ? sanitize_text_field( (string) ( $window['opened_at_utc'] ?? '' ) ) : '', 'expires_at_utc' => is_array( $window ) ? sanitize_text_field( (string) ( $window['expires_at_utc'] ?? '' ) ) : '', 'read_only' => is_array( $window ) && ! empty( $window['read_only'] ) );
	}

	private static function retirement_status() {
		$evidence = get_option( SNFLA_Schema::RETIREMENT_OPTION, array() );
		return array( 'recorded' => SNFLA_Integrity::evidence_valid( $evidence ), 'retired_at_utc' => is_array( $evidence ) ? sanitize_text_field( (string) ( $evidence['retired_at_utc'] ?? '' ) ) : '', 'source_retained' => is_array( $evidence ) && ! empty( $evidence['source_retained'] ), 'redirect_handoff_count' => is_array( $evidence ) ? absint( $evidence['redirect_handoff_count'] ?? 0 ) : 0 );
	}

	private static function integrity_status() {
		$chain = SNFLA_Audit::verify_chain();
		$last  = get_option( 'snfla_last_integrity_check', array() );
		return array( 'audit_chain' => array( 'valid' => ! empty( $chain['valid'] ), 'checked' => absint( $chain['checked'] ?? 0 ), 'error' => sanitize_key( (string) ( $chain['error'] ?? '' ) ) ), 'last_check' => is_array( $last ) ? array( 'checked_at_utc' => sanitize_text_field( (string) ( $last['checked_at_utc'] ?? '' ) ), 'ok' => ! empty( $last['ok'] ) ) : array() );
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
		$public_data = is_array( $data ) ? SNFLA_Audit::redact( $data ) : array();
		unset( $public_data['status'] );
		$response = new WP_REST_Response( array( 'ok' => false, 'code' => sanitize_key( $error->get_error_code() ), 'trace_id' => wp_generate_uuid4(), 'message' => sanitize_text_field( $error->get_error_message() ), 'data' => $public_data ), $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}
}
