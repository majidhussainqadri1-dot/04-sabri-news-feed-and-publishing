<?php
defined( 'ABSPATH' ) || exit;

/**
 * Versioned cross-file contracts for File 04.
 *
 * This adapter never takes ownership from File 01/19/20/21/24/25/26. It only
 * publishes bounded manifests/events and offers an explicit Foundation
 * registration command for an authorized migration operator.
 */
final class SNFLA_Cross_File_Contracts {
	const CONTRACT_VERSION = '1.0.0';
	const PRODUCER_KEY      = 'file-04';

	public static function boot() {
		add_filter( 'sun_registered_producers', array( __CLASS__, 'file19_producer' ) );
		add_filter( 'spcrc/module_manifests', array( __CLASS__, 'file24_manifests' ) );
		add_filter( 'spcrc/file04_contract_state', array( __CLASS__, 'file24_contract_state' ), 10, 2 );
		add_filter( 'sabri_file04_foundation_manifest_v1', array( __CLASS__, 'foundation_manifest' ) );
		add_filter( 'sabri_file04_route_context_v1', array( __CLASS__, 'route_context_contract' ) );
	}

	public static function file19_producer( $registry ) {
		$registry = is_array( $registry ) ? $registry : array();
		$registry[ self::PRODUCER_KEY ] = array(
			'owner'           => 'File 04',
			'event_types'     => array(
				'File04.LegacyMigrationBatchCompleted',
				'File04.LegacyRecordQuarantined',
				'File04.LegacyCutoverCompleted',
				'File04.LegacyAdapterRetired',
			),
			'schema_versions' => array( '1.0.0' ),
			'internal'        => true,
		);
		return $registry;
	}

	public static function publish_file19_event( $contract_event, $actor_id, array $data = array() ) {
		$map = array(
			'LegacyMigrationBatchCompleted.v1' => 'File04.LegacyMigrationBatchCompleted',
			'LegacyRecordQuarantined.v1'       => 'File04.LegacyRecordQuarantined',
			'LegacyCutoverCompleted.v1'        => 'File04.LegacyCutoverCompleted',
			'LegacyAdapterRetired.v1'          => 'File04.LegacyAdapterRetired',
		);
		if ( ! isset( $map[ $contract_event ] ) || ! is_int( $actor_id ) || $actor_id <= 0 ) {
			return new WP_Error( 'snfla_file19_event_identity_invalid', 'File 19 event identity is invalid.' );
		}

		$event_seed = array(
			'contract_event' => $contract_event,
			'actor_id'       => $actor_id,
			'data'           => SNFLA_Checksum::canonicalize( $data ),
			'runtime'        => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '',
		);
		$event = array(
			'producer'        => self::PRODUCER_KEY,
			'owner'           => 'File 04',
			'event_id'        => 'file04:' . strtolower( str_replace( array( '.', 'Legacy' ), array( '-', '' ), $contract_event ) ) . ':' . substr( SNFLA_Checksum::hash( $event_seed ), 0, 32 ),
			'event_type'      => $map[ $contract_event ],
			'schema_version'  => '1.0.0',
			'occurred_at'     => gmdate( 'c' ),
			'recipients'      => array( array( 'user_id' => $actor_id ) ),
			'category'        => 'system',
			'priority'        => 'normal',
			'sensitivity'     => 'standard',
			'trace_id'        => wp_generate_uuid4(),
			'source_version'  => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '',
			'idempotency_key' => SNFLA_Checksum::hash( $event_seed ),
			'data'            => array_merge(
				array(
					'contract_event' => $contract_event,
					'file_number'    => '04',
					'canonical_owner'=> 'File 21',
				),
				SNFLA_Audit::redact( $data )
			),
		);

		do_action( 'snfla_file04_domain_event_v1', $contract_event, $event );

		if ( ! function_exists( 'sun_ingest_domain_event' ) ) {
			do_action( 'snfla_operational_alert_v1', 'file19_event_delivery_unavailable', array( 'event' => $contract_event ) );
			return array( 'delivered' => false, 'reason' => 'file19_unavailable', 'event_id' => $event['event_id'] );
		}

		$result = sun_ingest_domain_event( $event );
		if ( is_wp_error( $result ) ) {
			do_action( 'snfla_operational_alert_v1', 'file19_event_delivery_failed', array( 'event' => $contract_event, 'code' => $result->get_error_code() ) );
			return $result;
		}
		return array( 'delivered' => true, 'event_id' => $event['event_id'], 'result' => $result );
	}

