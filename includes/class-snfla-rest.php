<?php
defined( 'ABSPATH' ) || exit;

final class SNFLA_REST {
	const NAMESPACE = 'sabri/v1/legacy/file-04';

	public static function register() {
		register_rest_route( self::NAMESPACE, '/status', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/inventory', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'inventory' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/dry-run', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'dry_run' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/backup-proof', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'backup_proof' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/migrate', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'migrate' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/reconcile', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'reconcile' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/cutover', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'cutover' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/fallback', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'fallback' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/rollback', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'rollback' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/retire', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'retire' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( self::NAMESPACE, '/conflicts/resolve', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'resolve_conflict' ), 'permission_callback' => '__return_true' ) );
	}

	public static function status( WP_REST_Request $request ) {
		unset( $request );
		$actor = SNFLA_Capabilities::current_read_actor( SNFLA_Capabilities::CAP_REVIEW );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		global $wpdb;
		$t = SNFLA_Database::tables();
		$inventory = SNFLA_Inventory::locked();
		$dry = get_option( SNFLA_Schema::DRY_RUN_OPTION, array() );
		$reconciliation = SNFLA_Reconciliation::report();
		$data = array(
			'plugin'          => array( 'version' => SNFLA_VERSION, 'schema_version' => SNFLA_SCHEMA_VERSION, 'role' => 'legacy_foundation_adapter', 'canonical_owner' => 'File 21' ),
			'lifecycle'       => SNFLA_Schema::public_status(),
			'file21'          => SNFLA_File21_Adapter::status(),
			'inventory'       => empty( $inventory ) ? array() : array( 'captured_at_utc' => $inventory['captured_at_utc'] ?? '', 'source_signature' => $inventory['source_signature'] ?? '', 'post_counts' => $inventory['post_counts'] ?? array(), 'table_counts' => $inventory['table_counts'] ?? array() ),
			'dry_run'         => empty( $dry ) ? array() : array( 'created_at_utc' => $dry['created_at_utc'] ?? '', 'candidate_count' => $dry['candidate_count'] ?? 0, 'conflict_count' => count( (array) ( $dry['conflicts'] ?? array() ) ), 'report_checksum' => $dry['report_checksum'] ?? '' ),
			'reconciliation'  => empty( $reconciliation ) ? array() : array( 'green' => ! empty( $reconciliation['green'] ), 'source_total' => $reconciliation['source_total'] ?? 0, 'verified_mappings' => $reconciliation['verified_mappings'] ?? 0, 'open_conflicts' => $reconciliation['open_conflicts'] ?? 0, 'report_checksum' => $reconciliation['report_checksum'] ?? '' ),
			'open_conflicts'  => SNFLA_Mapping::open_conflict_count(),
			'mapping_counts'  => array(
				'migrated'    => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$t['map']} WHERE status='migrated'" ) ),
				'rolled_back' => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$t['map']} WHERE status='rolled_back'" ) ),
				'conflict'    => absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$t['map']} WHERE status='conflict'" ) ),
			),
			'backup_proof'     => array( 'valid' => SNFLA_Migration::backup_proof_valid() ),
			'fallback'         => array( 'active' => SNFLA_Redirects::fallback_active(), 'window' => get_option( SNFLA_Schema::FALLBACK_OPTION, array() ) ),
			'retirement'       => get_option( SNFLA_Schema::RETIREMENT_OPTION, array() ),
			'mutations_allowed'=> SNFLA_Retirement::mutations_allowed(),
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
		return self::result( 'snfla_backup_proof_recorded', SNFLA_Migration::record_backup_proof( $actor, $request->get_param( 'reference' ), $request->get_param( 'checksum' ), $request->get_param( 'created_at_utc' ) ) );
	}

	public static function migrate( WP_REST_Request $request ) {
		$actor = self::authorize( $request, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $actor ) ) { return self::failure( $actor ); }
		return self::result( 'snfla_migration_completed', SNFLA_Migration::migrate( $actor, (array) $request->get_param( 'legacy_ids' ), $request->get_header( 'Idempotency-Key' ) ?: $request->get_param( 'idempotency_key' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ), true ) );
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
		return self::result( 'snfla_rollback_completed', SNFLA_Rollback::execute( $actor, (array) $request->get_param( 'legacy_ids' ), $request->get_header( 'Idempotency-Key' ) ?: $request->get_param( 'idempotency_key' ), $request->get_param( 'expected_state' ), $request->get_param( 'expected_version' ) ) );
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
		return new WP_REST_Response( array( 'ok' => true, 'code' => sanitize_key( $code ), 'trace_id' => wp_generate_uuid4(), 'data' => $data ), 200 );
	}

	private static function failure( WP_Error $error ) {
		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 400;
		$public_data = is_array( $data ) ? SNFLA_Audit::redact( $data ) : array();
		unset( $public_data['status'] );
		return new WP_REST_Response( array( 'ok' => false, 'code' => sanitize_key( $error->get_error_code() ), 'trace_id' => wp_generate_uuid4(), 'message' => sanitize_text_field( $error->get_error_message() ), 'data' => $public_data ), $status );
	}
}