	public static function file24_manifests( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::file24_manifest();
		return array_slice( $manifests, 0, 99 );
	}

	public static function file24_manifest() {
		return array(
			'module_key'             => 'file-04',
			'name'                   => 'Sabri News Feed Legacy Foundation Adapter',
			'version'                => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '0.0.0',
			'owner'                  => 'File 04',
			'posture'                => 'foundation',
			'data_classes'           => array( 'C1 migration metadata', 'C2 legacy identity references', 'C5 audit evidence references' ),
			'public_routes'          => array(),
			'private_routes'         => array(
				'/wp-admin/tools.php',
				'/wp-json/sabri/file04/v1/status/',
				'/wp-json/sabri/file04/v1/plan/system-check/',
				'/wp-json/sabri/file04/v1/plan/metrics',
			),
			'tables'                 => array( 'snfla-migration-map', 'snfla-conflict-ledger', 'snfla-audit-evidence' ),
			'files'                  => array( 'file04-plugin-package', 'migration-runbooks', 'rollback-runbooks' ),
			'capabilities'           => array( SNFLA_Capabilities::CAP_RUN, SNFLA_Capabilities::CAP_REVIEW, SNFLA_Capabilities::CAP_RETIRE ),
			'external_vendors'       => array(),
			'secret_classes'         => array(),
			'privacy_operations'     => array( 'legacy-source-retention', 'source-only-quarantine', 'non-destructive-rollback' ),
			'exporters'              => array(),
			'erasers'                => array(),
			'emergency_callbacks'    => array( 'adapter-safe-mode', 'migration-rollback', 'adapter-retirement' ),
			'last_security_test'     => '',
			'verification_level'     => 'asvs-l2-adapter',
			'contract_version'       => self::CONTRACT_VERSION,
			'canonical_data_owner'   => 'File 21 after verified migration',
			'canonical_action_owner' => 'File 21 publishing; File 04 migration only',
			'evidence_source'        => 'repo:file04-' . ( defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : 'unknown' ),
			'degraded_behavior'      => 'Legacy writes remain disabled; protected mutations fail closed; read-only diagnostics remain available to authorized operators.',
			'release_gate'           => 'Exact-head QA, staging contracts, restore rehearsal, Founder approval, controlled deployment and live verification.',
		);
	}

	public static function file24_contract_state( $state, $definition = array() ) {
		unset( $definition );
		if ( class_exists( 'SNFLA_Database' ) && SNFLA_Database::schema_healthy() ) {
			return 'available';
		}
		return 'degraded';
	}

	public static function foundation_manifest( $existing = null ) {
		$manifest = array(
			'module_key'        => 'file-04',
			'owner_file'        => '04',
			'owner_name'        => 'File 04 Legacy Publishing Adapter',
			'slug'              => 'file-04-legacy-publishing-adapter',
			'namespace_prefix'  => 'snfla',
			'software_version'  => defined( 'SNFLA_VERSION' ) ? SNFLA_VERSION : '0.0.0',
			'contract_version'  => self::CONTRACT_VERSION,
			'state'             => class_exists( 'SNFLA_Schema' ) && 'retired' === SNFLA_Schema::state() ? 'retired' : 'active',
			'required'          => array(),
			'optional'          => array(),
			'capabilities'      => array( SNFLA_Capabilities::CAP_RUN, SNFLA_Capabilities::CAP_REVIEW, SNFLA_Capabilities::CAP_RETIRE ),
			'commands'          => array( 'inventory', 'dry-run', 'migrate', 'reconcile', 'cutover', 'rollback', 'retire' ),
			'queries'           => array( 'status', 'system-check', 'metrics', 'legacy-resolution' ),
			'events'            => array( 'LegacyMigrationBatchCompleted.v1', 'LegacyRecordQuarantined.v1', 'LegacyCutoverCompleted.v1', 'LegacyAdapterRetired.v1' ),
			'routes'            => array( '/wp-json/sabri/file04/v1/status/', '/wp-json/sabri/file04/v1/plan/system-check/' ),
			'data_classes'      => array( 'legacy-publication-source', 'migration-map', 'conflict-ledger', 'audit-evidence' ),
			'health'            => array( 'system_check' => '/wp-json/sabri/file04/v1/plan/system-check/', 'degraded_reads' => true ),
			'canonical_entities'=> array(),
			'writes'            => array(),
			'global_shell_owner'=> false,
			'application_shell_owner'=> false,
		);
		return is_array( $existing ) ? array_replace( $existing, $manifest ) : $manifest;
	}

	public static function route_context_contract( $existing = null ) {
		$contract = array(
			'contract_version' => self::CONTRACT_VERSION,
			'owner_module'     => 'file-04',
			'shell_owner'      => 'file-20',
			'routes'           => array(
				'status'       => array( 'path' => '/wp-json/sabri/file04/v1/status/', 'layout_context' => 'system_recovery', 'public' => false ),
				'system_check' => array( 'path' => '/wp-json/sabri/file04/v1/plan/system-check/', 'layout_context' => 'system_recovery', 'public' => false ),
			),
			'public_ui_owner'   => 'File 20 / File 25',
			'legacy_route_mode'=> 'redirect-or-hidden',
		);
		return is_array( $existing ) ? array_replace_recursive( $existing, $contract ) : $contract;
	}

	/**
	 * Explicit, operator-authorized registration into File 01's canonical
	 * registry. This is never run automatically.
	 */
	public static function register_foundation_contracts( $actor_id ) {
		if ( ! is_int( $actor_id ) || $actor_id <= 0 ) {
			return new WP_Error( 'snfla_foundation_actor_invalid', 'A canonical positive operator identity is required.', array( 'status' => 400 ) );
		}
		$authorized = SNFLA_Capabilities::revalidate_actor( $actor_id, SNFLA_Capabilities::CAP_RUN );
		if ( is_wp_error( $authorized ) ) { return $authorized; }
		if ( ! class_exists( 'SPF_Registry' ) ) {
			return new WP_Error( 'snfla_file01_registry_unavailable', 'File 01 registry is unavailable.', array( 'status' => 503 ) );
		}

		$manifest = self::foundation_manifest();
		$current  = SPF_Registry::get_module( 'file-04' );
		$manifest_context = array( 'purpose' => 'file04_explicit_contract_registration' );
		if ( is_array( $current ) && isset( $current['record_version'] ) ) {
			$manifest_context['expected_version'] = (int) $current['record_version'];
		}
		$registered = SPF_Registry::register_manifest( $manifest, $manifest_context );
		if ( is_wp_error( $registered ) ) { return $registered; }

		$routes = array(
			array( 'route_key' => 'file04-status', 'route_path' => '/wp-json/sabri/file04/v1/status/', 'owner_module' => 'file-04', 'layout_context' => 'system_recovery', 'status' => 'active', 'destination' => '', 'redirects' => array() ),
			array( 'route_key' => 'file04-system-check', 'route_path' => '/wp-json/sabri/file04/v1/plan/system-check/', 'owner_module' => 'file-04', 'layout_context' => 'system_recovery', 'status' => 'active', 'destination' => '', 'redirects' => array() ),
		);
		$mapped = array();
		$existing_routes = SPF_Registry::list_routes();
		if ( is_wp_error( $existing_routes ) ) { return $existing_routes; }
		foreach ( $routes as $route ) {
			$route_context = array( 'purpose' => 'file04_explicit_route_registration' );
			foreach ( (array) $existing_routes as $existing_route ) {
				if ( is_array( $existing_route ) && (string) ( $existing_route['route_key'] ?? '' ) === $route['route_key'] && isset( $existing_route['record_version'] ) ) {
					$route_context['expected_version'] = (int) $existing_route['record_version'];
					break;
				}
			}
			$mapped_route = SPF_Registry::map_route( $route, $route_context );
			if ( is_wp_error( $mapped_route ) ) { return $mapped_route; }
			$mapped[] = $mapped_route;
		}
		return array( 'manifest' => $registered, 'routes' => $mapped );
	}
}
